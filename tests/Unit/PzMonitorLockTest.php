<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;
use RuntimeException;

final class PzMonitorLockTest extends PzMonitorTestCase
{
    // -----------------------------------------------------------------------
    // Cenário 1 — caminho feliz
    // -----------------------------------------------------------------------

    public function test_lock_executa_o_callback_com_exclusividade_retorna_o_valor_e_libera(): void
    {
        // Arrange
        $chave = 'fechamento-caixa-1';

        // Act
        $retorno = PzMonitor::lock($chave, fn (): string => 'processado');

        // Assert
        $this->assertSame('processado', $retorno);
        PzMonitor::tryAcquire($chave)->release();
    }

    public function test_lock_grava_a_chave_no_store_com_o_prefixo_configurado(): void
    {
        // Arrange
        $chave = 'fechamento-caixa-prefixo';

        // Act
        PzMonitor::lock($chave, function () use ($chave): void {
            // Assert (durante a seção crítica a chave está presa no store)
            $this->assertArrayHasKey('pzmonitor:'.$chave, $this->locksDoStore());
        });
    }

    // -----------------------------------------------------------------------
    // Cenário 2 — exceção do callback
    // -----------------------------------------------------------------------

    public function test_lock_propaga_excecao_do_callback_e_libera_o_lock(): void
    {
        // Arrange
        $chave = 'fechamento-caixa-2';

        // Act
        try {
            PzMonitor::lock($chave, function (): void {
                throw new RuntimeException('falha no callback');
            });
            $this->fail('A exceção do callback deveria ter sido propagada.');
        } catch (RuntimeException $e) {
            // Assert
            $this->assertSame('falha no callback', $e->getMessage());
        }

        PzMonitor::tryAcquire($chave)->release();
    }

    // -----------------------------------------------------------------------
    // Cenário 4 — timeout
    // -----------------------------------------------------------------------

    public function test_lock_com_timeout_zero_e_chave_ocupada_lanca_timeout_sem_executar_o_callback(): void
    {
        // Arrange
        $chave = 'fechamento-caixa-4';
        $dono = PzMonitor::tryAcquire($chave);
        $executou = false;

        // Act
        try {
            PzMonitor::lock($chave, function () use (&$executou): void {
                $executou = true;
            }, waitTimeoutInSeconds: 0);
            $this->fail('Deveria ter lançado PzMonitorTimeoutException.');
        } catch (PzMonitorTimeoutException $e) {
            // Assert
            $this->assertFalse($executou);
            $this->assertSame($chave, $e->uniqueProcessKey);
            $this->assertSame("Maximum wait time reached. Process {$chave}", $e->getMessage());
            $this->assertInstanceOf(LockTimeoutException::class, $e->getPrevious());
        }

        $dono->release();
    }

    // -----------------------------------------------------------------------
    // Cenário 5 — regressão: o perdedor do timeout não pode derrubar o dono
    // -----------------------------------------------------------------------

    public function test_perdedor_do_timeout_nao_derruba_o_lock_do_dono(): void
    {
        // Arrange
        $chave = 'fechamento-caixa-5';
        $donoA = PzMonitor::tryAcquire($chave);

        // Act — B estoura o timeout esperando pela chave de A
        try {
            PzMonitor::lock($chave, fn (): bool => true, waitTimeoutInSeconds: 0);
            $this->fail('Deveria ter lançado PzMonitorTimeoutException.');
        } catch (PzMonitorTimeoutException) {
            // esperado
        }

        // Assert — C ainda encontra a chave de A presa
        try {
            PzMonitor::tryAcquire($chave);
            $this->fail('A chave do dono foi indevidamente liberada pelo perdedor do timeout.');
        } catch (PzMonitorBusyException $e) {
            $this->assertSame($chave, $e->uniqueProcessKey);
        }

        // ...e só libera quando o próprio dono solta
        $this->assertTrue($donoA->release());
        PzMonitor::tryAcquire($chave)->release();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{owner: string, expiresAt: mixed}>
     */
    private function locksDoStore(): array
    {
        /** @var \Illuminate\Cache\ArrayStore $store */
        $store = $this->container->make('cache')->store()->getStore();

        return $store->locks;
    }
}
