<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Exception;

use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Tempo máximo de espera pela aquisição do lock esgotado.
 *
 * Mensagem idêntica à da versão anterior do Monitor — não alterar. A
 * exceção original do framework fica acessível via getPrevious().
 */
final class PzMonitorTimeoutException extends PzMonitorException
{
    public function __construct(string $uniqueProcessKey, LockTimeoutException $previous)
    {
        parent::__construct(
            $uniqueProcessKey,
            "Maximum wait time reached. Process {$uniqueProcessKey}",
            $previous,
        );
    }
}
