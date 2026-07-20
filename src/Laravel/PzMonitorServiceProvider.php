<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider Laravel do PzMonitor.
 *
 * Mantém o pacote "plug-and-play" via auto-discovery (declarado em
 * `composer.json` `extra.laravel.providers`):
 *
 * - `register()`: mescla `config/pzmonitor.php` em `config('pzmonitor.*')`,
 *   sem sobrescrever valores já definidos pela aplicação.
 * - `boot()`: registra o `publishes` com a tag `pzmonitor-config` apenas
 *   quando rodando em console (economia de memória em runtime web).
 *
 * O provider não registra bindings: `PzMonitor` é estática e lê a config
 * pela Facade `Config` — aqui só vivem config e publish.
 */
final class PzMonitorServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__.'/../../config/pzmonitor.php';

    public const PUBLISH_TAG = 'pzmonitor-config';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'pzmonitor');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->configPath('pzmonitor.php'),
            ], self::PUBLISH_TAG);
        }
    }

    /**
     * Resolve o destino do publish. Em ambiente Laravel completo usa o
     * helper `config_path()`; em testes sob `Illuminate\Container\Container`
     * puro, faz fallback para `basePath('config/...')`.
     */
    protected function configPath(string $file): string
    {
        return function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/'.$file);
    }
}
