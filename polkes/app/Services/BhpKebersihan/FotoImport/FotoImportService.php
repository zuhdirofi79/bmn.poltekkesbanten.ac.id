<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Services\BhpKebersihan\BarcodeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FotoImportService
{
    /** @var list<string> */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

    public function __construct(
        private OcrEngine $ocr,
        private PreviewBuilder $preview,
        private BarcodeService $barcode,
        private KodeBarangAllocator $allocator,
    ) {
    }

    /**
     * @return array{available: bool, provider: string, message: string}
     */
    public function ocrStatus(): array
    {
        return $this->ocr->status();
    }

    /**
     * OCR + preview. Tidak menulis ke database.
     *
     * @param  list<UploadedFile>  $files
     * @return array<string,mixed>
     */
    public function previewFromUploads(array $files, int $adminId): array
    {
        $tmpDir = storage_path('app/tmp/bhp-ocr/' . Str::lower(Str::random(16)));
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Gagal membuat folder sementara OCR.');
        }

        $paths = [];
        try {
            foreach ($files as $i => $file) {
                $this->assertSafeImage($file);
                $ext = $this->safeExtension($file);
                $name = 'p' . ($i + 1) . '-' . Str::lower(Str::random(8)) . '.' . $ext;
                $target = $tmpDir . '/' . $name;
                $file->move($tmpDir, $name);
                if (! is_file($target)) {
                    throw new \RuntimeException('Gagal menyimpan foto sementara.');
                }
                $paths[] = $target;
            }

            $rawRows = $this->ocr->extractRows($paths);
            $built = $this->preview->build($rawRows);

            Log::info('BHP import foto OCR preview', [
                'admin_id' => $adminId,
                'photo_count' => count($paths),
                'ocr_rows' => count($rawRows),
                'preview_rows' => count($built['rows']),
                'provider' => $this->ocr->resolveProvider(),
                'summary' => $built['summary'],
            ]);

            return [
                'provider' => $this->ocr->resolveProvider(),
                'photo_count' => count($paths),
                'rows' => $built['rows'],
                'summary' => $built['summary'],
            ];
        } finally {
            $this->deleteDir($tmpDir);
        }
    }

    /**
     * Simpan batch setelah admin review. All-or-nothing kecuali baris skip.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{inserted: int, skipped: int, barcodes_failed: int, message: string}
     */
    public function commit(array $rows, int $adminId): array
    {
        $classified = $this->classifyForCommit($rows);
        if ($classified['errors'] !== []) {
            throw ValidationException::withMessages([
                'rows' => $classified['errors'],
            ]);
        }

        $toInsert = $classified['insert'];
        $skipped = $classified['skipped'];

        if ($toInsert === []) {
            return [
                'inserted' => 0,
                'skipped' => $skipped,
                'barcodes_failed' => 0,
                'message' => $skipped > 0
                    ? "0 barang ditambahkan. {$skipped} barang dilewati karena sudah ada / duplikat."
                    : 'Tidak ada barang yang disimpan.',
            ];
        }

        $created = [];
        DB::transaction(function () use ($toInsert, $adminId, &$created): void {
            $existing = BhpKebersihanBarang::query()->pluck('kode_barang')->all();
            $reserved = [];

            foreach ($toInsert as $row) {
                $kode = VolumeParser::clean((string) ($row['kode_barang'] ?? ''));
                if ($kode === '' || $this->kodeTaken($kode, $existing, $reserved)) {
                    $kode = $this->allocator->nextCodes(1, $existing, $reserved)[0] ?? ('KBR-01-001');
                }
                $reserved[] = $kode;

                $created[] = BhpKebersihanBarang::create([
                    'kode_barang' => $kode,
                    'nama_barang' => VolumeParser::clean((string) $row['nama_barang']),
                    'merk' => VolumeParser::clean((string) ($row['merk'] ?? '')) ?: null,
                    'satuan' => VolumeParser::clean((string) $row['satuan']),
                    'stok_awal' => max(0, (int) $row['jumlah']),
                    'harga_satuan' => null,
                    'barcode_token' => $this->barcode->generateToken(),
                    'status' => 'aktif',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]);
            }
        });

        $barcodeFailed = 0;
        foreach ($created as $barang) {
            try {
                $this->barcode->generateAndSave($barang);
            } catch (\Throwable $e) {
                $barcodeFailed++;
                report($e);
            }
        }

        $inserted = count($created);
        Log::info('BHP import foto commit', [
            'admin_id' => $adminId,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'barcodes_failed' => $barcodeFailed,
        ]);

        $message = "{$inserted} barang berhasil ditambahkan.";
        if ($skipped > 0) {
            $message .= " {$skipped} barang dilewati karena sudah ada.";
        }
        if ($barcodeFailed > 0) {
            $message .= " {$barcodeFailed} barcode gagal digenerate (bisa di-generate ulang dari Master Barang).";
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'barcodes_failed' => $barcodeFailed,
            'message' => $message,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{insert: list<array<string,mixed>>, skipped: int, errors: list<string>}
     */
    public function classifyForCommit(array $rows): array
    {
        $errors = [];
        $insert = [];
        $skipped = 0;
        $batchKeys = [];
        $batchKodes = [];

        $existing = BhpKebersihanBarang::query()->get(['id', 'kode_barang', 'nama_barang', 'merk', 'satuan']);
        $dbKeys = [];
        $dbKodes = [];
        foreach ($existing as $b) {
            $dbKeys[VolumeParser::normalizeKey((string) $b->nama_barang, (string) ($b->merk ?? ''), (string) $b->satuan)] = true;
            $dbKodes[strtoupper((string) $b->kode_barang)] = true;
        }

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $status = (string) ($row['status'] ?? 'ok');
            $nama = VolumeParser::clean((string) ($row['nama_barang'] ?? ''));
            $merk = VolumeParser::clean((string) ($row['merk'] ?? ''));
            $satuan = VolumeParser::clean((string) ($row['satuan'] ?? ''));
            $jumlah = $row['jumlah'] ?? null;
            $kode = VolumeParser::clean((string) ($row['kode_barang'] ?? ''));

            if ($status === 'perlu_diperiksa') {
                $errors[] = "Baris {$line} ({$nama}): masih bertanda Perlu diperiksa. Koreksi dulu atau hapus baris.";
                continue;
            }

            if ($nama === '') {
                $errors[] = "Baris {$line}: nama barang wajib diisi.";
                continue;
            }
            if ($satuan === '') {
                $errors[] = "Baris {$line}: satuan wajib diisi.";
                continue;
            }
            if (! is_numeric($jumlah) || (int) $jumlah < 0) {
                $errors[] = "Baris {$line}: jumlah/stok awal harus angka ≥ 0.";
                continue;
            }

            $key = VolumeParser::normalizeKey($nama, $merk, $satuan);
            if (isset($dbKeys[$key]) || isset($batchKeys[$key])) {
                $skipped++;
                continue;
            }
            $batchKeys[$key] = true;

            if ($kode !== '') {
                $kodeKey = strtoupper($kode);
                if (isset($dbKodes[$kodeKey]) || isset($batchKodes[$kodeKey])) {
                    $errors[] = "Baris {$line}: kode {$kode} sudah digunakan.";
                    continue;
                }
                $batchKodes[$kodeKey] = true;
            }

            $insert[] = [
                'nama_barang' => $nama,
                'merk' => $merk,
                'satuan' => $satuan,
                'jumlah' => (int) $jumlah,
                'kode_barang' => $kode,
            ];
        }

        return ['insert' => $insert, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function assertSafeImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['fotos' => 'Salah satu file gagal diunggah.']);
        }

        $maxKb = (int) config('bhp_kebersihan.ocr.max_file_kb', 8192);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['fotos' => 'Ukuran foto melebihi ' . $maxKb . ' KB.']);
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages(['fotos' => 'Hanya JPG, JPEG, PNG, atau WEBP yang diizinkan.']);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages(['fotos' => 'Ekstensi file tidak diizinkan.']);
        }

        $head = (string) file_get_contents($file->getRealPath() ?: $file->getPathname(), false, null, 0, 16);
        if (str_contains($head, '<?php') || str_starts_with(ltrim($head), '<svg') || str_starts_with(ltrim($head), '<html')) {
            throw ValidationException::withMessages(['fotos' => 'File bukan gambar yang valid.']);
        }
    }

    private function safeExtension(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());

        return match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $reserved
     */
    private function kodeTaken(string $kode, array $existing, array $reserved): bool
    {
        $key = strtoupper($kode);
        foreach (array_merge($existing, $reserved) as $item) {
            if (strtoupper((string) $item) === $key) {
                return true;
            }
        }

        return false;
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
