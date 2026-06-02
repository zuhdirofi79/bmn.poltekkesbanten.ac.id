<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PublicLandingController extends Controller
{
    public function index(): View
    {
        return view('public.landing');
    }
}
