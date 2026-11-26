<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers each domain module under app/Domain/{Module} and registers its
 * routes (routes.php, web middleware group) and console commands.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            foreach ($this->moduleFiles('routes.php') as $file) {
                Route::middleware('web')->group($file);
            }
        }

        if ($this->app->runningInConsole()) {
            $commands = [];
            foreach (glob(app_path('Domain/*/Console/*.php')) ?: [] as $file) {
                $module = basename(dirname($file, 2));
                $commands[] = 'App\\Domain\\'.$module.'\\Console\\'.basename($file, '.php');
            }
            $this->commands($commands);
        }
    }

    /** @return list<string> */
    private function moduleFiles(string $name): array
    {
        $files = glob(app_path('Domain/*/'.$name)) ?: [];
        sort($files);

        return $files;
    }
}
