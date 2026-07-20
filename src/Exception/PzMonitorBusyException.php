<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Exception;

/**
 * Chave de lock já ocupada por outro processo.
 *
 * Mensagem idêntica à da versão anterior do Monitor — não alterar
 * (telas e logs da aplicação consumidora dependem deste texto).
 */
final class PzMonitorBusyException extends PzMonitorException
{
    public function __construct(string $uniqueProcessKey)
    {
        parent::__construct(
            $uniqueProcessKey,
            "Already exists process. Process {$uniqueProcessKey}",
        );
    }
}
