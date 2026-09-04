<?php

declare(strict_types=1);

namespace Tests\Unit\BhpKebersihan;

use App\Services\BhpKebersihan\FotoImport\KodeBarangAllocator;
use App\Services\BhpKebersihan\FotoImport\TextTableParser;
use App\Services\BhpKebersihan\FotoImport\VolumeParser;
use Tests\TestCase;

class FotoImportParserTest extends TestCase
{
    public function test_volume_memisahkan_angka_dan_satuan(): void
    {
        $a = VolumeParser::split('150 Jerigen');
        $this->assertSame(150, $a['jumlah']);
        $this->assertSame('Jerigen', $a['satuan']);
        $this->assertFalse($a['needs_review']);

        $b = VolumeParser::split('20 Pack (1 Karton isi 72)');
        $this->assertSame(20, $b['jumlah']);
        $this->assertSame('Pack', $b['satuan']);

        $c = VolumeParser::split(40, 'Jerigen');
        $this->assertSame(40, $c['jumlah']);
        $this->assertSame('Jerigen', $c['satuan']);
    }

    public function test_normalize_key_abaikan_kapital_dan_spasi(): void
    {
        $a = VolumeParser::normalizeKey(' Floor Cleaner ', 'SOS', 'Jerigen');
        $b = VolumeParser::normalizeKey('floor cleaner', 'sos', 'jerigen');
        $this->assertSame($a, $b);
    }

    public function test_kode_lanjut_dari_kode_terakhir(): void
    {
        $alloc = new KodeBarangAllocator();
        $codes = $alloc->nextCodes(3, ['KBR-03-007', 'KBR-01-001']);
        $this->assertSame(['KBR-03-008', 'KBR-03-009', 'KBR-03-010'], $codes);
    }

    public function test_kode_melewati_yang_sudah_dipakai(): void
    {
        $alloc = new KodeBarangAllocator();
        $codes = $alloc->nextCodes(2, ['KBR-01-001', 'KBR-01-003']);
        $this->assertSame(['KBR-01-004', 'KBR-01-005'], $codes);
    }

    public function test_text_parser_mengambil_baris_tabel_bukan_header(): void
    {
        $text = "No | Nama Barang | Merk | Volume\n1 | Floor cleaner | SOS | 150 Jerigen\n2 | Sabun cuci piring 5 Liter | Mama Lemon | 40 Jerigen\nDone\n";
        $rows = (new TextTableParser())->parse($text);
        $this->assertCount(2, $rows);
        $this->assertSame('Floor cleaner', $rows[0]['nama_barang']);
        $this->assertSame('SOS', $rows[0]['merk']);
        $this->assertSame(150, $rows[0]['jumlah']);
        $this->assertSame('Jerigen', $rows[0]['satuan']);
    }
}
