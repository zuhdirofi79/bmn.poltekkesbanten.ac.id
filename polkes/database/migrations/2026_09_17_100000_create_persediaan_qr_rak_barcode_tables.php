<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: master rak, mapping barang, histori mutasi, QR pintu, barcode path.
 * Tidak drop/truncate tabel existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('persediaan_rak')) {
            Schema::create('persediaan_rak', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('kode_rak', 50)->unique();
                $table->string('nama_rak', 150);
                $table->string('unit_kerja', 50);
                $table->string('lokasi_detail', 255)->nullable();
                $table->string('qr_token', 64)->unique();
                $table->string('qr_path', 255)->nullable();
                $table->string('status', 20)->default('aktif');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('unit_kerja');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('persediaan_rak_barang')) {
            Schema::create('persediaan_rak_barang', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rak_id');
                $table->unsignedBigInteger('barang_id')->unique();
                $table->timestamp('assigned_at')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();

                $table->index('rak_id');
            });
        }

        if (! Schema::hasTable('persediaan_rak_movements')) {
            Schema::create('persediaan_rak_movements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('barang_id');
                $table->unsignedBigInteger('from_rak_id')->nullable();
                $table->unsignedBigInteger('to_rak_id');
                $table->unsignedBigInteger('moved_by')->nullable();
                $table->timestamp('moved_at')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index('barang_id');
                $table->index('to_rak_id');
            });
        }

        if (! Schema::hasTable('persediaan_qr_akses')) {
            Schema::create('persediaan_qr_akses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('unit_kerja', 50);
                $table->string('nama', 150);
                $table->string('token', 64)->unique();
                $table->string('qr_path', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('unit_kerja');
            });
        }

        if (Schema::hasTable('persediaan_barang')) {
            Schema::table('persediaan_barang', function (Blueprint $table) {
                if (! Schema::hasColumn('persediaan_barang', 'barcode_path')) {
                    $table->string('barcode_path', 255)->nullable();
                }
                if (! Schema::hasColumn('persediaan_barang', 'barcode_payload')) {
                    $table->string('barcode_payload', 120)->nullable();
                }
            });
        }

        if (Schema::hasTable('persediaan_permintaan')) {
            Schema::table('persediaan_permintaan', function (Blueprint $table) {
                if (! Schema::hasColumn('persediaan_permintaan', 'source')) {
                    $table->string('source', 40)->nullable();
                }
                if (! Schema::hasColumn('persediaan_permintaan', 'rak_id')) {
                    $table->unsignedBigInteger('rak_id')->nullable();
                    $table->index('rak_id');
                }
                if (! Schema::hasColumn('persediaan_permintaan', 'idempotency_key')) {
                    $table->string('idempotency_key', 64)->nullable()->unique();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('persediaan_permintaan')) {
            Schema::table('persediaan_permintaan', function (Blueprint $table) {
                if (Schema::hasColumn('persediaan_permintaan', 'idempotency_key')) {
                    $table->dropUnique(['idempotency_key']);
                    $table->dropColumn('idempotency_key');
                }
                if (Schema::hasColumn('persediaan_permintaan', 'rak_id')) {
                    $table->dropIndex(['rak_id']);
                    $table->dropColumn('rak_id');
                }
                if (Schema::hasColumn('persediaan_permintaan', 'source')) {
                    $table->dropColumn('source');
                }
            });
        }

        if (Schema::hasTable('persediaan_barang')) {
            Schema::table('persediaan_barang', function (Blueprint $table) {
                if (Schema::hasColumn('persediaan_barang', 'barcode_payload')) {
                    $table->dropColumn('barcode_payload');
                }
                if (Schema::hasColumn('persediaan_barang', 'barcode_path')) {
                    $table->dropColumn('barcode_path');
                }
            });
        }

        Schema::dropIfExists('persediaan_qr_akses');
        Schema::dropIfExists('persediaan_rak_movements');
        Schema::dropIfExists('persediaan_rak_barang');
        Schema::dropIfExists('persediaan_rak');
    }
};
