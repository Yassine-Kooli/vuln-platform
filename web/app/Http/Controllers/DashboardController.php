<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\Target;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'targets' => Target::with('latestScan')->orderBy('name')->get(),
            'totalScans' => Scan::count(),
            'totalFindings' => Finding::count(),
            'runningScans' => Scan::whereIn('status', ['queued', 'running'])->count(),
        ]);
    }
}
