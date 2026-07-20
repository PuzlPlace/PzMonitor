<?php

declare(strict_types=1);

namespace Puzl\PzMonitor;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;

/**
 * Lock distribuído sobre `Cache::lock` do Laravel.
 *
 * Wrapper fino e 100% estático: aquisição atômica, owner token por
 * instância e liberação owner-safe são todos do framework — nenhum
 * primitivo de concorrência é reimplementado aqui.
 *
 * Os parâmetros de tempo (`$waitTimeoutInSeconds` e `$lockTtlInSeconds`)
 * seguem três níveis de precedência: argumento da chamada > valor em
 * `config/pzmonitor.php` (`wait_timeout` / `lock_ttl`) > padrão do pacote
 * (60s e 300s). Passar `null` — ou omitir — cai para a config.
 *
 * Limites conhecidos (deliberados):
 * - **TTL**: é rede de segurança contra processo morto, não watchdog. Se a
 *   seção crítica passar do TTL, o lock expira e outro processo entra —
 *   dimensione `$lockTtlInSeconds` pelo pior caso da operação. Como o valor
 *   adequado depende da rotina (e não da aplicação), o padrão de config é só
 *   um piso: rotina longa deve passar o TTL explícito na chamada.
 * - **Não reentrante**: chamar um método desta classe aninhado na mesma
 *   chave bloqueia até o timeout (ou falha imediata em `tryLock`).
 * - **Cache compartilhado**: a exclusão só vale entre processos que usam o
 *   mesmo store. Em produção, aponte para um backend compartilhado (Redis);
 *   stores por processo (`array`, `file` local) não excluem entre máquinas.
 */
final class PzMonitor
{
    /**
     * Seção crítica com espera. Executa o callback com exclusividade e
     * libera o lock SEMPRE — inclusive quando o callback lança exceção.
     *
     * `null` nos tempos cai para a config (`wait_timeout` / `lock_ttl`).
     *
     * @throws PzMonitorTimeoutException espera esgotada; callback não executa
     */
    public static function lock(
        string $uniqueProcessKey,
        callable $callback,
        ?int $waitTimeoutInSeconds = null,
        ?int $lockTtlInSeconds = null,
    ): mixed {
        $lock = self::makeLock($uniqueProcessKey, $lockTtlInSeconds);

        try {
            // block() com callback adquire, executa e libera em finally interno.
            return $lock->block(self::waitTimeout($waitTimeoutInSeconds), $callback);
        } catch (LockTimeoutException $e) {
            // Perdedor do timeout NUNCA libera o lock do dono.
            throw new PzMonitorTimeoutException($uniqueProcessKey, $e);
        }
    }

    /**
     * Seção crítica com falha rápida: chave ocupada lança busy imediata.
     *
     * `null` no TTL cai para a config (`lock_ttl`).
     *
     * @throws PzMonitorBusyException chave ocupada; callback não executa
     */
    public static function tryLock(
        string $uniqueProcessKey,
        callable $callback,
        ?int $lockTtlInSeconds = null,
    ): mixed {
        $lock = self::tryAcquire($uniqueProcessKey, $lockTtlInSeconds);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Seção crítica multi-chave, all-or-nothing. Deduplica as chaves e
     * adquire em ordem lexicográfica fixa (anti-deadlock entre lotes
     * concorrentes). Qualquer chave ocupada: libera as já adquiridas
     * (rollback) e lança busy nomeando a chave conflitante — nunca fica
     * aquisição órfã e o callback nunca executa sem o conjunto completo.
     *
     * O mesmo TTL vale para todas as chaves: dimensione pelo pior caso do
     * lote inteiro, não pelo de uma chave. `null` cai para a config
     * (`lock_ttl`) — que costuma ser curto demais para lote.
     *
     * @param  string[]  $uniqueProcessKeys
     *
     * @throws InvalidArgumentException lista vazia
     * @throws PzMonitorBusyException alguma chave ocupada
     */
    public static function tryLockMany(
        array $uniqueProcessKeys,
        callable $callback,
        ?int $lockTtlInSeconds = null,
    ): mixed {
        if ($uniqueProcessKeys === []) {
            throw new InvalidArgumentException('uniqueProcessKeys must not be empty.');
        }

        // Cópia local: dedup e sort nunca tocam o array do caller.
        $keys = array_values(array_unique($uniqueProcessKeys));
        sort($keys, SORT_STRING);

        /** @var Lock[] $acquired */
        $acquired = [];

        try {
            foreach ($keys as $key) {
                $acquired[] = self::tryAcquire($key, $lockTtlInSeconds);
            }

            return $callback();
        } finally {
            // Cobre os dois caminhos: rollback da aquisição parcial e
            // liberação normal após o callback (sucesso ou exceção).
            foreach ($acquired as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Aquisição crua bloqueante. O caller DEVE liberar em finally:
     * `$lock->release()`. Não reentrante; TTL é a rede de segurança
     * contra processo morto — dimensione pelo pior caso da operação.
     *
     * `null` nos tempos cai para a config (`wait_timeout` / `lock_ttl`).
     *
     * @throws PzMonitorTimeoutException espera esgotada (lock do dono fica intacto)
     */
    public static function acquire(
        string $uniqueProcessKey,
        ?int $waitTimeoutInSeconds = null,
        ?int $lockTtlInSeconds = null,
    ): Lock {
        $lock = self::makeLock($uniqueProcessKey, $lockTtlInSeconds);

        try {
            $lock->block(self::waitTimeout($waitTimeoutInSeconds));
        } catch (LockTimeoutException $e) {
            // Perdedor do timeout NUNCA libera o lock do dono.
            throw new PzMonitorTimeoutException($uniqueProcessKey, $e);
        }

        return $lock;
    }

    /**
     * Aquisição crua com falha rápida. O caller DEVE liberar em finally:
     * `$lock->release()`. `null` no TTL cai para a config (`lock_ttl`).
     *
     * @throws PzMonitorBusyException chave ocupada
     */
    public static function tryAcquire(string $uniqueProcessKey, ?int $lockTtlInSeconds = null): Lock
    {
        $lock = self::makeLock($uniqueProcessKey, $lockTtlInSeconds);

        if (! $lock->get()) {
            throw new PzMonitorBusyException($uniqueProcessKey);
        }

        return $lock;
    }

    /**
     * Liberação administrativa: ignora o dono. Uso EXCLUSIVO de
     * operação/suporte para destravar chave presa por processo morto
     * antes do TTL. Nunca chamar em fluxo de negócio — libera a chave
     * mesmo com o dono legítimo ainda dentro da seção crítica.
     */
    public static function forceRelease(string $uniqueProcessKey): void
    {
        self::makeLock($uniqueProcessKey, 0)->forceRelease();
    }

    /**
     * Resolve o tempo de espera: argumento > config > padrão do pacote.
     */
    private static function waitTimeout(?int $waitTimeoutInSeconds): int
    {
        // `??` e não o default do Config::get: config publicada com a chave
        // presente e nula devolve null, e o default do get() nunca entraria.
        return $waitTimeoutInSeconds ?? (int) (Config::get('pzmonitor.wait_timeout') ?? 60);
    }

    /**
     * Monta o lock do framework já com o prefixo de config aplicado.
     * TTL `null` cai para a config (`lock_ttl`) e, na ausência dela, 300s.
     */
    private static function makeLock(string $uniqueProcessKey, ?int $lockTtlInSeconds): Lock
    {
        $lockTtlInSeconds ??= (int) (Config::get('pzmonitor.lock_ttl') ?? 300);

        // Facade Config (e não o helper config()): o helper vive no pacote
        // Foundation, que este pacote não requer.
        /** @var string|null $store */
        $store = Config::get('pzmonitor.store');
        /** @var string $prefix */
        $prefix = Config::get('pzmonitor.prefix') ?? 'pzmonitor:';

        return Cache::store($store)->lock($prefix.$uniqueProcessKey, $lockTtlInSeconds);
    }
}
