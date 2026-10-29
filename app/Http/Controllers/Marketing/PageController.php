<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('marketing.home');
    }

    public function contact(): View
    {
        return view('marketing.contact');
    }

    public function privacy(): View
    {
        return view('marketing.privacy');
    }
}
