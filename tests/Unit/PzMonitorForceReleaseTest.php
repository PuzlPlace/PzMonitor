<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;

final class PzMonitorForceReleaseTest extends PzMonitorTestCase
{
    // -----------------------------------------------------------------------
    // Cenário 11 — destrave administrativo
    // -----------------------------------------------------------------------

    public function test_force_release_destrava_chave_presa_por_outro_dono(): void
    {
        // Arrange — A adquire e "morre": o handle é abandonado sem release
        $chave = 'processo-travado-11';
        PzMonitor::tryAcquire($chave);

        // Act — operador destrava a chave
        PzMonitor::forceRelease($chave);

        // Assert — nova aquisição funciona imediatamente
        $this->assertTrue(PzMonitor::tryAcquire($chave)->release());
    }

    public function test_force_release_de_chave_livre_nao_lanca_excecao(): void
    {
        // Arrange
        $chave = 'processo-inexistente-11';

        // Act
        PzMonitor::forceRelease($chave);

        // Assert
        $this->assertTrue(PzMonitor::tryAcquire($chave)->release());
    }
}
