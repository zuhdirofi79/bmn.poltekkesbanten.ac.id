<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceHistoriAsetAuditJsonAndFk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('histori_aset', function (Blueprint $table) {
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('histori_aset', function (Blueprint $table) {
                $table->dropForeign(['aset_id']);
            });

            DB::statement('ALTER TABLE histori_aset MODIFY aset_id BIGINT UNSIGNED NULL');

            Schema::table('histori_aset', function (Blueprint $table) {
                $table->foreign('aset_id')
                    ->references('id')
                    ->on('aset_bmn')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('histori_aset', function (Blueprint $table) {
            $table->dropColumn(['data_lama', 'data_baru']);
        });

        // Mengembalikan FK cascade membutuhkan aset_id NOT NULL tanpa baris orphan;
        // lewati alter FK di rollback agar tidak merusak data produksi.
    }
}
