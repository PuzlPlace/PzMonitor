<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use InvalidArgumentException;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;
use RuntimeException;

final class PzMonitorTryLockManyTest extends PzMonitorTestCase
{
    /**
     * Prova que a chave está livre: adquire e libera em seguida.
     */
    private function assertChaveLivre(string $chave): void
    {
        PzMonitor::tryAcquire($chave)->release();
        $this->assertTrue(true);
    }

    private function assertChaveOcupada(string $chave): void
    {
        try {
            PzMonitor::tryAcquire($chave)->release();
            $this->fail("A chave {$chave} deveria estar ocupada.");
        } catch (PzMonitorBusyException $e) {
            $this->assertSame($chave, $e->uniqueProcessKey);
        }
    }

    // -----------------------------------------------------------------------
    // Cenário 7 — todas as chaves livres
    // -----------------------------------------------------------------------

    public function test_todas_as_chaves_livres_adquire_executa_e_libera_todas(): void
    {
        // Arrange
        $chaves = ['parcela-3', 'parcela-1', 'parcela-2'];

        // Act
        $retorno = PzMonitor::tryLockMany($chaves, fn (): string => 'lote-pago');

        // Assert
        $this->assertSame('lote-pago', $retorno);

        foreach ($chaves as $chave) {
            $this->assertChaveLivre($chave);
        }
    }

    public function test_excecao_do_callback_propaga_e_ainda_assim_libera_todas_as_chaves(): void
    {
        // Arrange
        $chaves = ['erro-a', 'erro-b', 'erro-c'];

        // Act
        try {
            PzMonitor::tryLockMany($chaves, function (): void {
                throw new RuntimeException('falha no lote');
            });
            $this->fail('A exceção do callback deveria ter sido propagada.');
        } catch (RuntimeException $e) {
            // Assert
            $this->assertSame('falha no lote', $e->getMessage());
        }

        foreach ($chaves as $chave) {
            $this->assertChaveLivre($chave);
        }
    }

    // -----------------------------------------------------------------------
    // Cenário 8 — rollback da aquisição parcial
    // -----------------------------------------------------------------------

    public function test_chave_do_meio_ocupada_faz_rollback_e_lanca_busy_nomeando_a_chave(): void
    {
        // Arrange: ordem lexicográfica é a, b, c — 'b' presa por outro dono.
        $dono = PzMonitor::tryAcquire('b');
        $executou = false;

        // Act: entrada fora de ordem prova a ordenação interna.
        try {
            PzMonitor::tryLockMany(['c', 'a', 'b'], function () use (&$executou): void {
                $executou = true;
            });
            $this->fail('Deveria ter lançado PzMonitorBusyException.');
        } catch (PzMonitorBusyException $e) {
            // Assert
            $this->assertFalse($executou);
            $this->assertSame('b', $e->uniqueProcessKey);
        }

        // 'a' foi adquirida antes da falha e liberada no rollback;
        // 'c' nunca chegou a ser tocada; 'b' segue com o dono original.
        $this->assertChaveLivre('a');
        $this->assertChaveLivre('c');
        $this->assertChaveOcupada('b');

        $dono->release();
    }

    // -----------------------------------------------------------------------
    // Cenário 9 — deduplicação
    // -----------------------------------------------------------------------

    public function test_chaves_duplicadas_sao_deduplicadas_e_nao_travam_contra_si_mesmas(): void
    {
        // Arrange
        $executou = false;

        // Act
        PzMonitor::tryLockMany(['x', 'x', 'y'], function () use (&$executou): void {
            $executou = true;
        });

        // Assert
        $this->assertTrue($executou);
        $this->assertChaveLivre('x');
        $this->assertChaveLivre('y');
    }

    // -----------------------------------------------------------------------
    // Cenário 10 — lista vazia
    // -----------------------------------------------------------------------

    public function test_lista_vazia_lanca_invalid_argument_exception_sem_executar_o_callback(): void
    {
        // Arrange
        $executou = false;

        // Act
        try {
            PzMonitor::tryLockMany([], function () use (&$executou): void {
                $executou = true;
            });
            $this->fail('Deveria ter lançado InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            // Assert
            $this->assertSame('uniqueProcessKeys must not be empty.', $e->getMessage());
        }

        $this->assertFalse($executou);
    }
}
