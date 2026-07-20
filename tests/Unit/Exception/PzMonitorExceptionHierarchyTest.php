<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit\Exception;

use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use PHPUnit\Framework\TestCase;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\Exception\PzMonitorException;
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;

final class PzMonitorExceptionHierarchyTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Hierarquia
    // -----------------------------------------------------------------------

    public function test_catch_da_base_captura_busy_e_timeout(): void
    {
        $capturadas = [];

        foreach ($this->exceptions() as $exception) {
            try {
                throw $exception;
            } catch (PzMonitorException $e) {
                $capturadas[] = $e;
            }
        }

        $this->assertCount(2, $capturadas);

        foreach ($capturadas as $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    // -----------------------------------------------------------------------
    // Mensagens e chave do processo
    // -----------------------------------------------------------------------

    public function test_busy_carrega_a_chave_e_a_mensagem_original(): void
    {
        $e = new PzMonitorBusyException('minha-chave');

        $this->assertSame('Already exists process. Process minha-chave', $e->getMessage());
        $this->assertSame('minha-chave', $e->uniqueProcessKey);
        $this->assertSame(0, $e->getCode());
        $this->assertNull($e->getPrevious());
    }

    public function test_timeout_carrega_a_chave_a_mensagem_original_e_o_previous(): void
    {
        $previous = new LockTimeoutException();

        $e = new PzMonitorTimeoutException('minha-chave', $previous);

        $this->assertSame('Maximum wait time reached. Process minha-chave', $e->getMessage());
        $this->assertSame('minha-chave', $e->uniqueProcessKey);
        $this->assertSame(0, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return list<PzMonitorException>
     */
    private function exceptions(): array
    {
        return [
            new PzMonitorBusyException('chave-ocupada'),
            new PzMonitorTimeoutException('chave-lenta', new LockTimeoutException()),
        ];
    }
}
