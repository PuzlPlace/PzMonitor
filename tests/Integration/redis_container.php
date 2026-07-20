<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Container com store Redis real
|--------------------------------------------------------------------------
|
| Compartilhado pelo teste de integração e pelos processos filhos que ele
| dispara. Diferente do bootstrap da suíte unitária (store `array`, um único
| processo), aqui o lock precisa de um backend externo — é o único ponto onde
| a exclusão mútua *entre processos* é de fato exercitada.
|
| Retorna um Container pronto, com as Facades apontadas para ele.
|
*/

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Facade;

require_once __DIR__.'/../bootstrap.php';

return static function (): Container {
    $container = new Container();
    Container::setInstance($container);

    $container->singleton('config', fn (): ConfigRepository => new ConfigRepository([
        'cache' => [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                    'lock_connection' => 'default',
                ],
            ],
            'prefix' => 'pzmonitor_test_cache:',
        ],
        'database' => [
            'redis' => [
                'client' => 'predis',
                'options' => ['prefix' => ''],
                'default' => [
                    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                    'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                    'password' => getenv('REDIS_PASSWORD') ?: null,
                    'database' => 0,
                ],
            ],
        ],
        'pzmonitor' => [
            'store' => 'redis',
            'prefix' => 'pzmonitor:',
            'wait_timeout' => 60,
            'lock_ttl' => 300,
        ],
    ]));

    $container->singleton('redis', fn (Container $app): RedisManager => new RedisManager(
        $app,
        'predis',
        $app['config']['database.redis'],
    ));

    $container->singleton('cache', fn (Container $app): CacheManager => new CacheManager($app));

    Facade::setFacadeApplication($container);

    return $container;
};
