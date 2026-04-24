<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtendedFieldsToAsetBmnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('aset_bmn', function (Blueprint $table) {
            if (! Schema::hasColumn('aset_bmn', 'pengguna_ruangan')) {
                $table->string('pengguna_ruangan')->nullable()->after('status');
            }
            if (! Schema::hasColumn('aset_bmn', 'asset_tag_hash')) {
                $table->string('asset_tag_hash', 191)->nullable()->after('pengguna_ruangan');
            }
            if (! Schema::hasColumn('aset_bmn', 'asset_tag_type')) {
                $table->string('asset_tag_type', 30)->nullable()->after('asset_tag_hash');
            }
            if (! Schema::hasColumn('aset_bmn', 'harga_satuan')) {
                $table->decimal('harga_satuan', 15, 2)->nullable()->after('nilai_perolehan');
            }
            if (! Schema::hasColumn('aset_bmn', 'foto_url')) {
                $table->string('foto_url', 2048)->nullable()->after('asset_tag_type');
            }
            if (! Schema::hasColumn('aset_bmn', 'foto_preview_url')) {
                $table->string('foto_preview_url', 2048)->nullable()->after('foto_url');
            }
            if (! Schema::hasColumn('aset_bmn', 'foto_provider')) {
                $table->string('foto_provider', 30)->nullable()->after('foto_preview_url');
            }
            if (! Schema::hasColumn('aset_bmn', 'satker')) {
                $table->string('satker', 191)->nullable()->after('foto_provider');
            }
            if (! Schema::hasColumn('aset_bmn', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index()->after('satker');
            }
            if (! Schema::hasColumn('aset_bmn', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
            }
            if (! Schema::hasColumn('aset_bmn', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
            }
            if (! Schema::hasColumn('aset_bmn', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('deleted_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aset_bmn', function (Blueprint $table) {
            foreach ([
                'pengguna_ruangan',
                'asset_tag_hash',
                'asset_tag_type',
                'harga_satuan',
                'foto_url',
                'foto_preview_url',
                'foto_provider',
                'satker',
                'created_by',
                'updated_by',
                'deleted_by',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('aset_bmn', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
