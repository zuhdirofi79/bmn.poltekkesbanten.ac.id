<?php

declare(strict_types=1);

namespace Tests\Feature\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Support\BhpKebersihan\BhpAdminSession;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BhpFotoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Http::fake([
            'api.qrserver.com/*' => Http::response('PNGFAKEIMAGECONTENTXXXXXXXXXXXXXXXX', 200),
        ]);
    }

    public function test_ocr_tanpa_mesin_mengembalikan_pesan_jelas(): void
    {
        $this->loginBhpAdmin();
        config(['bhp_kebersihan.ocr.provider' => 'gemini', 'bhp_kebersihan.ocr.api_key' => '']);

        $this->getJson(route('bhp.admin.master-barang.import-foto.status'))
            ->assertOk()
            ->assertJsonPath('available', false);

        $file = UploadedFile::fake()->image('tabel.jpg', 200, 200);
        $this->postJson(route('bhp.admin.master-barang.import-foto.ocr'), [
            'fotos' => [$file],
        ])->assertStatus(503)
            ->assertJsonPath('code', 'ocr_unavailable');
    }

    public function test_commit_menyimpan_batch_dan_melewati_duplikat(): void
    {
        $this->loginBhpAdmin();
        $this->makeBarang('Floor Cleaner', 'SOS', 'Jerigen');

        $payload = [
            'rows' => [
                [
                    'nama_barang' => 'Floor Cleaner',
                    'merk' => 'SOS',
                    'satuan' => 'Jerigen',
                    'jumlah' => 150,
                    'kode_barang' => 'KBR-01-099',
                    'status' => 'ok',
                ],
                [
                    'nama_barang' => 'Sabun Cuci Piring 5 Liter',
                    'merk' => 'Mama Lemon',
                    'satuan' => 'Jerigen',
                    'jumlah' => 40,
                    'kode_barang' => '',
                    'status' => 'ok',
                ],
            ],
        ];

        $this->postJson(route('bhp.admin.master-barang.import-foto.commit'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('skipped', 1);

        $this->assertSame(1, BhpKebersihanBarang::where('nama_barang', 'Sabun Cuci Piring 5 Liter')->count());
        $this->assertSame(1, BhpKebersihanBarang::where('nama_barang', 'Floor Cleaner')->count());
        $this->assertNotNull(
            BhpKebersihanBarang::where('nama_barang', 'Sabun Cuci Piring 5 Liter')->value('barcode_token')
        );
    }

    public function test_commit_dibatalkan_jika_masih_perlu_diperiksa(): void
    {
        $this->loginBhpAdmin();

        $this->postJson(route('bhp.admin.master-barang.import-foto.commit'), [
            'rows' => [
                [
                    'nama_barang' => 'Hit Semprot',
                    'merk' => '',
                    'satuan' => 'Botol',
                    'jumlah' => 10,
                    'kode_barang' => 'KBR-01-001',
                    'status' => 'perlu_diperiksa',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, BhpKebersihanBarang::count());
    }

    public function test_import_foto_tanpa_login_ditolak(): void
    {
        $this->postJson(route('bhp.admin.master-barang.import-foto.commit'), [
            'rows' => [['nama_barang' => 'X', 'satuan' => 'Buah', 'jumlah' => 1, 'status' => 'ok']],
        ])->assertRedirect(route('bhp.admin.login.show'));
    }

    private function makeBarang(string $nama, string $merk, string $satuan): BhpKebersihanBarang
    {
        return BhpKebersihanBarang::create([
            'kode_barang' => 'KBR-01-001',
            'nama_barang' => $nama,
            'merk' => $merk,
            'satuan' => $satuan,
            'stok_awal' => 10,
            'barcode_token' => Str::lower(Str::random(32)),
            'status' => 'aktif',
        ]);
    }

    private function loginBhpAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin BHP',
            'email' => 'adminbhp-import-' . uniqid('', true) . '@example.test',
            'password' => Hash::make('secret'),
        ]);

        BhpKebersihanAdmin::create([
            'user_id' => $user->id,
            'role' => BhpKebersihanAdmin::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);

        $this->withSession([
            BhpAdminSession::KEY_USER_ID => (int) $user->id,
            BhpAdminSession::KEY_ROLE => BhpKebersihanAdmin::ROLE_SUPERADMIN,
        ]);

        return $user;
    }
}
