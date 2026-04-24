<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status inventaris BHP bahan: aktif | dimusnahkan (terpisah dari soft-delete `delete`).
 * Backfill: semua baris lama = aktif; baris yang sudah punya tindak_lanjut dimusnahkan diselaraskan.
 */
class AddStatusTanggalPemusnahanToBarangbhpBahan extends Migration
{
    public function up()
    {
        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->string('status', 20)->default('aktif')->after('delete');
            $table->date('tanggal_pemusnahan')->nullable()->after('status');
            $table->index(['jurusan', 'status']);
        });

        DB::table('barangbhp_bahan')->update(['status' => 'aktif']);

        $today = now()->format('Y-m-d');
        DB::table('barangbhp_bahan')
            ->where('tindak_lanjut', 'dimusnahkan')
            ->update([
                'status' => 'dimusnahkan',
                'stok' => 0,
                'tanggal_pemusnahan' => $today,
            ]);
    }

    public function down()
    {
        Schema::table('barangbhp_bahan', function (Blueprint $table) {
            $table->dropIndex(['jurusan', 'status']);
            $table->dropColumn(['status', 'tanggal_pemusnahan']);
        });
    }
}
