<?php

declare(strict_types=1);

namespace App\Services\BhpKebersihan\FotoImport;

use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;

final class OcrEngine
{
    public const PROMPT = <<<'PROMPT'
Anda mengekstrak tabel BARANG HABIS PAKAI KEBERSIHAN dari foto dokumen tercetak (surat jalan / usulan belanja).

WAJIB:
- Baca HANYA teks cetak di dalam tabel.
- ABAIKAN coretan tangan, centang, tulisan "Done", "Kurang ...", "Baru ...", angka di margin, paraf, dan kolom keterangan checklist.
- Beberapa foto adalah halaman lanjutan dari SATU daftar (mis. 1–36 lalu 37–48). Gabungkan sebagai satu daftar berurutan. Jangan anggap halaman baru sebagai dataset baru.
- Jangan mengarang baris yang tidak terlihat.
- Jangan mengambil kop surat, nama perusahaan, tanda tangan, atau footer.
- Volume bisa tertulis "150 Jerigen" — pecah menjadi jumlah numerik dan satuan.
- Jika merk kosong atau hanya tanda strip, isi merk dengan string kosong.
- Kode barang hampir pasti tidak tercetak; biarkan null.

Kembalikan JSON murni:
{"rows":[{"no":1,"nama_barang":"...","merk":"...","jumlah":150,"satuan":"Jerigen","kode_barang":null,"confidence":"high"}]}

confidence: high jika cetakan jelas; medium jika agak kabur; low jika ragu.
PROMPT;

    /**
     * @return array{available: bool, provider: string, message: string}
     */
    public function status(): array
    {
        $provider = $this->resolveProvider();
        if ($provider === null) {
            return [
                'available' => false,
                'provider' => 'none',
                'message' => 'Mesin OCR belum tersedia di server. Set BHP_OCR_PROVIDER dan BHP_OCR_API_KEY di .env (rekomendasi shared hosting: gemini dari Google AI Studio). Tesseract biasanya tidak terpasang di Hostinger.',
            ];
        }

        return [
            'available' => true,
            'provider' => $provider,
            'message' => 'OCR siap (' . $provider . '). Hasil wajib diperiksa sebelum disimpan.',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->status()['available'];
    }

    /**
     * @param  list<string>  $imagePaths
     * @return list<array<string,mixed>>
     */
    public function extractRows(array $imagePaths): array
    {
        $provider = $this->resolveProvider();
        if ($provider === null) {
            throw new OcrUnavailableException($this->status()['message']);
        }

        $prepared = [];
        foreach ($imagePaths as $i => $path) {
            $prepared[] = $this->prepareImage($path, $provider);
        }

        $rows = match ($provider) {
            'gemini' => $this->extractGemini($prepared),
            'openai' => $this->extractOpenAi($prepared),
            'ocrspace' => $this->extractOcrSpace($prepared),
            'tesseract' => $this->extractTesseract($prepared),
            default => throw new OcrUnavailableException('Provider OCR tidak dikenali.'),
        };

        return $this->filterJunk($rows);
    }

    public function resolveProvider(): ?string
    {
        $configured = strtolower(trim((string) config('bhp_kebersihan.ocr.provider', 'auto')));
        $key = trim((string) config('bhp_kebersihan.ocr.api_key', ''));

        if (in_array($configured, ['gemini', 'openai', 'ocrspace', 'tesseract'], true)) {
            if ($configured === 'tesseract') {
                return $this->tesseractBinary() !== null ? 'tesseract' : null;
            }

            return $key !== '' ? $configured : null;
        }

        if ($this->tesseractBinary() !== null) {
            return 'tesseract';
        }

        if ($key === '') {
            return null;
        }

        if (str_starts_with($key, 'sk-')) {
            return 'openai';
        }
        if (str_starts_with($key, 'AIza')) {
            return 'gemini';
        }

        return 'gemini';
    }

    /**
     * @return array{path: string, mime: string, page: int}
     */
    private function prepareImage(string $path, string $provider): array
    {
        $maxWidth = $provider === 'ocrspace' ? 1280 : 1920;
        $quality = $provider === 'ocrspace' ? 70 : 82;
        $dir = dirname($path);
        $out = $dir . '/prep-' . basename($path) . '.jpg';

        try {
            Image::make($path)
                ->orientate()
                ->resize($maxWidth, $maxWidth, function ($c): void {
                    $c->aspectRatio();
                    $c->upsize();
                })
                ->save($out, $quality, 'jpg');
            $use = is_file($out) ? $out : $path;
        } catch (\Throwable) {
            $use = $path;
        }

        $mime = mime_content_type($use) ?: 'image/jpeg';

        return ['path' => $use, 'mime' => $mime, 'page' => 0];
    }

    /**
     * @param  list<array{path: string, mime: string, page: int}>  $images
     * @return list<array<string,mixed>>
     */
    private function extractGemini(array $images): array
    {
        $key = trim((string) config('bhp_kebersihan.ocr.api_key'));
        $model = trim((string) config('bhp_kebersihan.ocr.model')) ?: 'gemini-2.0-flash';
        $parts = [['text' => self::PROMPT]];
        foreach ($images as $i => $img) {
            $parts[] = ['text' => 'Foto halaman ' . ($i + 1) . ':'];
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $img['mime'] ?: 'image/jpeg',
                    'data' => base64_encode((string) file_get_contents($img['path'])),
                ],
            ];
        }

        $models = array_values(array_unique([$model, 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-flash-latest']));
        $lastError = 'Gemini gagal.';

        foreach ($models as $tryModel) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                . rawurlencode($tryModel)
                . ':generateContent?key=' . urlencode($key);

            $response = Http::timeout((int) config('bhp_kebersihan.ocr.timeout', 90))
                ->acceptJson()
                ->post($url, [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->status() === 404) {
                $lastError = 'Model Gemini tidak ditemukan: ' . $tryModel;
                continue;
            }

            if (! $response->successful()) {
                $lastError = 'Gemini error HTTP ' . $response->status();
                continue;
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $rows = $this->rowsFromJson($text);
            if ($rows !== []) {
                return $this->stampPages($rows, count($images));
            }
            $lastError = 'Gemini tidak mengembalikan baris tabel.';
        }

        if (count($images) > 1) {
            $merged = [];
            foreach ($images as $i => $img) {
                $pageRows = $this->extractGemini([$img]);
                foreach ($pageRows as $row) {
                    $row['source_page'] = $i + 1;
                    $merged[] = $row;
                }
            }
            if ($merged !== []) {
                return $merged;
            }
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @param  list<array{path: string, mime: string, page: int}>  $images
     * @return list<array<string,mixed>>
     */
    private function extractOpenAi(array $images): array
    {
        $key = trim((string) config('bhp_kebersihan.ocr.api_key'));
        $model = trim((string) config('bhp_kebersihan.ocr.model')) ?: 'gpt-4o-mini';

        $content = [['type' => 'text', 'text' => self::PROMPT]];
        foreach ($images as $i => $img) {
            $b64 = base64_encode((string) file_get_contents($img['path']));
            $mime = $img['mime'] ?: 'image/jpeg';
            $content[] = ['type' => 'text', 'text' => 'Foto halaman ' . ($i + 1) . ':'];
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64],
            ];
        }

        $response = Http::withToken($key)
            ->timeout((int) config('bhp_kebersihan.ocr.timeout', 90))
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI error HTTP ' . $response->status());
        }

        $text = (string) data_get($response->json(), 'choices.0.message.content', '');
        $rows = $this->rowsFromJson($text);
        if ($rows === []) {
            throw new \RuntimeException('OpenAI tidak mengembalikan baris tabel.');
        }

        return $this->stampPages($rows, count($images));
    }

    /**
     * @param  list<array{path: string, mime: string, page: int}>  $images
     * @return list<array<string,mixed>>
     */
    private function extractOcrSpace(array $images): array
    {
        $key = trim((string) config('bhp_kebersihan.ocr.api_key'));
        $parser = new TextTableParser();
        $all = [];

        foreach ($images as $i => $img) {
            $response = Http::timeout((int) config('bhp_kebersihan.ocr.timeout', 90))
                ->attach('file', (string) file_get_contents($img['path']), 'page.jpg')
                ->post('https://api.ocr.space/parse/image', [
                    'apikey' => $key,
                    'language' => 'eng',
                    'isTable' => 'true',
                    'scale' => 'true',
                    'OCREngine' => 2,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('OCR.space error HTTP ' . $response->status());
            }

            $json = $response->json();
            if (! empty($json['IsErroredOnProcessing'])) {
                $msg = $json['ErrorMessage'] ?? 'OCR.space gagal memproses gambar.';
                throw new \RuntimeException(is_array($msg) ? implode(' ', $msg) : (string) $msg);
            }

            $text = (string) data_get($json, 'ParsedResults.0.ParsedText', '');
            $pageRows = $parser->parse($text, $i + 1);
            foreach ($pageRows as $row) {
                $all[] = $row;
            }
        }

        return $all;
    }

    /**
     * @param  list<array{path: string, mime: string, page: int}>  $images
     * @return list<array<string,mixed>>
     */
    private function extractTesseract(array $images): array
    {
        $bin = $this->tesseractBinary();
        if ($bin === null) {
            throw new OcrUnavailableException('Tesseract tidak ditemukan di server.');
        }

        $parser = new TextTableParser();
        $all = [];

        foreach ($images as $i => $img) {
            $redir = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
            $cmd = escapeshellarg($bin)
                . ' ' . escapeshellarg($img['path'])
                . ' stdout -l ind+eng --psm 6 ' . $redir;
            $text = (string) shell_exec($cmd);
            if (trim($text) === '') {
                $cmd = escapeshellarg($bin)
                    . ' ' . escapeshellarg($img['path'])
                    . ' stdout --psm 6 ' . $redir;
                $text = (string) shell_exec($cmd);
            }
            foreach ($parser->parse($text, $i + 1) as $row) {
                $row['needs_review'] = true;
                $all[] = $row;
            }
        }

        return $all;
    }

    private function tesseractBinary(): ?string
    {
        $configured = trim((string) config('bhp_kebersihan.ocr.tesseract_bin', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $out = [];
        $code = 0;
        @exec('tesseract --version 2>&1', $out, $code);
        if ($code === 0) {
            return 'tesseract';
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rowsFromJson(string $text): array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m) === 1) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = $decoded['rows'] ?? $decoded;
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nama = VolumeParser::clean((string) ($row['nama_barang'] ?? $row['nama'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $out[] = [
                'no' => $row['no'] ?? null,
                'nama_barang' => $nama,
                'merk' => VolumeParser::clean((string) ($row['merk'] ?? $row['spesifikasi'] ?? '')),
                'jumlah' => $row['jumlah'] ?? $row['volume'] ?? null,
                'satuan' => $row['satuan'] ?? null,
                'kode_barang' => $row['kode_barang'] ?? null,
                'confidence' => $row['confidence'] ?? 'medium',
                'needs_review' => strtolower((string) ($row['confidence'] ?? '')) === 'low',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function stampPages(array $rows, int $photoCount): array
    {
        foreach ($rows as $i => $row) {
            if (! isset($row['source_page'])) {
                $rows[$i]['source_page'] = 1;
            }
        }
        unset($photoCount);

        return $rows;
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function filterJunk(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $nama = VolumeParser::fold((string) ($row['nama_barang'] ?? ''));
            if ($nama === '' || preg_match('/^(nama barang|spesifikasi|keterangan|volume|done|cv\.|jakarta)/u', $nama) === 1) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
