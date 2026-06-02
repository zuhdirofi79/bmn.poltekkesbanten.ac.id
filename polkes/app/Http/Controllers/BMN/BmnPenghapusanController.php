<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BmnPenghapusanController extends Controller
{
    public function index(): View
    {
        return view('bmn.penghapusan.index');
    }
}
