<?php

namespace App\Domain\Administration\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Administration\Services\SystemStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class SystemController extends Controller
{
    public function index(SystemStatus $status)
    {
        return view('administration.system.index', ['status' => $status->snapshot(app('migrator'))]);
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

    public function migrate(Request $request): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'string', Rule::in(['MIGRATE'])]]);

        $lock = null;
        $acquired = false;

        try {
            AuditLog::record('system.migrations_requested', null, [], [], 'Pending database migrations requested');

            $lock = Cache::lock('system.migrations', 3600);
            $acquired = $lock->get();

            if (! $acquired) {
                AuditLog::record('system.migrations_failed', null, [], ['reason' => 'already_running'], 'Migration request blocked by an active migration lock');

                return back()->with('error', 'Another migration request is already running. Wait for it to finish.');
            }

            $result = Artisan::call('migrate', ['--force' => true]);
            if ($result !== 0) {
                throw new RuntimeException('Database migrations failed with exit code '.$result.'.');
            }

            AuditLog::record('system.migrations_succeeded', null, [], [], 'Pending database migrations completed');

            return back()->with('success', 'Pending database migrations completed.');
        } catch (Throwable $e) {
            report($e);

            try {
                AuditLog::record('system.migrations_failed', null, [], [], 'Database migration request failed; check application logs');
            } catch (Throwable $auditError) {
                report($auditError);
            }

            return back()->with('error', 'Database migrations failed. Check the application logs before retrying.');
        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                } catch (Throwable $lockError) {
                    report($lockError);
                }
            }
        }
    }
}
