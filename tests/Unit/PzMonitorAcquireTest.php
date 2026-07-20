<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;

final class PzMonitorAcquireTest extends PzMonitorTestCase
{
    // -----------------------------------------------------------------------
    // Cenário 6 — aquisição crua
    // -----------------------------------------------------------------------

    public function test_acquire_retorna_lock_e_release_libera_a_chave(): void
    {
        // Arrange
        $chave = 'rotina-noturna-6';

        // Act
        $lock = PzMonitor::acquire($chave);

        // Assert
        $this->assertTrue($lock->release());
        PzMonitor::acquire($chave)->release();
    }

    public function test_acquire_sem_release_mantem_a_chave_presa_para_a_proxima_tentativa(): void
    {
        // Arrange
        $chave = 'rotina-noturna-6-sem-release';
        $dono = PzMonitor::acquire($chave);

        // Act
        try {
            PzMonitor::tryAcquire($chave);
            $this->fail('Deveria ter lançado PzMonitorBusyException.');
        } catch (PzMonitorBusyException $e) {
            // Assert
            $this->assertSame($chave, $e->uniqueProcessKey);
        }

        $dono->release();
    }

    public function test_acquire_com_timeout_zero_e_chave_ocupada_lanca_timeout(): void
    {
        // Arrange
        $chave = 'rotina-noturna-6-timeout';
        $dono = PzMonitor::acquire($chave);

        // Act
        try {
            PzMonitor::acquire($chave, waitTimeoutInSeconds: 0);
            $this->fail('Deveria ter lançado PzMonitorTimeoutException.');
        } catch (PzMonitorTimeoutException $e) {
            // Assert
            $this->assertSame($chave, $e->uniqueProcessKey);
            $this->assertInstanceOf(LockTimeoutException::class, $e->getPrevious());
        }

        $dono->release();
    }

    public function test_release_por_nao_dono_nao_libera_a_chave(): void
    {
        // Arrange — o não-dono é um handle da mesma chave, com outro owner token
        $chave = 'rotina-noturna-6-owner-safe';
        $dono = PzMonitor::acquire($chave);
        $naoDono = $this->container->make('cache')->store()->lock('pzmonitor:'.$chave, 300);

        // Act
        $liberou = $naoDono->release();

        // Assert
        $this->assertFalse($liberou);
        $this->expectException(PzMonitorBusyException::class);

        try {
            PzMonitor::tryAcquire($chave);
        } finally {
            $dono->release();
        }
    }
}
