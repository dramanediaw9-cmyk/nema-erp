<?php

namespace App\Modules\Core\Help\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HelpController extends Controller
{
    public function work(): View
    {
        return view('help.work');
    }
}
