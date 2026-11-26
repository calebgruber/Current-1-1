<?php

namespace App\Domain\Setup\Http\Controllers;

use App\Domain\Setup\Services\EnvironmentWriter;
use App\Domain\Setup\Services\Installer;
use App\Domain\Setup\Services\RequirementsChecker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/** Web installer: requirements → database → application → install → administrator → finish. */
class SetupController extends Controller
{
    public const STEPS = [
        'requirements' => 'Requirements',
        'database' => 'Database',
        'application' => 'Application',
        'install' => 'Install',
        'admin' => 'Administrator',
        'finish' => 'Finish',
    ];

    public function __construct(
        private readonly Installer $installer,
        private readonly RequirementsChecker $requirements,
        private readonly EnvironmentWriter $env,
    ) {}

    public function show(Request $request): View
    {
        $completed = $this->completed($request);
        $requested = (string) $request->query('step', '');
        $current = $this->firstIncomplete($completed);
        if ($requested !== '' && array_key_exists($requested, self::STEPS) && $this->reachable($requested, $completed)) {
            $current = $requested;
        }

        return view('setup.wizard', [
            'steps' => collect(self::STEPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'current' => $current,
            'completed' => $completed,
            'requirements' => $this->requirements->checks(),
            'requirementsPass' => $this->requirements->passes(),
            'database' => [
                'driver' => config('database.default') === 'mysql' || config('database.default') === 'mariadb' ? 'mysql' : ($request->session()->get('setup.db_driver', 'mysql')),
                'host' => (string) ($this->env->get('DB_HOST') ?: '127.0.0.1'),
                'port' => (string) ($this->env->get('DB_PORT') ?: '3306'),
                'database' => (string) ($this->env->get('DB_DATABASE') ?: ''),
                'username' => (string) ($this->env->get('DB_USERNAME') ?: ''),
            ],
            'application' => [
                'name' => (string) config('app.name', 'StageTrack Enterprise'),
                'url' => $request->getSchemeAndHttpHost().$request->getBasePath(),
                'organization_name' => (string) $request->session()->get('setup.settings.organization_name', ''),
                'timezone' => (string) $request->session()->get('setup.settings.timezone', 'UTC'),
                'currency' => (string) $request->session()->get('setup.settings.currency', 'USD'),
            ],
            'installLog' => $request->session()->get('setup.install_log'),
            'hasAdministrator' => in_array('install', $completed, true) && $this->installer->hasAdministrator(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'JPY', 'ZAR', 'AED', 'SGD', 'HKD', 'INR', 'BRL', 'MXN', 'PLN', 'CZK'],
            'version' => config('stagetrack.version'),
        ]);
    }

    public function requirements(Request $request): RedirectResponse
    {
        if (! $this->requirements->passes()) {
            return back()->with('error', 'Resolve the failing requirements before continuing.');
        }
        $this->complete($request, 'requirements');

        return $this->next('database');
    }

    public function testDatabase(Request $request): RedirectResponse
    {
        $db = $this->validateDatabase($request);
        $error = $this->installer->testConnection($db);

        return back()->withInput($request->except('password'))
            ->with($error ? 'error' : 'success', $error ? 'Connection failed: '.$error : 'Connection successful.');
    }

    public function database(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'requirements');
        $db = $this->validateDatabase($request);

        if ($error = $this->installer->testConnection($db)) {
            return back()->withErrors(['database' => 'Connection failed: '.$error])
                ->withInput($request->except('password'));
        }

        try {
            $this->installer->saveDatabase($db);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->put('setup.db_driver', $db['driver']);
        $this->complete($request, 'database');

        return $this->next('application');
    }

    public function application(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'database');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'url' => ['required', 'url:http,https', 'max:255', 'not_regex:/[\x00-\x1F\x7F"\s]/'],
            'organization_name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ]);

        try {
            $this->installer->saveApplication(['name' => $data['name'], 'url' => $data['url']]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->put('setup.settings', [
            'organization_name' => $data['organization_name'],
            'timezone' => $data['timezone'],
            'currency' => strtoupper($data['currency']),
        ]);
        $this->complete($request, 'application');

        return $this->next('install');
    }

    public function install(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'application');
        @set_time_limit(300);

        $migrate = $this->installer->migrate();
        $log = "== Migrations ==\n".$migrate['output'];
        if (! $migrate['ok']) {
            $request->session()->put('setup.install_log', $log);

            return back()->with('error', 'Migrations failed. Review the log below.');
        }

        $seed = $this->installer->seed();
        $log .= "\n\n== Base data ==\n".$seed['output'];
        $request->session()->put('setup.install_log', $log);
        if (! $seed['ok']) {
            return back()->with('error', 'Seeding failed. Review the log below.');
        }

        try {
            $this->installer->applySettings($request->session()->get('setup.settings', []));
        } catch (Throwable $e) {
            return back()->with('error', 'Unable to save settings: '.$e->getMessage());
        }

        $this->complete($request, 'install');

        return $this->next('admin');
    }

    public function admin(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'install');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if (! Schema::hasTable('users')) {
            return back()->with('error', 'The database has not been migrated yet.');
        }

        $this->installer->createAdministrator($data);
        $this->complete($request, 'admin');

        return $this->next('finish');
    }

    public function finish(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'admin');

        try {
            $this->installer->finish();
        } catch (Throwable $e) {
            return back()->with('error', 'Unable to complete installation: '.$e->getMessage());
        }

        $request->session()->forget(['setup.completed', 'setup.settings', 'setup.install_log', 'setup.db_driver']);

        return redirect('/login')->with('success', 'Installation complete. Sign in with your administrator account.');
    }

    /** @return array{driver: string, host: ?string, port: ?int, database: string, username: ?string, password: ?string} */
    private function validateDatabase(Request $request): array
    {
        $data = $request->validate([
            'driver' => ['required', Rule::in(['mysql', 'sqlite'])],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:_\[\]]+$/'],
            'port' => ['required_if:driver,mysql', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
            'password' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
        ]);

        if ($data['driver'] === 'sqlite' && (str_contains($data['database'], '..') || ! preg_match('/^[A-Za-z0-9_\-\/.:\\\\ ]+$/', $data['database']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['database' => 'Use a plain file name (stored in /database) or an absolute path.']);
        }

        return [
            'driver' => $data['driver'],
            'host' => $data['host'] ?? null,
            'port' => isset($data['port']) ? (int) $data['port'] : null,
            'database' => $data['database'],
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
        ];
    }

    /** @return list<string> */
    private function completed(Request $request): array
    {
        return array_values((array) $request->session()->get('setup.completed', []));
    }

    private function complete(Request $request, string $step): void
    {
        $completed = $this->completed($request);
        if (! in_array($step, $completed, true)) {
            $completed[] = $step;
        }
        $request->session()->put('setup.completed', $completed);
    }

    private function requireStep(Request $request, string $step): void
    {
        if (! in_array($step, $this->completed($request), true)) {
            abort(redirect()->route('setup.index')->with('error', 'Complete the '.self::STEPS[$step].' step first.'));
        }
    }

    private function firstIncomplete(array $completed): string
    {
        foreach (array_keys(self::STEPS) as $step) {
            if (! in_array($step, $completed, true)) {
                return $step;
            }
        }

        return 'finish';
    }

    private function reachable(string $step, array $completed): bool
    {
        $keys = array_keys(self::STEPS);
        $index = array_search($step, $keys, true);

        return $index === 0 || in_array($keys[$index - 1], $completed, true);
    }

    private function next(string $step): RedirectResponse
    {
        return redirect()->route('setup.index', ['step' => $step]);
    }
}
