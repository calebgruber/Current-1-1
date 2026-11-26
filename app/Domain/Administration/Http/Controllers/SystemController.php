<?php

namespace App\Domain\Administration\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Administration\Services\SystemStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemController extends Controller
{
    public function index(SystemStatus $status): Response
    {
        return Inertia::render('Administration/System/Index', ['status' => $status->snapshot(app('migrator'))]);
    }

    public function clearCache(Request $request): RedirectResponse
    {
        $data = $request->validate(['cache' => ['required', Rule::in(['config', 'route', 'view'])]]);
        $cache = $data['cache'];

        AuditLog::record('system.cache_clear_requested', null, [], ['cache' => $cache], 'Framework cache clear requested');

        try {
            $result = Artisan::call($cache.':clear');
            if ($result !== 0) {
                Log::warning('Framework cache clear failed.', ['cache' => $cache, 'exit_code' => $result]);

                return back()->with('error', 'Cache clearing failed. Check the application logs.');
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Cache clearing failed. Check the application logs.');
        }

        return back()->with('success', ucfirst($cache).' cache cleared.');
    }
}
