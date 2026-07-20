<?php

declare(strict_types=1);

namespace Puzl\PzMonitor\Tests\Integration;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Puzl\PzMonitor\PzMonitor;
use Throwable;

/**
 * A promessa central do pacote: exclusão mútua **entre processos**, contra um
 * backend compartilhado. A suíte unitária roda no store `array` e num único
 * processo — ela passaria verde mesmo com um store que não exclui nada. Este
 * é o único teste que cobre essa lacuna.
 *
 * Pula automaticamente quando não há Redis alcançável (ambiente local sem o
 * serviço); no CI o serviço está de pé e o teste roda pra valer.
 */
final class RedisMutualExclusionTest extends TestCase
{
    private const COMPETITORS = 8;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Predis\Client::class)) {
            $this->markTestSkipped('predis/predis não instalado.');
        }

        /** @var callable(): Container $makeContainer */
        $makeContainer = require __DIR__.'/redis_container.php';
        $this->container = $makeContainer();

        try {
            $this->container->make('redis')->connection()->ping();
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis indisponível: '.$e->getMessage());
        }
    }

    public function test_apenas_um_processo_entra_na_secao_critica_entre_processos_concorrentes(): void
    {
        // Arrange — chave única por execução: o Redis do CI é reaproveitado
        // entre testes e uma sobra de execução anterior falsearia o resultado.
        $chave = 'exclusao-'.bin2hex(random_bytes(8));
        $largada = microtime(true) + 1.5;

        // Act — N processos separados disputam a mesma chave no mesmo instante
        $resultados = $this->dispararConcorrentes($chave, $largada, hold: 1.0);

        // Assert
        $this->assertCount(self::COMPETITORS, $resultados);
        $this->assertSame(
            1,
            count(array_keys($resultados, 'ACQUIRED', strict: true)),
            'Mais de um processo entrou na seção crítica: '.implode(',', $resultados),
        );
        $this->assertSame(
            self::COMPETITORS - 1,
            count(array_keys($resultados, 'BUSY', strict: true)),
        );
    }

    public function test_chave_fica_livre_para_o_proximo_depois_que_o_dono_termina(): void
    {
        // Arrange
        $chave = 'liberacao-'.bin2hex(random_bytes(8));
        $largada = microtime(true) + 1.0;

        // Act — primeira disputa resolve, segunda acontece com a chave já solta
        $this->dispararConcorrentes($chave, $largada, hold: 0.2);
        $segundaRodada = $this->dispararConcorrentes($chave, microtime(true) + 1.0, hold: 0.2);

        // Assert
        $this->assertSame(
            1,
            count(array_keys($segundaRodada, 'ACQUIRED', strict: true)),
            'A chave não voltou a ficar disponível: '.implode(',', $segundaRodada),
        );
    }

    /**
     * Dispara os processos concorrentes e coleta a saída de cada um.
     *
     * @return string[]
     */
    private function dispararConcorrentes(string $chave, float $largada, float $hold): array
    {
        $script = __DIR__.'/competitor.php';
        $processos = [];

        for ($i = 0; $i < self::COMPETITORS; $i++) {
            $comando = sprintf(
                '%s %s %s %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($script),
                escapeshellarg($chave),
                escapeshellarg((string) $largada),
                escapeshellarg((string) $hold),
            );

            $processo = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($processo, 'Falha ao disparar o processo concorrente.');

            $processos[] = [$processo, $pipes];
        }

        $resultados = [];

        foreach ($processos as [$processo, $pipes]) {
            $saida = trim((string) stream_get_contents($pipes[1]));
            $erro = trim((string) stream_get_contents($pipes[2]));
            array_map(fclose(...), $pipes);
            proc_close($processo);

            $this->assertSame('', $erro, 'Processo concorrente falhou: '.$erro);
            $resultados[] = $saida;
        }

        return $resultados;
    }
}
