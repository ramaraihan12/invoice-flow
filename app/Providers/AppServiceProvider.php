<?php

namespace App\Providers;

use App\Filesystem\DatabaseAdapter;
use App\Llm\Ollama;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Prism\Prism\PrismManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(PrismManager::class, function (PrismManager $prism): void {
            $prism->extend('ollama', fn (Application $app, array $config): Ollama => new Ollama(
                apiKey: $config['api_key'] ?? '',
                url: $config['url'],
                think: $this->ollamaThink(),
            ));
        });
    }

    public function boot(): void
    {
        Storage::extend('database', function (Application $app, array $config): FilesystemAdapter {
            $adapter = new DatabaseAdapter;

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        // Vercel ends TLS in front of the container, so PHP only ever sees http.
        if (config('invoices.demo.enabled') && $this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function ollamaThink(): ?bool
    {
        $think = config('invoices.ollama_think');

        return $think === null ? null : filter_var($think, FILTER_VALIDATE_BOOL);
    }
}
