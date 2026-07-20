<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Store de cache dos locks
    |--------------------------------------------------------------------------
    |
    | Store de cache usado para os locks distribuídos. `null` usa o store
    | padrão da aplicação. Em produção, o store deve resolver para um
    | backend compartilhado entre as instâncias (Redis).
    |
    */
    'store' => env('PZMONITOR_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Prefixo das chaves
    |--------------------------------------------------------------------------
    |
    | Prefixo aplicado a toda chave de lock. Garante que as chaves do
    | PzMonitor nunca colidam com as do Monitor v1 da aplicação.
    |
    */
    'prefix' => env('PZMONITOR_PREFIX', 'pzmonitor:'),

    /*
    |--------------------------------------------------------------------------
    | Tempo de espera padrão (segundos)
    |--------------------------------------------------------------------------
    |
    | Quanto tempo os métodos bloqueantes (`lock` e `acquire`) tentam entrar
    | na seção crítica antes de lançar `PzMonitorTimeoutException`. Vale como
    | padrão da aplicação; o argumento passado na chamada sempre prevalece.
    |
    */
    'wait_timeout' => (int) env('PZMONITOR_WAIT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | TTL padrão do lock (segundos)
    |--------------------------------------------------------------------------
    |
    | Prazo após o qual o cache expira a chave sozinho, mesmo sem `release()`.
    | É rede de segurança contra processo morto, não watchdog: rotina que passa
    | do TTL perde o lock com a seção crítica ainda rodando. O valor adequado
    | depende da duração de cada rotina — este padrão é só o piso da aplicação,
    | passe o argumento na chamada quando a rotina for longa.
    |
    */
    'lock_ttl' => (int) env('PZMONITOR_LOCK_TTL', 300),

];
