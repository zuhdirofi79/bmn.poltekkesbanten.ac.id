<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBhpKebersihanAdminsTable extends Migration
{
    public function up()
    {
        Schema::create('bhp_kebersihan_admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->enum('role', [
                'superadmin_bhp',
                'admin_bhp_master',
                'admin_bhp_verifikator',
                'admin_bhp_pengambilan',
            ]);
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bhp_kebersihan_admins');
    }
}
