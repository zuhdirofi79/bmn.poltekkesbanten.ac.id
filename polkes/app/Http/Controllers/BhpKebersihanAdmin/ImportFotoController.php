<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Services\BhpKebersihan\FotoImport\FotoImportService;
use App\Services\BhpKebersihan\FotoImport\OcrUnavailableException;
use App\Support\BhpKebersihan\BhpAccess;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImportFotoController extends Controller
{
    public function __construct(private FotoImportService $import)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorizeMaster($request);

        return response()->json(['ok' => true] + $this->import->ocrStatus());
    }

    public function ocr(Request $request): JsonResponse
    {
        $this->authorizeMaster($request);
        @set_time_limit(180);

        $status = $this->import->ocrStatus();
        if (! $status['available']) {
            return response()->json([
                'ok' => false,
                'code' => 'ocr_unavailable',
                'message' => $status['message'],
            ], 503);
        }

        $maxFiles = (int) config('bhp_kebersihan.ocr.max_files', 10);
        $maxKb = (int) config('bhp_kebersihan.ocr.max_file_kb', 8192);

        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:' . $maxFiles],
            'fotos.*' => ['required', 'file', 'max:' . $maxKb, 'mimes:jpg,jpeg,png,webp'],
        ], [
            'fotos.required' => 'Pilih minimal satu foto tabel barang.',
            'fotos.max' => 'Maksimal ' . $maxFiles . ' foto per batch.',
        ]);

        $files = array_values(array_filter(
            (array) $request->file('fotos', []),
            fn ($f) => $f instanceof UploadedFile
        ));

        try {
            $preview = $this->import->previewFromUploads(
                $files,
                (int) BhpAdminSession::userId($request)
            );
        } catch (OcrUnavailableException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'ocr_unavailable',
                'message' => $e->getMessage(),
            ], 503);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'code' => 'ocr_failed',
                'message' => 'Gagal membaca foto. ' . $e->getMessage(),
            ], 422);
        }

        if (($preview['rows'] ?? []) === []) {
            return response()->json([
                'ok' => false,
                'code' => 'empty',
                'message' => 'Tidak ada baris tabel yang terbaca. Pastikan foto jelas, tegak, dan menampilkan tabel tercetak. Coretan tangan diabaikan.',
                'provider' => $preview['provider'] ?? null,
                'photo_count' => $preview['photo_count'] ?? count($files),
                'rows' => [],
                'summary' => $preview['summary'] ?? [],
            ], 422);
        }

        return response()->json(['ok' => true] + $preview);
    }

    public function commit(Request $request): JsonResponse
    {
        $this->authorizeMaster($request);
        @set_time_limit(180);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.nama_barang' => ['nullable', 'string', 'max:200'],
            'rows.*.merk' => ['nullable', 'string', 'max:255'],
            'rows.*.satuan' => ['nullable', 'string', 'max:50'],
            'rows.*.jumlah' => ['nullable'],
            'rows.*.kode_barang' => ['nullable', 'string', 'max:100'],
            'rows.*.status' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $result = $this->import->commit(
                $data['rows'],
                (int) BhpAdminSession::userId($request)
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'validation',
                'message' => 'Import dibatalkan. Tidak ada data yang disimpan.',
                'errors' => $e->errors()['rows'] ?? collect($e->errors())->flatten()->all(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'code' => 'commit_failed',
                'message' => 'Gagal menyimpan batch. Tidak ada perubahan yang diterapkan.',
            ], 500);
        }

        return response()->json(['ok' => true] + $result);
    }

    private function authorizeMaster(Request $request): void
    {
        if (! BhpAccess::canManageMaster(BhpAdminSession::role($request))) {
            abort(403, 'Hanya admin Master Data BHP yang dapat import dari foto.');
        }
    }
}
