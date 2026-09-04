<?php

declare(strict_types=1);

namespace Tests\Unit\BhpKebersihan;

use App\Services\BhpKebersihan\FotoService;
use App\Support\BhpKebersihan\PublicUploadPath;
use Tests\TestCase;

class PublicUploadPathTest extends TestCase
{
    public function test_public_url_never_leaks_filesystem_path(): void
    {
        config([
            'bhp_kebersihan.uploads_path' => '/home/u708283844/domains/bmn.poltekkesbanten.ac.id/public_html/uploads',
            'bhp_kebersihan.uploads_url' => 'https://bmn.poltekkesbanten.ac.id/uploads',
            'bmn.foto_path' => '',
            'bmn.foto_url' => '',
        ]);

        $url = PublicUploadPath::publicUrl('uploads/barcode/bhp-kebersihan/bhp-1-hit.png');

        $this->assertSame(
            'https://bmn.poltekkesbanten.ac.id/uploads/barcode/bhp-kebersihan/bhp-1-hit.png',
            $url
        );
        $this->assertStringNotContainsString('storage/', $url);
        $this->assertStringNotContainsString('C:', $url);
        $this->assertStringNotContainsString('/home/', $url);
        $this->assertStringNotContainsString('/tmp/', $url);
    }

    public function test_normalizes_windows_and_storage_paths_to_web_relative(): void
    {
        $fromWindows = PublicUploadPath::normalizeRelative('C:\\tmp\\uploads\\barcode\\bhp-kebersihan\\abc.png');
        $fromStorage = PublicUploadPath::normalizeRelative('/var/www/storage/app/public/abc.png');

        $this->assertSame('uploads/barcode/bhp-kebersihan/abc.png', $fromWindows);
        $this->assertSame('uploads/barcode/bhp-kebersihan/abc.png', $fromStorage);
    }

    public function test_absolute_path_uses_public_html_uploads_root(): void
    {
        config([
            'bhp_kebersihan.uploads_path' => '/home/u708283844/public_html/uploads',
            'bhp_kebersihan.uploads_url' => 'https://bmn.poltekkesbanten.ac.id/uploads',
        ]);

        $this->assertSame(
            '/home/u708283844/public_html/uploads/barcode/bhp-kebersihan/x.png',
            PublicUploadPath::absolute('uploads/barcode/bhp-kebersihan/x.png')
        );
        $this->assertSame(
            '/home/u708283844/public_html/uploads/foto_bhp_kebersihan/hit.webp',
            PublicUploadPath::absolute('uploads/foto_bhp_kebersihan/hit.webp')
        );
    }

    public function test_infers_uploads_root_from_bmn_foto_path(): void
    {
        config([
            'bhp_kebersihan.uploads_path' => '',
            'bhp_kebersihan.uploads_url' => '',
            'bmn.foto_path' => '/home/u708283844/domains/bmn.poltekkesbanten.ac.id/public_html/uploads/foto_bmn',
            'bmn.foto_url' => 'https://bmn.poltekkesbanten.ac.id/uploads/foto_bmn',
        ]);

        $this->assertSame(
            '/home/u708283844/domains/bmn.poltekkesbanten.ac.id/public_html/uploads',
            PublicUploadPath::uploadsRoot()
        );
        $this->assertSame(
            'https://bmn.poltekkesbanten.ac.id/uploads',
            PublicUploadPath::uploadsUrl()
        );
    }

    public function test_foto_filename_is_sanitized(): void
    {
        $service = new FotoService();
        $name = $service->buildFilename('3.05.02.01.012.KD', 'Hit Spray ../etc', 'Contoh Merk', 'PNG');

        $this->assertRegExp('/^[a-z0-9._-]+\.png$/', $name);
        $this->assertStringNotContainsString('..', $name);
        $this->assertStringNotContainsString(' ', $name);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringContainsString('contoh-merk', $name);
        $this->assertStringEndsWith('.png', $name);
    }

    public function test_deprecated_logo_filenames_are_rejected(): void
    {
        $this->assertTrue(PublicUploadPath::isDeprecatedLogoFile('img/logo1.png'));
        $this->assertTrue(PublicUploadPath::isDeprecatedLogoFile('img/fav1.png'));
        $this->assertTrue(PublicUploadPath::isDeprecatedLogoFile('img/fav.png'));
        $this->assertFalse(PublicUploadPath::isDeprecatedLogoFile('img/logo.png'));
    }

    public function test_official_logo_prefers_document_root_logo_png(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhp-logo-root-' . uniqid('', true);
        $uploads = $root . DIRECTORY_SEPARATOR . 'uploads';
        $img = $root . DIRECTORY_SEPARATOR . 'img';
        mkdir($uploads, 0777, true);
        mkdir($img, 0777, true);

        $official = $img . DIRECTORY_SEPARATOR . 'logo.png';
        $old = $img . DIRECTORY_SEPARATOR . 'logo1.png';
        file_put_contents($official, $this->tinyPng());
        file_put_contents($old, $this->tinyPng());

        config([
            'bhp_kebersihan.uploads_path' => $uploads,
            'bhp_kebersihan.barcode_logo_path' => '',
        ]);

        $found = PublicUploadPath::officialLogoAbsolutePath();
        $this->assertSame(
            str_replace('\\', '/', $official),
            str_replace('\\', '/', $found)
        );
        $this->assertStringEndsWith('/img/logo.png', str_replace('\\', '/', $found));
        $this->assertStringNotContainsString('logo1.png', $found);
        $this->assertStringNotContainsString('fav1.png', $found);

        @unlink($official);
        @unlink($old);
        @rmdir($img);
        @rmdir($uploads);
        @rmdir($root);
    }

    public function test_official_logo_skips_explicit_deprecated_path(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhp-logo-skip-' . uniqid('', true);
        $uploads = $root . DIRECTORY_SEPARATOR . 'uploads';
        $img = $root . DIRECTORY_SEPARATOR . 'img';
        mkdir($uploads, 0777, true);
        mkdir($img, 0777, true);

        $official = $img . DIRECTORY_SEPARATOR . 'logo.png';
        $deprecated = $img . DIRECTORY_SEPARATOR . 'logo1.png';
        file_put_contents($official, $this->tinyPng());
        file_put_contents($deprecated, $this->tinyPng());

        config([
            'bhp_kebersihan.uploads_path' => $uploads,
            'bhp_kebersihan.barcode_logo_path' => $deprecated,
        ]);

        $found = PublicUploadPath::officialLogoAbsolutePath();
        $this->assertSame(
            str_replace('\\', '/', $official),
            str_replace('\\', '/', $found)
        );

        @unlink($official);
        @unlink($deprecated);
        @rmdir($img);
        @rmdir($uploads);
        @rmdir($root);
    }

    private function tinyPng(): string
    {
        $im = imagecreatetruecolor(16, 16);
        $green = imagecolorallocate($im, 0, 128, 80);
        imagefilledrectangle($im, 0, 0, 16, 16, $green);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }
}
