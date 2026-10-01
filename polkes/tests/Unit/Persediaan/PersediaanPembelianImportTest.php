<?php

declare(strict_types=1);

namespace Tests\Unit\Persediaan;

use App\Services\Persediaan\PembelianExcelReader;
use App\Services\Persediaan\PembelianImportException;
use App\Services\Persediaan\PembelianImportNormalizer;
use App\Support\Persediaan\PersediaanDateParser;
use App\Support\Persediaan\UnitKerja;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

class PersediaanPembelianImportTest extends TestCase
{
    public function test_tanggal_indonesia_tidak_dibaca_sebagai_bulan_di_depan(): void
    {
        $this->assertSame('2026-08-06', PersediaanDateParser::toDateString('06/08/2026'));
        $this->assertSame('2026-08-06', PersediaanDateParser::toDateString('06-08-2026'));
        $this->assertSame('2026-08-06', PersediaanDateParser::toDateString('2026-08-06'));
        $this->assertSame('2026-10-01', PersediaanDateParser::toDateString('01/10/2026'));
        $this->assertSame('2025-10-01', PersediaanDateParser::toDateString('01/10/2025'));
        $this->assertSame('2026-09-30', PersediaanDateParser::toDateString('30/09/2026'));
        $this->assertNull(PersediaanDateParser::toDateString('31/02/2026'));
        $this->assertNull(PersediaanDateParser::toDateString(''));
    }

    public function test_tanggal_excel_serial_menjadi_hari_yang_sama(): void
    {
        $serial = ExcelDate::PHPToExcel(new \DateTime('2026-08-06 00:00:00'));

        $this->assertSame('2026-08-06', PersediaanDateParser::toDateString($serial));
        $this->assertSame(
            '2026-08-06',
            PersediaanDateParser::toDateString(new \DateTime('2026-08-06 00:00:00', new \DateTimeZone('Asia/Jakarta'))),
        );
        $this->assertSame('06/08/2026', PersediaanDateParser::formatIndonesia('2026-08-06'));
    }

    public function test_satu_faktur_banyak_baris_menjadi_satu_transaksi(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = $this->row($i + 1, [
                'kode_barang' => 'MAN-' . $i,
                'nama_barang' => 'Barang ' . $i,
                'jumlah'      => $i,
            ]);
        }

        $preview = $this->normalizer()->build($rows, [], [], null);

        $this->assertTrue($preview['can_import']);
        $this->assertCount(1, $preview['groups']);
        $this->assertCount(10, $preview['groups'][0]['items']);
        $this->assertSame('INV-001', $preview['groups'][0]['header']['nomor_faktur']);
        $this->assertSame('2026-08-06', $preview['groups'][0]['header']['tanggal']);
        $this->assertNull($preview['groups'][0]['items'][0]['barang_id']);
    }

    public function test_tiga_faktur_menjadi_tiga_header(): void
    {
        $rows = [
            $this->row(2, ['no_faktur' => 'INV-001', 'kode_barang' => 'A']),
            $this->row(3, ['no_faktur' => 'INV-001', 'kode_barang' => 'B']),
            $this->row(4, ['no_faktur' => 'INV-002', 'kode_barang' => 'C']),
            $this->row(5, ['no_faktur' => 'INV-003', 'kode_barang' => 'D']),
        ];

        $preview = $this->normalizer()->build($rows, [], [], null);

        $this->assertTrue($preview['can_import']);
        $this->assertCount(3, $preview['groups']);
        $this->assertCount(2, $preview['groups'][0]['items']);
        $this->assertCount(1, $preview['groups'][1]['items']);
        $this->assertCount(1, $preview['groups'][2]['items']);
    }

    public function test_kode_master_terhubung_dan_kode_asing_tetap_manual(): void
    {
        $masters = [
            UnitKerja::KEPERAWATAN . '|ATK001' => [
                'id' => 9, 'kode' => 'ATK001', 'nama' => 'Pulpen', 'satuan' => 'Buah', 'harga' => 2500,
            ],
        ];
        $rows = [
            $this->row(2, ['kode_barang' => 'ATK001', 'nama_barang' => 'Pulpen', 'jumlah' => 20]),
            $this->row(3, ['kode_barang' => 'MAN-001', 'nama_barang' => 'Kabel HDMI', 'jumlah' => 5]),
            $this->row(4, ['kode_barang' => '', 'nama_barang' => 'Tanpa Kode', 'jumlah' => 2]),
        ];

        $preview = $this->normalizer()->build($rows, $masters, [], null);

        $this->assertTrue($preview['can_import']);
        $this->assertSame('MASTER', $preview['rows'][0]['status']);
        $this->assertSame(9, $preview['rows'][0]['barang_id']);
        $this->assertSame(50000.0, $preview['rows'][0]['subtotal']);
        $this->assertSame('MANUAL', $preview['rows'][1]['status']);
        $this->assertNull($preview['rows'][1]['barang_id']);
        $this->assertSame('MANUAL', $preview['rows'][2]['status']);
        $this->assertNull($preview['rows'][2]['barang_id']);
    }

    public function test_faktur_yang_sudah_ada_ditolak(): void
    {
        $preview = $this->normalizer()->build(
            [$this->row(2), $this->row(3, ['kode_barang' => 'ATK002', 'nama_barang' => 'Kertas'])],
            [],
            ['INV-001'],
            null,
        );

        $this->assertFalse($preview['can_import']);
        $this->assertSame([], $preview['groups']);
        $this->assertSame('DUPLIKAT', $preview['rows'][0]['status']);
        $this->assertStringContainsString('Transaksi dengan No. Faktur INV-001 sudah terdaftar.', implode(' ', $preview['messages']));
    }

    public function test_tanggal_dan_jumlah_tidak_valid_memblokir_import(): void
    {
        $preview = $this->normalizer()->build([
            $this->row(7, ['tanggal' => '31/02/2026']),
            $this->row(12, ['no_faktur' => 'INV-002', 'jumlah' => 0]),
            $this->row(20, ['no_faktur' => 'INV-003', 'unit_kerja' => 'Unit Fiktif']),
        ], [], [], null);

        $this->assertFalse($preview['can_import']);
        $text = implode("\n", $preview['messages']);
        $this->assertStringContainsString('Baris 7:', $text);
        $this->assertStringContainsString('tidak valid', $text);
        $this->assertStringContainsString('DD/MM/YYYY', $text);
        $this->assertStringContainsString('Baris 12:', $text);
        $this->assertStringContainsString('jumlah harus lebih besar dari 0', $text);
        $this->assertStringContainsString('Baris 20:', $text);
        $this->assertStringContainsString('unit kerja tidak valid', $text);
    }

    public function test_unit_berbeda_dalam_satu_faktur_ditolak(): void
    {
        $preview = $this->normalizer()->build([
            $this->row(2, ['unit_kerja' => UnitKerja::KEPERAWATAN]),
            $this->row(3, ['unit_kerja' => UnitKerja::DIREKTORAT, 'kode_barang' => 'X', 'nama_barang' => 'Map']),
        ], [], [], null);

        $this->assertFalse($preview['can_import']);
        $this->assertSame('ERROR', $preview['rows'][1]['status']);
    }

    public function test_reader_melewati_header_dan_baris_kosong(): void
    {
        $rows = (new PembelianExcelReader())->parseSheet([
            ['no_faktur', 'tanggal', 'penyedia', 'unit_kerja', 'kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah'],
            ['', '', '', '', '', '', '', '', ''],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK001', 'Pulpen', 'Buah', 2500, 20],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['line']);
        $this->assertSame('INV-001', $rows[0]['no_faktur']);
        $this->assertSame('06/08/2026', $rows[0]['tanggal']);
    }

    public function test_reader_menolak_sheet_tanpa_header(): void
    {
        $this->assertNull((new PembelianExcelReader())->parseSheet([
            ['Petunjuk pengisian'],
            ['Satu baris = satu item'],
        ]));
    }

    public function test_reader_menolak_sheet_kosong(): void
    {
        $this->expectException(PembelianImportException::class);
        $this->expectExceptionMessage('Tidak ada baris data');

        (new PembelianExcelReader())->parseSheet([
            ['no_faktur', 'tanggal', 'penyedia', 'unit_kerja', 'kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah'],
        ]);
    }

    private function normalizer(): PembelianImportNormalizer
    {
        return new PembelianImportNormalizer();
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function row(int $line, array $overrides = []): array
    {
        return array_merge([
            'line'          => $line,
            'no_faktur'     => 'INV-001',
            'tanggal'       => '06/08/2026',
            'penyedia'      => 'PT ABC',
            'unit_kerja'    => UnitKerja::KEPERAWATAN,
            'kode_barang'   => 'ATK009',
            'nama_barang'   => 'Pulpen',
            'satuan'        => 'Buah',
            'harga_satuan'  => 2500,
            'jumlah'        => 20,
        ], $overrides);
    }
}
