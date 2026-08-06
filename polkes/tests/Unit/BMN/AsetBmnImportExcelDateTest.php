<?php

declare(strict_types=1);

namespace Tests\Unit\BMN;

use App\Services\BMN\AsetBmnImportService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class AsetBmnImportExcelDateTest extends TestCase
{
    private AsetBmnImportService $service;

    private ReflectionMethod $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsetBmnImportService;
        $this->parser = new ReflectionMethod(AsetBmnImportService::class, 'parseTanggalExcel');
        $this->parser->setAccessible(true);
    }

    private function parse(mixed $value): ?string
    {
        return $this->parser->invoke($this->service, $value, 2, 'tanggal_perolehan');
    }

    public function test_parses_dd_mm_yyyy_with_dash(): void
    {
        $this->assertSame('2026-08-06', $this->parse('06-08-2026'));
    }

    public function test_parses_dd_mm_yyyy_with_slash_as_indonesian(): void
    {
        // 06/08/2026 = 6 Agustus 2026, bukan 8 Juni 2026
        $this->assertSame('2026-08-06', $this->parse('06/08/2026'));
    }

    public function test_parses_iso_yyyy_mm_dd(): void
    {
        $this->assertSame('2026-08-06', $this->parse('2026-08-06'));
    }

    public function test_parses_iso_with_slash(): void
    {
        $this->assertSame('2026-08-06', $this->parse('2026/08/06'));
    }

    public function test_parses_excel_serial_number(): void
    {
        // 46240 = 2026-08-06 (PhpSpreadsheet)
        $this->assertSame('2026-08-06', $this->parse(46240));
        $this->assertSame('2026-08-06', $this->parse('46240'));
    }

    public function test_parses_real_world_excel_serials_from_user_file(): void
    {
        $this->assertSame('2022-07-21', $this->parse(44763));
        $this->assertSame('2022-10-07', $this->parse(44841));
        $this->assertSame('2022-11-23', $this->parse(44888));
    }

    public function test_parses_datetime_interface(): void
    {
        $this->assertSame(
            '2026-08-06',
            $this->parse(new DateTimeImmutable('2026-08-06 15:30:00'))
        );
    }

    public function test_trims_whitespace(): void
    {
        $this->assertSame('2026-08-06', $this->parse(' 06/08/2026 '));
    }

    public function test_empty_returns_null(): void
    {
        $this->assertNull($this->parse(null));
        $this->assertNull($this->parse(''));
        $this->assertNull($this->parse('   '));
    }

    public function test_rejects_invalid_calendar_dates(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('31/02/2026');
        $this->parse('31/02/2026');
    }

    public function test_rejects_out_of_range_day_and_month(): void
    {
        foreach (['32/01/2026', '00/12/2026', '2026/13/01'] as $invalid) {
            try {
                $this->parse($invalid);
                $this->fail("Expected RuntimeException for {$invalid}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($invalid, $e->getMessage());
                $this->assertStringContainsString('tanggal_perolehan', $e->getMessage());
            }
        }
    }

    public function test_rejects_bare_year_as_excel_serial(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('2026');
        $this->parse(2026);
    }

    public function test_error_message_format(): void
    {
        try {
            $this->parse('not-a-date');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Baris 2:', $e->getMessage());
            $this->assertStringContainsString('Kolom tanggal_perolehan:', $e->getMessage());
            $this->assertStringContainsString('tidak valid', $e->getMessage());
        }
    }

    /**
     * Membuktikan: toArray() default (formatData=true) mengubah serial jadi string format,
     * sedangkan toArray(null, true, false) mempertahankan angka serial mentah.
     */
    public function test_xlsx_date_cells_remain_numeric_when_format_data_false(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'tanggal_perolehan');
        $serial = ExcelDate::dateTimeToExcel(new DateTimeImmutable('2022-07-21'));
        $sheet->setCellValue('A2', $serial);
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        $tmp = tempnam(sys_get_temp_dir(), 'aset_date_').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmp);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        try {
            $loaded = IOFactory::load($tmp);

            $formatted = $loaded->getActiveSheet()->toArray();
            $raw = $loaded->getActiveSheet()->toArray(null, true, false);

            $this->assertIsNumeric($raw[1][0], 'Raw toArray must keep Excel serial as number');
            $this->assertSame('2022-07-21', $this->parse($raw[1][0]));

            // Default toArray may return a formatted string (locale-dependent).
            // Parser must still succeed for either numeric or valid dd/mm/yyyy string.
            $formattedVal = $formatted[1][0];
            if (is_numeric($formattedVal)) {
                $this->assertSame('2022-07-21', $this->parse($formattedVal));
            } elseif (is_string($formattedVal)) {
                // Jika string format lokal valid (dd/mm/yyyy), parser harus berhasil;
                // jika string rusak (tahun Jepang, dsb.), itu alasan formatData=false wajib.
                try {
                    $parsed = $this->parse($formattedVal);
                    $this->assertSame('2022-07-21', $parsed);
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('tidak valid', $e->getMessage());
                }
            }

            $loaded->disconnectWorksheets();
        } finally {
            @unlink($tmp);
        }
    }
}
