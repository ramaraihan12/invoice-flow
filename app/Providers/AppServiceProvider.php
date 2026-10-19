<?php

namespace App\Providers;

use App\Llm\Ollama;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
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
        //
    }

    private function ollamaThink(): ?bool
    {
        $think = config('invoices.ollama_think');

        return $think === null ? null : filter_var($think, FILTER_VALIDATE_BOOL);
    }
}
