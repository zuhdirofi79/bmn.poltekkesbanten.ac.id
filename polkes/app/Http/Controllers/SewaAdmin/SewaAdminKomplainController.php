<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SewaAdminKomplainController extends Controller
{
    public function index(): View
    {
        return view('sewa_admin.komplain.index');
    }
}
