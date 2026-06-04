<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanAdmin;
use App\User;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function index(): View
    {
        $admins = PersediaanAdmin::query()->orderBy('role')->get();
        $userMap = User::query()
            ->whereIn('id', $admins->pluck('user_id'))
            ->get()
            ->keyBy('id');

        return view('persediaan_admin.pengaturan.index', [
            'admins'  => $admins,
            'userMap' => $userMap,
        ]);
    }
}
