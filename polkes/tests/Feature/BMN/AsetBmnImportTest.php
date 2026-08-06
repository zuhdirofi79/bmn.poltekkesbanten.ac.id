<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsetBmnImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function authenticatedUser(): User
    {
        return User::create([
            'name' => 'Import Tester',
            'email' => 'import-tester-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function csvFile(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('aset_import.csv', $body);
    }

    private function csvHeader(): string
    {
        return "kode_barang,nup,nama_barang,kategori_id,nilai_perolehan,tanggal_perolehan,kondisi,pengguna,satker_id,lokasi\n";
    }

    private function satkerId(): int
    {
        $id = DB::table('bmn_satker')->orderBy('id')->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }

    private function lokasiId(): int
    {
        $id = DB::table('lokasi_aset')->orderBy('id')->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeExistingAset(array $extra = []): AsetBmn
    {
        return AsetBmn::create(array_merge([
            'kode_barang' => 'EXIST-'.uniqid(),
            'nama_barang' => 'Aset Lama',
            'kategori_id' => 1,
            'nilai_perolehan' => 0,
            'tanggal_perolehan' => null,
            'kondisi' => 'baik',
            'lokasi_id' => $this->lokasiId(),
            'status' => 'aktif',
            'nup' => '999',
            'satker_id' => $this->satkerId(),
            'satker' => 'Satker Default',
        ], $extra));
    }

    public function test_import_inserts_two_rows_with_distinct_nup(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $csv = $this->csvHeader()
            . "IMP-A,10,Alpha,1,,,baik,,{$sid},\n"
            . "IMP-B,11,Beta,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, AsetBmn::query()->count());
        $response->assertSessionHas('import_summary');
        $this->assertSame(2, session('import_summary')['imported']);
    }

    /**
     * NUP is no longer unique – two rows with the same NUP must both be imported.
     */
    public function test_import_accepts_two_rows_with_same_nup(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $csv = $this->csvHeader()
            . "DUP-A,5,Pertama,1,,,baik,,{$sid},\n"
            . "DUP-B,5,Kedua,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, AsetBmn::query()->count());

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['skipped_count']);
    }

    /**
     * NUP is no longer unique – an import row whose NUP already exists in the database
     * must still be inserted (not skipped).
     */
    public function test_import_row_with_nup_matching_existing_asset_is_inserted(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $this->makeExistingAset([
            'kode_barang' => 'OLD-01',
            'nama_barang' => 'Meja Kantor',
            'nup' => '2',
        ]);

        $csv = $this->csvHeader()
            . "NEW-01,2,Meja Baru,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, AsetBmn::query()->count());

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(0, $summary['skipped_count']);
        $this->assertEmpty($summary['duplicate_nup_errors']);
    }

    /**
     * A soft-deleted asset no longer blocks imports with the same NUP.
     */
    public function test_soft_deleted_asset_does_not_block_import_with_same_nup(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $existing = $this->makeExistingAset([
            'kode_barang' => 'DEL-01',
            'nama_barang' => 'Disembunyikan',
            'nup' => '7',
        ]);
        $existing->delete();

        $csv = $this->csvHeader()
            . "NEW-07,7,Masuk Lagi,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        // soft-deleted + new = 2 total (withTrashed)
        $this->assertSame(2, AsetBmn::query()->withTrashed()->count());
        $this->assertSame(1, AsetBmn::query()->count());

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(0, $summary['skipped_count']);
    }

    /**
     * Rows with invalid required fields are still skipped; valid rows go through
     * regardless of NUP collisions.
     */
    public function test_valid_rows_import_when_another_row_has_invalid_required_field(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $csv = $this->csvHeader()
            . "OK-1,40,Satu,1,,,baik,,{$sid},\n"
            . ",50,Tanpa Kode,1,,,baik,,{$sid},\n"
            . "OK-2,60,Dua,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, AsetBmn::query()->count());

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(1, $summary['skipped_count']);
        $this->assertContains(3, $summary['skipped_rows']);
    }

    public function test_import_route_never_returns_server_error_for_handled_failure(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => UploadedFile::fake()->create('korup.xlsx', 12),
        ]);

        $this->assertLessThan(500, $response->getStatusCode());
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_import_accepts_indonesian_and_iso_date_formats(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $csv = $this->csvHeader()
            . "DT-DASH,1,Dash,1,,06-08-2026,baik,,{$sid},\n"
            . "DT-SLASH,2,Slash,1,,06/08/2026,baik,,{$sid},\n"
            . "DT-ISO,3,Iso,1,,2026-08-06,baik,,{$sid},\n"
            . "DT-ISOSL,4,IsoSlash,1,,2026/08/06,baik,,{$sid},\n"
            . "DT-SERIAL,5,Serial,1,,46240,baik,,{$sid},\n"
            . "DT-TRIM,6,Trim,1,, 06/08/2026 ,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(6, AsetBmn::query()->count());

        foreach (['DT-DASH', 'DT-SLASH', 'DT-ISO', 'DT-ISOSL', 'DT-SERIAL', 'DT-TRIM'] as $kode) {
            $aset = AsetBmn::query()->where('kode_barang', $kode)->first();
            $this->assertNotNull($aset, "Missing {$kode}");
            $this->assertSame('2026-08-06', optional($aset->tanggal_perolehan)->format('Y-m-d'));
        }
    }

    public function test_invalid_date_skips_row_and_keeps_valid_rows(): void
    {
        $user = $this->authenticatedUser();
        $sid = $this->satkerId();

        $csv = $this->csvHeader()
            . "OK-DATE,1,Valid,1,,06/08/2026,baik,,{$sid},\n"
            . "BAD-DATE,2,Invalid,1,,31/02/2026,baik,,{$sid},\n"
            . "OK-EMPTY,3,EmptyDate,1,,,baik,,{$sid},\n";

        $response = $this->actingAs($user)->post(route('bmn.aset.import'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, AsetBmn::query()->count());
        $this->assertNull(AsetBmn::query()->where('kode_barang', 'BAD-DATE')->first());

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(2, $summary['imported']);
        $this->assertSame(1, $summary['skipped_count']);
        $this->assertContains(3, $summary['skipped_rows']);

        $errors = session('import_errors');
        $this->assertIsArray($errors);
        $joined = implode("\n", $errors);
        $this->assertStringContainsString('tanggal_perolehan', $joined);
        $this->assertStringContainsString('31/02/2026', $joined);
    }
}
