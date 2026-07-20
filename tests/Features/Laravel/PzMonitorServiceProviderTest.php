<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Features\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Puzl\PzMonitor\Laravel\PzMonitorServiceProvider;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\Features\Laravel\Fixtures\AppStub;

/**
 * Prova o RF-10: `composer require` basta — o provider mescla a config,
 * registra o publish só em console e os locks passam a respeitar
 * `pzmonitor.store` e `pzmonitor.prefix`.
 */
final class PzMonitorServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetEnvironment();
    }

    protected function tearDown(): void
    {
        $this->resetEnvironment();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // register() — merge de config
    // -----------------------------------------------------------------------

    public function test_register_mescla_os_defaults_do_pacote_na_config(): void
    {
        $app = $this->makeApp();

        (new PzMonitorServiceProvider($app))->register();

        $config = $app->make('config');
        $this->assertSame('pzmonitor:', $config->get('pzmonitor.prefix'));
        $this->assertNull($config->get('pzmonitor.store'));
    }

    public function test_register_nao_sobrescreve_valores_definidos_pela_aplicacao(): void
    {
        $app = $this->makeApp();
        $app->make('config')->set('pzmonitor', ['prefix' => 'custom:', 'store' => 'array']);

        (new PzMonitorServiceProvider($app))->register();

        $config = $app->make('config');
        $this->assertSame('custom:', $config->get('pzmonitor.prefix'));
        $this->assertSame('array', $config->get('pzmonitor.store'));
    }

    // -----------------------------------------------------------------------
    // boot() — publishes com a tag pzmonitor-config (apenas em console)
    // -----------------------------------------------------------------------

    public function test_boot_em_console_registra_o_publish_da_config(): void
    {
        $app = $this->makeApp(true);

        (new PzMonitorServiceProvider($app))->boot();

        $paths = ServiceProvider::pathsToPublish(PzMonitorServiceProvider::class, PzMonitorServiceProvider::PUBLISH_TAG);

        $this->assertSame(
            [realpath(PzMonitorServiceProvider::CONFIG_PATH)],
            array_map(realpath(...), array_keys($paths)),
        );
        $this->assertSame(
            [$app->basePath('config/pzmonitor.php')],
            array_values($paths),
        );
    }

    public function test_boot_fora_de_console_nao_registra_nenhum_publish(): void
    {
        $app = $this->makeApp(false);

        (new PzMonitorServiceProvider($app))->boot();

        $this->assertSame(
            [],
            ServiceProvider::pathsToPublish(PzMonitorServiceProvider::class, PzMonitorServiceProvider::PUBLISH_TAG),
        );
    }

    // -----------------------------------------------------------------------
    // Efeito da config no lock
    // -----------------------------------------------------------------------

    public function test_lock_usa_o_prefixo_configurado_pela_aplicacao(): void
    {
        $app = $this->makeApp();
        $app->make('config')->set('pzmonitor', ['prefix' => 'custom:', 'store' => 'array']);
        (new PzMonitorServiceProvider($app))->register();

        $lock = PzMonitor::tryAcquire('minha-chave');

        try {
            $this->assertFalse(Cache::store('array')->lock('custom:minha-chave')->get());
            $this->assertTrue(Cache::store('array')->lock('outro:minha-chave')->get());
        } finally {
            $lock->release();
        }
    }

    public function test_lock_usa_o_prefixo_default_do_pacote_quando_a_app_nao_configura(): void
    {
        $app = $this->makeApp();
        (new PzMonitorServiceProvider($app))->register();

        $lock = PzMonitor::tryAcquire('minha-chave');

        try {
            $this->assertFalse(Cache::store('array')->lock('pzmonitor:minha-chave')->get());
        } finally {
            $lock->release();
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Container mínimo com `config` (cache em store `array`) e `cache`,
     * já eleito root das Facades — o mais próximo de uma app real sem
     * subir o framework completo.
     */
    private function makeApp(bool $runningInConsole = true): AppStub
    {
        $app = new AppStub($runningInConsole);

        $app->instance('config', new ConfigRepository([
            'cache' => [
                'default' => 'array',
                'stores' => ['array' => ['driver' => 'array']],
            ],
        ]));
        $app->singleton('cache', fn (Container $container): CacheManager => new CacheManager($container));

        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        return $app;
    }

    /**
     * Zera o estado global compartilhado entre testes: publishes estáticos
     * do ServiceProvider, Facades, Container e as variáveis de ambiente
     * lidas por `config/pzmonitor.php`.
     */
    private function resetEnvironment(): void
    {
        ServiceProvider::$publishes = [];
        ServiceProvider::$publishGroups = [];

        putenv('PZMONITOR_STORE');
        putenv('PZMONITOR_PREFIX');

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }
}
