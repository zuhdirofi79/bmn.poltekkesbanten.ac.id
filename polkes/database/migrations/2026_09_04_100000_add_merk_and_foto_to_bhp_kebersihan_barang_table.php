<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMerkAndFotoToBhpKebersihanBarangTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('bhp_kebersihan_barang')) {
            return;
        }

        if (! Schema::hasColumn('bhp_kebersihan_barang', 'merk')) {
            Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
                $table->string('merk', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('bhp_kebersihan_barang', 'foto_path')) {
            Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
                $table->string('foto_path', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('bhp_kebersihan_barang', 'foto_original_name')) {
            Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
                $table->string('foto_original_name', 255)->nullable();
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('bhp_kebersihan_barang')) {
            return;
        }

        Schema::table('bhp_kebersihan_barang', function (Blueprint $table) {
            $drops = [];
            foreach (['merk', 'foto_path', 'foto_original_name'] as $col) {
                if (Schema::hasColumn('bhp_kebersihan_barang', $col)) {
                    $drops[] = $col;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
}
