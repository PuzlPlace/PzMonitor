<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Features\Bootstrap;

use PHPUnit\Framework\TestCase;
use Puzl\PzMonitor\Laravel\PzMonitorServiceProvider;

final class PackageBootstrapTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Configuração publicável
    // -----------------------------------------------------------------------

    public function test_arquivo_de_configuracao_retorna_as_chaves_esperadas(): void
    {
        $config = require $this->packagePath('config').'/pzmonitor.php';

        // Presença por chave (e não igualdade da lista): chave nova não pode
        // quebrar o teste — só a remoção de uma chave publicada quebra.
        $this->assertIsArray($config);
        $this->assertArrayHasKey('store', $config);
        $this->assertArrayHasKey('prefix', $config);
        $this->assertArrayHasKey('wait_timeout', $config);
        $this->assertArrayHasKey('lock_ttl', $config);
        $this->assertNull($config['store']);
        $this->assertSame('pzmonitor:', $config['prefix']);
        $this->assertSame(60, $config['wait_timeout']);
        $this->assertSame(300, $config['lock_ttl']);
    }

    // -----------------------------------------------------------------------
    // Auto-discovery do Laravel
    // -----------------------------------------------------------------------

    public function test_composer_declara_o_provider_no_auto_discovery(): void
    {
        $manifest = json_decode(
            (string) file_get_contents($this->packagePath().'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertContains(
            PzMonitorServiceProvider::class,
            $manifest['extra']['laravel']['providers'],
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function packagePath(string $relative = ''): string
    {
        $base = realpath(__DIR__.'/../../..');

        return $relative === '' ? $base : realpath($base.'/'.$relative);
    }
}
