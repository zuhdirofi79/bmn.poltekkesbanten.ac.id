<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPersediaanPermintaanApproverColumns extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('persediaan_permintaan')) {
            return;
        }

        Schema::table('persediaan_permintaan', function (Blueprint $table) {
            if (! Schema::hasColumn('persediaan_permintaan', 'approver_user_id')) {
                $table->unsignedBigInteger('approver_user_id')->nullable()->after('status');
                $table->index('approver_user_id');
            }
            if (! Schema::hasColumn('persediaan_permintaan', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('persediaan_permintaan', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('persediaan_permintaan', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('catatan_admin');
            }
        });

        $approvers = config('persediaan.approvers', []);
        foreach ($approvers as $unit => $row) {
            DB::table('persediaan_permintaan')
                ->where('unit_kerja', $unit)
                ->whereNull('approver_user_id')
                ->update(['approver_user_id' => (int) $row['user_id']]);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('persediaan_permintaan')) {
            return;
        }

        Schema::table('persediaan_permintaan', function (Blueprint $table) {
            if (Schema::hasColumn('persediaan_permintaan', 'approver_user_id')) {
                $table->dropIndex(['approver_user_id']);
                $table->dropColumn('approver_user_id');
            }
            if (Schema::hasColumn('persediaan_permintaan', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('persediaan_permintaan', 'rejected_by')) {
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('persediaan_permintaan', 'approval_note')) {
                $table->dropColumn('approval_note');
            }
        });
    }
}
