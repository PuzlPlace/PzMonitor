<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Base compartilhada dos testes do pacote.
 *
 * Bootstrap standalone (sem app Laravel completa): sobe um Container com
 * `config` (defaults de `pzmonitor.*`) e `cache` (CacheManager com driver
 * `array`), e aponta o root das Facades para ele. O driver `array`
 * implementa LockProvider, então os locks funcionam de verdade dentro do
 * processo — suficiente para cobrir toda a semântica do PzMonitor.
 */
abstract class PzMonitorTestCase extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        Container::setInstance($this->container);

        $this->container->singleton('config', fn (): ConfigRepository => new ConfigRepository([
            'cache' => [
                'default' => 'array',
                'stores' => ['array' => ['driver' => 'array']],
            ],
            'pzmonitor' => [
                'store' => null,
                'prefix' => 'pzmonitor:',
                'wait_timeout' => 60,
                'lock_ttl' => 300,
            ],
        ]));

        $this->container->singleton('cache', fn (Container $app): CacheManager => new CacheManager($app));

        Facade::setFacadeApplication($this->container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }
}
