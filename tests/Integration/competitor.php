<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Processo concorrente
|--------------------------------------------------------------------------
|
| Disparado várias vezes em paralelo pelo teste de integração. Cada instância
| é um processo PHP separado — nenhuma memória compartilhada com os demais,
| que é justamente o cenário que o pacote promete cobrir e que a suíte
| unitária (um processo, store `array`) não consegue reproduzir.
|
| Uso: php competitor.php <chave> <inicio-unix-float> <hold-em-segundos>
| Saída: `ACQUIRED` (entrou na seção crítica) ou `BUSY` (chave ocupada).
|
*/

use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;

/** @var callable(): \Illuminate\Container\Container $makeContainer */
$makeContainer = require __DIR__.'/redis_container.php';
$makeContainer();

[$key, $startAt, $hold] = [$argv[1], (float) $argv[2], (float) $argv[3]];

// Barreira de largada: todos os filhos disputam a chave no mesmo instante.
// Sem isso um processo poderia adquirir e liberar antes de o próximo nascer,
// e o teste passaria sem nunca ter havido concorrência real.
while (microtime(true) < $startAt) {
    usleep(1_000);
}

try {
    PzMonitor::tryLock($key, function () use ($hold): void {
        usleep((int) ($hold * 1_000_000));
    });

    echo 'ACQUIRED';
} catch (PzMonitorBusyException) {
    echo 'BUSY';
}
