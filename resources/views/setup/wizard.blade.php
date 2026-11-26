@extends('layouts.setup')

@section('title', 'Setup · '.config('app.name', 'StageTrack Enterprise'))

@section('content')
    <nav class="w-full shrink-0 md:w-56" aria-label="Setup steps">
        <ol class="flex flex-wrap gap-2 md:flex-col md:gap-1">
            @foreach ($steps as $index => $step)
                @php($isCurrent = $step['key'] === $current)
                @php($isCompleted = in_array($step['key'], $completed, true))
                <li aria-current="{{ $isCurrent ? 'step' : 'false' }}"
                    class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm {{ $isCurrent ? 'bg-primary-50 font-medium text-primary-800 dark:bg-dark-700 dark:text-primary-300' : 'text-gray-600 dark:text-gray-300' }}">
                    <span class="grid size-5 shrink-0 place-items-center rounded-full text-[11px] {{ $isCompleted ? 'bg-emerald-500 text-white' : 'border border-gray-300 dark:border-dark-500' }}">
                        {{ $isCompleted ? '✓' : $index + 1 }}
                    </span>
                    {{ $step['label'] }}
                </li>
            @endforeach
        </ol>
    </nav>

    <main class="min-w-0 flex-1">
        <h1 class="mb-5 text-2xl font-semibold">Setup · {{ collect($steps)->firstWhere('key', $current)['label'] ?? 'Setup' }}</h1>

        @if (session('error'))
            <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">{{ session('error') }}</p>
        @endif
        @if (session('success'))
            <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                <p class="font-medium">Please correct the following:</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($current === 'requirements')
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="requirements-title">
                <h2 id="requirements-title" class="mb-4 text-lg font-semibold">Server requirements</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($requirements as $check)
                        <li>
                            <strong class="{{ $check['passed'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ $check['passed'] ? 'Pass' : ($check['required'] ? 'Required' : 'Optional') }}</strong>
                            · {{ $check['label'] }} <span class="text-gray-500 dark:text-gray-300">— {{ $check['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <form class="mt-4" method="POST" action="{{ route('setup.requirements') }}">
                    @csrf
                    <x-button type="submit" color="primary" :disabled="! $requirementsPass">Continue</x-button>
                </form>
            </section>
        @elseif ($current === 'database')
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="database-title">
                <h2 id="database-title" class="mb-4 text-lg font-semibold">Database connection</h2>
                <form class="space-y-4" method="POST" action="{{ route('setup.database') }}">
                    @csrf
                    <div>
                        <label for="db-driver" class="mb-1 block text-sm font-medium">Driver <span aria-hidden="true">*</span></label>
                        <select id="db-driver" name="driver" required class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            <option value="mysql" @selected(old('driver', $database['driver']) === 'mysql')>MySQL / MariaDB</option>
                            <option value="sqlite" @selected(old('driver', $database['driver']) === 'sqlite')>SQLite</option>
                        </select>
                        @error('driver') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div id="mysql-fields" class="space-y-4">
                        <div>
                            <label for="db-host" class="mb-1 block text-sm font-medium">Host <span aria-hidden="true">*</span></label>
                            <input id="db-host" name="host" value="{{ old('host', $database['host']) }}" autocomplete="off" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="db-port" class="mb-1 block text-sm font-medium">Port <span aria-hidden="true">*</span></label>
                            <input id="db-port" name="port" type="number" min="1" max="65535" value="{{ old('port', $database['port']) }}" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="db-username" class="mb-1 block text-sm font-medium">Username <span aria-hidden="true">*</span></label>
                            <input id="db-username" name="username" value="{{ old('username', $database['username']) }}" autocomplete="username" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="db-password" class="mb-1 block text-sm font-medium">Password</label>
                            <input id="db-password" name="password" type="password" autocomplete="new-password" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="db-name" class="mb-1 block text-sm font-medium" id="db-name-label"><span id="db-name-description">Database name</span> <span aria-hidden="true">*</span></label>
                        <input id="db-name" name="database" required value="{{ old('database', $database['database']) }}" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                        @error('database') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-button type="submit" color="primary">Save and continue</x-button>
                        <x-button type="submit" color="secondary" :formaction="route('setup.database.test')">Test connection</x-button>
                    </div>
                </form>
            </section>
        @elseif ($current === 'application')
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="application-title">
                <h2 id="application-title" class="mb-4 text-lg font-semibold">Application settings</h2>
                <form class="space-y-4" method="POST" action="{{ route('setup.application') }}">
                    @csrf
                    <div>
                        <label for="app-name" class="mb-1 block text-sm font-medium">Application name <span aria-hidden="true">*</span></label>
                        <input id="app-name" name="name" required maxlength="100" value="{{ old('name', $application['name']) }}" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="app-url" class="mb-1 block text-sm font-medium">Application URL <span aria-hidden="true">*</span></label>
                        <input id="app-url" name="url" type="url" required maxlength="255" value="{{ old('url', $application['url']) }}" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                        @error('url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="organization" class="mb-1 block text-sm font-medium">Organization name <span aria-hidden="true">*</span></label>
                        <input id="organization" name="organization_name" required maxlength="150" value="{{ old('organization_name', $application['organization_name']) }}" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                        @error('organization_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="timezone" class="mb-1 block text-sm font-medium">Timezone <span aria-hidden="true">*</span></label>
                        <select id="timezone" name="timezone" required class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $application['timezone']) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="currency" class="mb-1 block text-sm font-medium">Currency <span aria-hidden="true">*</span></label>
                        <select id="currency" name="currency" required class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}" @selected(old('currency', $application['currency']) === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                        @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" color="primary">Save and continue</x-button>
                </form>
            </section>
        @elseif ($current === 'install')
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="install-title">
                <h2 id="install-title" class="mb-4 text-lg font-semibold">Install database</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Run database migrations and install base data. This can take a few minutes.</p>
                @if ($installLog)
                    <pre class="mt-3 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 text-xs dark:bg-dark-800">{{ $installLog }}</pre>
                @endif
                <form class="mt-4" method="POST" action="{{ route('setup.install') }}">
                    @csrf
                    <x-button type="submit" color="primary">Run installation</x-button>
                </form>
            </section>
        @elseif ($current === 'admin')
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="admin-title">
                <h2 id="admin-title" class="mb-4 text-lg font-semibold">Administrator account</h2>
                @if ($hasAdministrator)
                    <p class="mb-3 text-sm text-red-600">An administrator already exists. Setup cannot create or change an administrator in this database.</p>
                @else
                    <form class="space-y-4" method="POST" action="{{ route('setup.admin') }}">
                        @csrf
                        <div>
                            <label for="admin-name" class="mb-1 block text-sm font-medium">Name <span aria-hidden="true">*</span></label>
                            <input id="admin-name" name="name" required maxlength="255" value="{{ old('name') }}" autocomplete="name" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin-username" class="mb-1 block text-sm font-medium">Username</label>
                            <input id="admin-username" name="username" maxlength="64" value="{{ old('username') }}" autocomplete="username" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin-email" class="mb-1 block text-sm font-medium">Email <span aria-hidden="true">*</span></label>
                            <input id="admin-email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" autocomplete="email" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin-password" class="mb-1 block text-sm font-medium">Password <span aria-hidden="true">*</span></label>
                            <p id="password-hint" class="mb-1 text-sm text-gray-500 dark:text-gray-300">At least 12 characters with upper and lower case letters and numbers.</p>
                            <input id="admin-password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin-confirm" class="mb-1 block text-sm font-medium">Confirm password <span aria-hidden="true">*</span></label>
                            <input id="admin-confirm" name="password_confirmation" type="password" required autocomplete="new-password" class="block w-full rounded-md border-gray-300 shadow-sm dark:border-dark-500 dark:bg-dark-800">
                        </div>
                        <x-button type="submit" color="primary">Create administrator</x-button>
                    </form>
                @endif
            </section>
        @else
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-dark-600 dark:bg-dark-700" aria-labelledby="finish-title">
                <h2 id="finish-title" class="mb-4 text-lg font-semibold">Finish setup</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Complete the installation and sign in with your administrator account.</p>
                <form class="mt-4" method="POST" action="{{ route('setup.finish') }}">
                    @csrf
                    <x-button type="submit" color="primary">Finish installation</x-button>
                </form>
            </section>
        @endif

        @php($completedSteps = collect($steps)->whereIn('key', $completed))
        @if ($completedSteps->isNotEmpty())
            <nav class="mt-5 flex flex-wrap gap-3 text-sm" aria-label="Completed setup steps">
                @foreach ($completedSteps as $step)
                    <a href="{{ route('setup.index', ['step' => $step['key']]) }}" class="text-primary-700 underline hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100">{{ $step['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </main>

    <script>
        (() => {
            const driver = document.getElementById('db-driver');
            if (!driver) return;
            const fields = document.getElementById('mysql-fields');
            const nameDescription = document.getElementById('db-name-description');
            const mysqlRequired = ['db-host', 'db-port', 'db-username'];
            const updateDriverFields = () => {
                const mysql = driver.value === 'mysql';
                fields.hidden = !mysql;
                mysqlRequired.forEach((id) => {
                    document.getElementById(id).required = mysql;
                });
                nameDescription.textContent = mysql ? 'Database name' : 'SQLite file name or absolute path';
            };
            driver.addEventListener('change', updateDriverFields);
            updateDriverFields();
        })();
    </script>
@endsection
