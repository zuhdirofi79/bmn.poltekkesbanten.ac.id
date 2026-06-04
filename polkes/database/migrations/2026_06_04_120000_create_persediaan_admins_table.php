<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping akses Admin Persediaan (terpisah dari users.role / login BMN utama).
 */
class CreatePersediaanAdminsTable extends Migration
{
    public function up()
    {
        Schema::create('persediaan_admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('role', 100);
            $table->string('satker', 100)->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('persediaan_admins');
    }
}
