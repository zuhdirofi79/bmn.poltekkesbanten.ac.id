<?php

declare(strict_types=1);

namespace Tests\Unit\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Services\BhpKebersihan\BarcodeService;
use App\Support\BhpKebersihan\PublicUploadPath;
use Tests\TestCase;

class BarcodeUrlTest extends TestCase
{
    public function test_file_url_uses_public_uploads_url_not_storage(): void
    {
        config([
            'bhp_kebersihan.uploads_path' => storage_path('app/testing-bhp-uploads'),
            'bhp_kebersihan.uploads_url' => 'https://bmn.poltekkesbanten.ac.id/uploads',
        ]);

        $dir = PublicUploadPath::barcodeDir();
        PublicUploadPath::ensureDir($dir);

        $barang = new BhpKebersihanBarang();
        $barang->id = 1;
        $barang->kode_barang = '3.05.02.01.012.KD';
        $barang->nama_barang = 'hit';
        $barang->barcode_token = 'wadi2oldeddpf0imhi6vaptbbzbg5ulv';

        $service = $this->app->make(BarcodeService::class);
        $relative = $service->relativePath($barang);
        $absolute = PublicUploadPath::absolute($relative);
        file_put_contents($absolute, str_repeat('PNG', 50));
        $barang->barcode_path = $relative;

        $url = $service->fileUrl($barang);
        $this->assertSame(
            'https://bmn.poltekkesbanten.ac.id/uploads/barcode/bhp-kebersihan/' . basename($relative),
            $url
        );
        $this->assertStringStartsWith('https://bmn.poltekkesbanten.ac.id/uploads/barcode/bhp-kebersihan/', (string) $url);
        $this->assertStringNotContainsString('/storage/', (string) $url);
        $this->assertTrue($service->hasValidFile($barang));

        $diag = $service->diagnose($barang);
        $this->assertTrue($diag['file_exists']);
        $this->assertSame($url, $diag['public_url']);
        $this->assertSame(
            url('/public/bhp-kebersihan/barang/wadi2oldeddpf0imhi6vaptbbzbg5ulv'),
            $diag['scan_url']
        );

        @unlink($absolute);
    }

    public function test_missing_file_does_not_return_url_even_if_path_in_database(): void
    {
        $barang = new BhpKebersihanBarang();
        $barang->id = 99;
        $barang->kode_barang = 'MISSING-1';
        $barang->barcode_token = 'tokentokentokentokentokentoken12';
        $barang->barcode_path = 'uploads/barcode/bhp-kebersihan/tidak-ada.png';

        $service = $this->app->make(BarcodeService::class);
        $this->assertFalse($service->hasValidFile($barang));
        $this->assertNull($service->fileUrl($barang));
    }
}
