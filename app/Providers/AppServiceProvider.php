<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The checked-in/exported OpenAPI document is used by the docs routes below.
        // This keeps the browser documentation available when the development database
        // is offline (Scramble's live generator introspects Eloquent tables).
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $config = Scramble::getGeneratorConfig('default');
        $middleware = $config->get('middleware', []);

        Route::middleware($middleware)
            ->get('docs/api', function () use ($config) {
                $spec = $this->readExportedOpenApiDocument();

                // The static export has no GeneratorResult, so Scramble's optional
                // diagnostics panel must not try to read diagnostics from null.
                config(['scramble.dev_tools.enabled' => false]);

                return view($config->renderer()->view, [
                    'spec' => $spec,
                    'config' => $config,
                    'result' => null,
                ]);
            })
            ->name('scramble.docs.ui');

        Route::middleware($middleware)
            ->get('docs/api.json', function () {
                return response()->json($this->readExportedOpenApiDocument(), options: JSON_PRETTY_PRINT);
            })
            ->name('scramble.docs.document');
    }

    /**
     * @return array<string, mixed>
     */
    private function readExportedOpenApiDocument(): array
    {
        $exportPath = config('scramble.export_path', 'api.json');
        $path = $this->isAbsolutePath($exportPath) ? $exportPath : base_path($exportPath);

        if (! is_file($path)) {
            abort(503, 'The API documentation export is missing. Run: php artisan scramble:export');
        }

        $spec = json_decode(file_get_contents($path), true);

        if (! is_array($spec) || json_last_error() !== JSON_ERROR_NONE) {
            abort(503, 'The API documentation export is invalid. Run: php artisan scramble:export');
        }

        return $spec;
    }

    private function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path);
    }
}
