<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;
use RuntimeException;

final class PzMonitorTryLockTest extends PzMonitorTestCase
{
    // -----------------------------------------------------------------------
    // Cenário 3 — falha rápida
    // -----------------------------------------------------------------------

    public function test_try_lock_com_chave_ocupada_lanca_busy_imediata_com_a_chave_preenchida(): void
    {
        // Arrange
        $chave = 'importacao-nfe-3';
        $dono = PzMonitor::tryAcquire($chave);
        $executou = false;

        // Act
        try {
            PzMonitor::tryLock($chave, function () use (&$executou): void {
                $executou = true;
            });
            $this->fail('Deveria ter lançado PzMonitorBusyException.');
        } catch (PzMonitorBusyException $e) {
            // Assert
            $this->assertFalse($executou);
            $this->assertSame($chave, $e->uniqueProcessKey);
            $this->assertSame("Already exists process. Process {$chave}", $e->getMessage());
        }

        $dono->release();
    }

    public function test_try_lock_com_chave_livre_executa_o_callback_retorna_o_valor_e_libera(): void
    {
        // Arrange
        $chave = 'importacao-nfe-livre';

        // Act
        $retorno = PzMonitor::tryLock($chave, fn (): int => 42);

        // Assert
        $this->assertSame(42, $retorno);
        PzMonitor::tryAcquire($chave)->release();
    }

    public function test_try_lock_propaga_excecao_do_callback_e_libera_o_lock(): void
    {
        // Arrange
        $chave = 'importacao-nfe-erro';

        // Act
        try {
            PzMonitor::tryLock($chave, function (): void {
                throw new RuntimeException('falha no callback');
            });
            $this->fail('A exceção do callback deveria ter sido propagada.');
        } catch (RuntimeException $e) {
            // Assert
            $this->assertSame('falha no callback', $e->getMessage());
        }

        PzMonitor::tryAcquire($chave)->release();
    }
}
