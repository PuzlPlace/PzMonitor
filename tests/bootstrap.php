<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Helper now()
|--------------------------------------------------------------------------
|
| `Illuminate\Cache\Lock::block()` usa o helper global `now()`, que é
| declarado pelo pacote Foundation do framework — ausente numa suíte
| standalone que só instala `illuminate/cache` + `illuminate/support`.
| Numa aplicação Laravel real o helper já existe; aqui ele é declarado
| com a mesma semântica apenas para o bootstrap dos testes.
|
*/
if (! function_exists('now')) {
    function now(DateTimeZone|string|null $tz = null): Carbon
    {
        return Carbon::now($tz);
    }
}
