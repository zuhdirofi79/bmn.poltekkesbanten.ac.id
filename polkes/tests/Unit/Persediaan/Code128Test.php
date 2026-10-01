<?php

declare(strict_types=1);

namespace Tests\Unit\Persediaan;

use App\Services\Persediaan\Code128Renderer;
use App\Services\Persediaan\PersediaanCodeService;
use App\Support\Persediaan\UnitKerja;
use Tests\TestCase;

class Code128Test extends TestCase
{
    public function test_payload_memakai_kode_unit_karena_kode_barang_tidak_global_unique(): void
    {
        $service = $this->app->make(PersediaanCodeService::class);

        $this->assertSame('DIR|ATK001', $service->makePayload(UnitKerja::DIREKTORAT, 'ATK001'));
        $this->assertSame('KEP|ATK001', $service->makePayload(UnitKerja::KEPERAWATAN, 'ATK001'));
    }

    public function test_parse_payload_memisahkan_unit_dan_kode(): void
    {
        $service = $this->app->make(PersediaanCodeService::class);
        $parsed = $service->parsePayload('DIR|ATK001');

        $this->assertSame('DIR', $parsed['unit_kode']);
        $this->assertSame('ATK001', $parsed['kode_barang']);
        $this->assertSame(UnitKerja::DIREKTORAT, $service->unitFromKode('DIR'));
    }

    public function test_encode_bits_stabil_untuk_payload_yang_sama(): void
    {
        $renderer = new Code128Renderer();
        $a = $renderer->encodeBits('DIR|ATK001');
        $b = $renderer->encodeBits('DIR|ATK001');

        $this->assertSame($a, $b);
        $this->assertNotSame('', $a);
        $this->assertStringStartsWith('11010010000', $a);
    }

    public function test_render_menghasilkan_png(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia');
        }

        $png = (new Code128Renderer())->render('DIR|ATK001', 'Pulpen', 'ATK001');
        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertGreaterThan(200, strlen($png));
    }
}
