<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Unit;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Config;
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;
use Puzl\PzMonitor\PzMonitor;
use Puzl\PzMonitor\Tests\PzMonitorTestCase;
use ReflectionProperty;

/**
 * Precedência dos tempos: argumento da chamada > config > padrão do pacote.
 */
final class PzMonitorTimeDefaultsTest extends PzMonitorTestCase
{
    // -----------------------------------------------------------------------
    // TTL do lock
    // -----------------------------------------------------------------------

    public function test_ttl_omitido_usa_o_valor_do_arquivo_de_configuracao(): void
    {
        // Arrange
        Config::set('pzmonitor.lock_ttl', 900);

        // Act
        $lock = PzMonitor::tryAcquire('rotina-longa');

        // Assert
        $this->assertSame(900, $this->ttlDoLock($lock));
        $lock->release();
    }

    public function test_ttl_passado_na_chamada_prevalece_sobre_a_configuracao(): void
    {
        // Arrange
        Config::set('pzmonitor.lock_ttl', 900);

        // Act
        $lock = PzMonitor::tryAcquire('rotina-curta', 30);

        // Assert
        $this->assertSame(30, $this->ttlDoLock($lock));
        $lock->release();
    }

    public function test_sem_configuracao_o_ttl_cai_no_padrao_do_pacote(): void
    {
        // Arrange: app que publicou uma config antiga, sem a chave nova
        Config::set('pzmonitor.lock_ttl', null);

        // Act
        $lock = PzMonitor::tryAcquire('rotina-sem-config');

        // Assert
        $this->assertSame(300, $this->ttlDoLock($lock));
        $lock->release();
    }

    // -----------------------------------------------------------------------
    // Tempo de espera
    // -----------------------------------------------------------------------

    public function test_espera_omitida_usa_o_valor_do_arquivo_de_configuracao(): void
    {
        // Arrange: config zerada faz o perdedor desistir na primeira tentativa
        Config::set('pzmonitor.wait_timeout', 0);
        $chave = 'espera-por-config';
        $dono = PzMonitor::tryAcquire($chave);

        // Act + Assert
        $this->expectException(PzMonitorTimeoutException::class);

        try {
            PzMonitor::acquire($chave);
        } finally {
            $dono->release();
        }
    }

    public function test_espera_passada_na_chamada_prevalece_sobre_a_configuracao(): void
    {
        // Arrange: config generosa, argumento zerado — se o argumento não
        // vencesse, este teste levaria 60s em vez de estourar de imediato.
        Config::set('pzmonitor.wait_timeout', 60);
        $chave = 'espera-por-argumento';
        $dono = PzMonitor::tryAcquire($chave);

        // Act + Assert
        $this->expectException(PzMonitorTimeoutException::class);

        try {
            PzMonitor::acquire($chave, 0);
        } finally {
            $dono->release();
        }
    }

    /**
     * O TTL fica em `Illuminate\Cache\Lock::$seconds` (protected): reflexão é
     * a única forma de observar o valor efetivamente entregue ao framework.
     */
    private function ttlDoLock(Lock $lock): int
    {
        return (new ReflectionProperty($lock, 'seconds'))->getValue($lock);
    }
}
