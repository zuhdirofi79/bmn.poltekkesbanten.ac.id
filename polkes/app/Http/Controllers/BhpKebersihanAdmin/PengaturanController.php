<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihanAdmin;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use App\Support\BhpKebersihan\BhpAdminSession;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function index(): View
    {
        $userId = BhpAdminSession::userId(request());
        $admin = BhpKebersihanAdmin::with('user')->where('user_id', $userId)->first();

        return view('bhp_kebersihan_admin.pengaturan.index', [
            'admin' => $admin,
            'role'  => BhpAdminSession::role(request()),
        ]);
    }
}
