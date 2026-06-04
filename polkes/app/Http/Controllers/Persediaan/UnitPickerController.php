<?php

declare(strict_types=1);

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UnitPickerController extends Controller
{
    public function index(): View
    {
        return view('persediaan.unit-pilih', [
            'units'   => UnitKerja::all(),
            'pemohon' => Auth::guard('persediaan')->user(),
        ]);
    }
}
