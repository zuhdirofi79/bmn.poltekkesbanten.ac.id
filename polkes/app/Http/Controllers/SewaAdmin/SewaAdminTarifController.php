<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Http\Controllers\Controller;
use App\Models\BMN\BmnSewaTarif;
use App\Models\BMN\BmnSewaTarifFoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SewaAdminTarifController extends Controller
{
    public function index(): View
    {
        $tarifs = BmnSewaTarif::query()
            ->withCount('photos')
            ->with('primaryPhoto')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('kategori');

        return view('sewa_admin.tarif.index', compact('tarifs'));
    }

    public function show(int $id): View
    {
        $tarif = BmnSewaTarif::query()
            ->with(['photos' => function ($q): void {
                $q->orderBy('sort_order')->orderBy('id');
            }])
            ->findOrFail($id);

        return view('sewa_admin.tarif.show', compact('tarif'));
    }

    public function uploadPhotos(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'fotos' => ['required', 'array', 'min:1'],
            'fotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $tarif = BmnSewaTarif::query()->findOrFail($id);
        $userId = (int) ($request->user()?->id ?? 0);
        $caption = trim((string) $request->input('caption', ''));
        $hasPrimary = $tarif->photos()->where('is_primary', 1)->exists();

        $maxSort = (int) $tarif->photos()->max('sort_order');
        $uploaded = 0;

        foreach ($request->file('fotos', []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $this->storeFoto($file, $tarif);
            $maxSort++;

            BmnSewaTarifFoto::create([
                'tarif_id' => $tarif->id,
                'foto_path' => $path,
                'foto_original_name' => (string) $file->getClientOriginalName(),
                'caption' => $caption !== '' ? $caption : null,
                'sort_order' => $maxSort,
                'is_primary' => $hasPrimary ? 0 : 1,
                'uploaded_by' => $userId > 0 ? $userId : null,
            ]);

            $hasPrimary = true;
            $uploaded++;
        }

        if ($uploaded === 0) {
            return back()->with('error', 'Tidak ada foto valid yang berhasil diunggah.');
        }

        return back()->with('success', $uploaded.' foto berhasil diunggah.');
    }

    public function deletePhoto(Request $request, int $tarifId, int $fotoId): RedirectResponse
    {
        $foto = BmnSewaTarifFoto::query()
            ->where('tarif_id', $tarifId)
            ->findOrFail($fotoId);

        $wasPrimary = (bool) $foto->is_primary;
        $this->deleteFotoFile($foto->foto_path);
        $foto->delete();

        if ($wasPrimary) {
            $next = BmnSewaTarifFoto::query()
                ->where('tarif_id', $tarifId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            if ($next !== null) {
                $next->update(['is_primary' => 1]);
            }
        }

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    public function setPrimary(int $tarifId, int $fotoId): RedirectResponse
    {
        DB::transaction(function () use ($tarifId, $fotoId): void {
            BmnSewaTarifFoto::query()
                ->where('tarif_id', $tarifId)
                ->update(['is_primary' => 0]);

            $foto = BmnSewaTarifFoto::query()
                ->where('tarif_id', $tarifId)
                ->findOrFail($fotoId);

            $foto->update(['is_primary' => 1]);
        });

        return back()->with('success', 'Foto utama diperbarui.');
    }

    public function movePhoto(Request $request, int $tarifId, int $fotoId): RedirectResponse
    {
        $direction = (string) $request->input('direction', '');
        abort_unless(in_array($direction, ['up', 'down'], true), 400);

        DB::transaction(function () use ($tarifId, $fotoId, $direction): void {
            $photos = BmnSewaTarifFoto::query()
                ->where('tarif_id', $tarifId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $index = $photos->search(fn (BmnSewaTarifFoto $f) => (int) $f->id === $fotoId);
            if ($index === false) {
                throw new \RuntimeException('Foto tidak ditemukan.');
            }

            $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swapIndex < 0 || $swapIndex >= $photos->count()) {
                return;
            }

            $current = $photos[$index];
            $target = $photos[$swapIndex];
            $currentOrder = (int) $current->sort_order;
            $targetOrder = (int) $target->sort_order;

            $current->update(['sort_order' => $targetOrder]);
            $target->update(['sort_order' => $currentOrder]);
        });

        return back()->with('success', 'Urutan foto diperbarui.');
    }

    private function storeFoto(UploadedFile $file, BmnSewaTarif $tarif): string
    {
        $dirAbsolute = bmn_upload_path();
        if (! is_dir($dirAbsolute)) {
            @mkdir($dirAbsolute, 0755, true);
        }

        if (! is_dir($dirAbsolute) || ! is_writable($dirAbsolute)) {
            throw new \RuntimeException('Folder upload foto sewa tidak tersedia.');
        }

        $slug = Str::slug($tarif->nama_layanan) !== '' ? Str::slug($tarif->nama_layanan) : 'layanan';
        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: 'jpg';
        $filename = sprintf(
            'sewa-tarif-%s-%s-%s.%s',
            $slug,
            Carbon::now()->format('Ymd'),
            Str::random(8),
            $ext
        );

        $file->move($dirAbsolute, $filename);

        return BmnSewaTarifFoto::FOTO_DIR.'/'.$filename;
    }

    private function deleteFotoFile(?string $path): void
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($normalized, BmnSewaTarifFoto::FOTO_DIR.'/')) {
            $absolute = bmn_upload_path(basename($normalized));
            if (is_file($absolute)) {
                @unlink($absolute);
            }

            return;
        }

        $absolute = public_path($normalized);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
