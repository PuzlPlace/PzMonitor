<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Exception;

use Exception;
use Throwable;

/**
 * Base da família de exceptions do PzMonitor.
 *
 * Estende \Exception para que os `catch (Exception)` já existentes na
 * aplicação consumidora continuem funcionando. Expõe a chave do processo
 * em propriedade readonly para que o caller possa logá-la sem parsear a
 * mensagem.
 */
abstract class PzMonitorException extends Exception
{
    public function __construct(
        public readonly string $uniqueProcessKey,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
