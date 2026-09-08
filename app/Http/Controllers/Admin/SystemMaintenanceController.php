<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemMaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/SystemMaintenance/Index', [
            'result' => $request->session()->get('maintenance_result'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }

    public function run(Request $request, SystemMaintenanceService $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['migrate', 'clear-cache', 'config-cache', 'all'])],
            'confirmed' => ['required', 'accepted'],
        ]);

        $result = $maintenance->run($data['action']);
        Log::notice('Admin ran system maintenance', [
            'admin_id' => $request->user()->id,
            'action' => $data['action'],
            'successful' => $result['successful'],
            'exit_codes' => collect($result['commands'])->pluck('exit_code')->all(),
        ]);

        return back()
            ->with('maintenance_result', $result)
            ->with($result['successful'] ? 'success' : 'error', $result['successful']
                ? 'عملیات نگهداری با موفقیت انجام شد.'
                : 'اجرای عملیات کامل نشد؛ خروجی دستور را بررسی کنید.');
    }
}
