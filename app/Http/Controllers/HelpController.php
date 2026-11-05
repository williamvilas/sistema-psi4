<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SupportHoursService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class HelpController extends Controller
{
    public function index(SupportHoursService $supportHoursService): View
    {
        return view('help.index', [
            'supportStatus' => $supportHoursService->status(),
            'supportSchedule' => $supportHoursService->schedule(),
            'supportCode' => Auth::user()->code,
        ]);
    }
}
