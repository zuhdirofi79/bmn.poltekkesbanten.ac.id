<?php

declare(strict_types=1);

namespace Tests\Unit\BhpKebersihan;

use App\Services\BhpKebersihan\BarcodeLabelRenderer;
use Tests\TestCase;

class BarcodeLabelRendererTest extends TestCase
{
    public function test_render_menghasilkan_png_horizontal_dengan_nama_dan_kode(): void
    {
        $renderer = new BarcodeLabelRenderer(fn () => $this->squarePng(240));
        $png = $renderer->render(
            'https://bmn.poltekkesbanten.ac.id/public/bhp-kebersihan/barang/wadi2oldeddpf0imhi6vaptbbzbg5ulv',
            'Wipol 4.5 L',
            'KBR-01-004'
        );

        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info);
        $this->assertSame(BarcodeLabelRenderer::CANVAS_W, $info[0]);
        $this->assertSame(BarcodeLabelRenderer::CANVAS_H, $info[1]);
        $this->assertGreaterThan($info[1], $info[0]);
        $this->assertSame('image/png', $info['mime']);
    }

    private function squarePng(int $size): string
    {
        $im = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $size, $size, $white);
        imagefilledrectangle($im, 20, 20, 80, 80, $black);
        imagefilledrectangle($im, $size - 80, 20, $size - 20, 80, $black);
        imagefilledrectangle($im, 20, $size - 80, 80, $size - 20, $black);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }
}
