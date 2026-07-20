# PzMonitor

Lock distribuído para Laravel sobre `Cache::lock`, com API de monitor por chave
única: uma chave, uma seção crítica, liberação garantida.

Wrapper fino e 100% estático — atomicidade, owner token e liberação owner-safe são
todos do framework. Nenhum primitivo de concorrência é reimplementado no pacote.

## Documentação

> **[Abrir documentação completa no navegador →](https://puzlplace.github.io/PzMonitor/)**
>
> Site estático (GitHub Pages) com visão geral, API dos 6 métodos, exceções,
> configuração, limites e integração Laravel.
> Fonte: [`docs/index.html`](docs/index.html).

## Requisitos

- PHP `^8.1`
- `illuminate/cache` e `illuminate/support` `^10|^11|^12`
- Backend de cache **compartilhado** (Redis) em produção — ver [Limites e avisos](#limites-e-avisos)

---

## Instalação

O pacote é público, mas **não está no Packagist** — é distribuído pelo próprio
repositório Git. São dois passos.

### 1. Declare o repositório

No `composer.json` da aplicação, no mesmo nível de `require`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/PuzlPlace/PzMonitor.git"
    }
]
```

Já tem outros pacotes `Pz*`? É só acrescentar mais um objeto à lista existente.

### 2. Instale por versão

```bash
composer require puzl/pzmonitor:^1.0
```

Resultado no `require`:

```json
"puzl/pzmonitor": "^1.0"
```

O **auto-discovery** do Laravel registra o `PzMonitorServiceProvider` automaticamente.
Nenhuma configuração manual é obrigatória.

### Escolhendo a constraint

Cada release publicado é uma tag semver — veja as
[Releases](https://github.com/PuzlPlace/PzMonitor/releases).

| Constraint | Resolve para |
|------------|--------------|
| `^1.0` | última `1.x`. **Recomendado**: pega correções e recursos novos, nunca uma major com breaking change. |
| `~1.2.0` | última `1.2.x`. Só correções de patch, sem subir de minor. |
| `v1.2.3` | exatamente essa tag. Reprodutível ao extremo; nenhuma correção chega sozinha. |
| `dev-production` | topo da branch, sem versão. Modo legado — sem rastreabilidade, evite. |

Atualizar depois:

```bash
composer update puzl/pzmonitor
```

### Versionamento

Não há versão escrita em lugar nenhum deste pacote — o `composer.json` **não tem**
campo `version` de propósito. A fonte é a tag do Git.

Toda entrega na branch `production` que passa no CI gera automaticamente a próxima
tag *patch* e o release correspondente, com notas montadas a partir dos commits
(`.github/workflows/tests.yml`, job `Release`). Mudanças de *minor* e *major* são
deliberadas: criam-se à mão uma vez, e o autoincremento continua a partir delas.

---

## Uso

Todos os métodos são estáticos e vivem na classe `Puzl\PzMonitor\PzMonitor`.

### `PzMonitor::lock()` — seção crítica com espera

Espera o lock ficar livre (até `$waitTimeoutInSeconds`), executa o callback com
exclusividade e libera **sempre** — inclusive quando o callback lança exceção.

```php
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;
use Puzl\PzMonitor\PzMonitor;

try {
    $nota = PzMonitor::lock(
        uniqueProcessKey: "emissao-nfe:{$pedidoId}",
        callback: fn () => $this->emitirNfe($pedidoId),
        waitTimeoutInSeconds: 60,   // omitido = config `wait_timeout` (padrão 60)
        lockTtlInSeconds: 300,      // omitido = config `lock_ttl` (padrão 300)
    );
} catch (PzMonitorTimeoutException $e) {
    // Espera esgotada: o callback NÃO executou e o lock do dono ficou intacto.
    report($e);
}
```

O retorno de `lock()` é o retorno do callback.

### `PzMonitor::tryLock()` — falha rápida

Não espera: se a chave estiver ocupada, lança imediatamente.

```php
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;

try {
    PzMonitor::tryLock(
        uniqueProcessKey: "sincronizacao-estoque:{$lojaId}",
        callback: fn () => $this->sincronizarEstoque($lojaId),
        lockTtlInSeconds: 300,      // omitido = config `lock_ttl` (padrão 300)
    );
} catch (PzMonitorBusyException $e) {
    // Já existe outro processo na mesma chave; o callback NÃO executou.
    return response()->json(['message' => $e->getMessage()], 409);
}
```

### `PzMonitor::tryLockMany()` — multi-chave all-or-nothing

Deduplica as chaves e adquire em ordem lexicográfica fixa (anti-deadlock entre lotes
concorrentes). Se qualquer chave estiver ocupada, as já adquiridas são liberadas
(rollback) e a exceção nomeia a chave conflitante — o callback nunca executa sem o
conjunto completo.

#### O problema que ele resolve

Os outros métodos travam **uma** chave. Mas há operações que tocam **duas ou mais
entidades ao mesmo tempo**, e travar só uma não protege nada.

O caso canônico é a transferência entre contas: `transferir(A → B)` debita A e credita
B. Travando apenas a conta A, nada impede uma transferência `X → B` simultânea de
bagunçar o saldo de B. A operação precisa das **duas** chaves ao mesmo tempo.

A tentativa ingênua é aninhar — e é exatamente aqui que nasce o deadlock:

```php
// NÃO faça isso.
PzMonitor::tryLock("conta:{$origemId}", function () use ($destinoId) {
    PzMonitor::tryLock("conta:{$destinoId}", fn () => $this->transferir(...));
});
```

#### O deadlock que ele evita

Duas transferências simultâneas, em sentidos opostos:

```
Processo 1: transferir(conta:5 → conta:9)     Processo 2: transferir(conta:9 → conta:5)
  trava conta:5  ✓                              trava conta:9  ✓
  quer conta:9 ... ocupada pelo P2               quer conta:5 ... ocupada pelo P1
```

Cada um segura o que o outro precisa. Abraço mortal: nenhum dos dois solta, e ambos
ficam presos até o TTL expirar.

**A solução é a ordem fixa.** O `tryLockMany` ordena as chaves lexicograficamente antes
de adquirir. Assim os dois processos, mesmo pedindo em sentidos opostos, pedem na
**mesma ordem**:

```
Processo 1: pede [conta:5, conta:9]           Processo 2: pede [conta:5, conta:9]
  trava conta:5  ✓                              tenta conta:5 → OCUPADA → falha limpa
  trava conta:9  ✓
  transfere
```

Um ganha; o outro leva `PzMonitorBusyException` **na hora**, sem ter travado nada.
Deadlock impossível por construção — o `sort()` não é estética, é o mecanismo.

#### All-or-nothing: o rollback

O callback só executa com o **conjunto completo** na mão:

```
pede [conta:5, conta:9, conta:12]
  conta:12  ✓ adquirida     // ordem de string: "conta:12" < "conta:5" < "conta:9"
  conta:5   ✓ adquirida
  conta:9   ✗ ocupada  →  libera conta:12 e conta:5, lança busy nomeando "conta:9"
```

Sem esse rollback sobrariam duas chaves órfãs presas até o TTL, bloqueando outras
operações à toa. O `finally` do método cobre os dois caminhos: o rollback da aquisição
parcial **e** a liberação normal após o callback (inclusive quando o callback lança
exceção).

> **A ordem não é a que você escreveu.** No exemplo acima `conta:12` vem antes de
> `conta:5` porque a comparação é **de string**, não numérica. Isso não afeta a
> correção — o que importa é que todos os processos usem o mesmo critério — mas explica
> por que a ordem de aquisição difere da ordem do array.

#### Deduplicação

`["conta:5", "conta:5"]` significaria travar a mesma chave duas vezes. Como o lock
**não é reentrante**, a segunda tentativa encontraria a chave ocupada… pelo próprio
processo. Auto-deadlock. O `array_unique` elimina isso — útil quando as chaves vêm de
um loop e origem e destino podem coincidir (transferência de uma conta para ela mesma).

#### Exemplo completo

```php
use InvalidArgumentException;
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;

try {
    PzMonitor::tryLockMany(
        uniqueProcessKeys: ["conta:{$origemId}", "conta:{$destinoId}"],
        callback: fn () => $this->transferir($origemId, $destinoId, $valor),
        lockTtlInSeconds: 300,      // omitido = config `lock_ttl` (padrão 300)
    );
} catch (PzMonitorBusyException $e) {
    // $e->uniqueProcessKey → a chave que estava ocupada.
    report($e);
} catch (InvalidArgumentException $e) {
    // Lista de chaves vazia.
    report($e);
}
```

> **TTL do lote.** Um único TTL vale para **todas** as chaves, e o relógio de cada uma
> começa a correr no momento em que ela é adquirida — não quando o callback começa. Num
> lote de 50 chaves cujo callback leva 4 minutos, um TTL de 300 s fica apertado: as
> primeiras chaves já gastaram tempo esperando as outras 49 serem travadas. Dimensione
> pelo **tempo total do lote**, não pelo tempo de processar um item — e como o padrão da
> config raramente basta para lote, este é o caso típico de passar `lockTtlInSeconds`
> explícito.

#### Quando usar cada método

| Situação | Método |
|----------|--------|
| Uma rotina, uma chave | `tryLock()` / `lock()` |
| Uma operação que toca N entidades e precisa de todas | `tryLockMany()` |
| Precisa esperar em vez de falhar | `lock()` — não há versão bloqueante multi-chave |

> **Por que não existe um `lockMany()` bloqueante.** É deliberado. Esperar por múltiplas
> chaves reintroduz a chance de deadlock que a falha rápida elimina: dois lotes com
> interseção parcial podem esperar um pelo outro indefinidamente. Se a operação é
> importante, o padrão é falhar rápido e tentar de novo depois (retry na fila), não
> esperar segurando locks.

### `PzMonitor::acquire()` / `PzMonitor::tryAcquire()` — handle cru

Retornam o `Illuminate\Contracts\Cache\Lock` do framework, sem callback. O caller
**deve** liberar em `finally`. `acquire()` espera; `tryAcquire()` falha rápido.

```php
use Puzl\PzMonitor\Exception\PzMonitorTimeoutException;
use Puzl\PzMonitor\PzMonitor;

try {
    $lock = PzMonitor::acquire(
        uniqueProcessKey: "fechamento-caixa:{$caixaId}",
        waitTimeoutInSeconds: 60,   // omitido = config `wait_timeout` (padrão 60)
        lockTtlInSeconds: 300,      // omitido = config `lock_ttl` (padrão 300)
    );
} catch (PzMonitorTimeoutException $e) {
    report($e);

    return;
}

try {
    $this->fecharCaixa($caixaId);
} finally {
    $lock->release();
}
```

```php
use Puzl\PzMonitor\Exception\PzMonitorBusyException;
use Puzl\PzMonitor\PzMonitor;

try {
    $lock = PzMonitor::tryAcquire("importacao-xml:{$arquivoId}", lockTtlInSeconds: 300);
} catch (PzMonitorBusyException $e) {
    report($e);

    return;
}

try {
    $this->importar($arquivoId);
} finally {
    $lock->release();
}
```

> Prefira `lock()` / `tryLock()` / `tryLockMany()`: eles já liberam o lock em
> `finally` internamente. Use o handle cru só quando a seção crítica não couber
> em um callback.

### `PzMonitor::forceRelease()` — operação/suporte apenas

Libera a chave **ignorando o dono**. Existe para destravar chave presa por processo
morto antes do TTL expirar. Nunca chame em fluxo de negócio: se o dono legítimo ainda
estiver dentro da seção crítica, a exclusão mútua é quebrada.

```php
use Puzl\PzMonitor\PzMonitor;

// Ex.: comando artisan de suporte, executado por operação.
PzMonitor::forceRelease("emissao-nfe:{$pedidoId}");
```

---

## Configuração

Para publicar o arquivo de configuração:

```bash
php artisan vendor:publish --tag=pzmonitor-config
```

```php
// config/pzmonitor.php
return [
    'store'        => env('PZMONITOR_STORE'),                // null = store padrão da app
    'prefix'       => env('PZMONITOR_PREFIX', 'pzmonitor:'), // prefixo de toda chave de lock
    'wait_timeout' => (int) env('PZMONITOR_WAIT_TIMEOUT', 60),
    'lock_ttl'     => (int) env('PZMONITOR_LOCK_TTL', 300),
];
```

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `PZMONITOR_STORE` | `null` | Store de cache dos locks. `null` usa o store padrão da aplicação. Em produção deve resolver para um backend compartilhado (Redis). |
| `PZMONITOR_PREFIX` | `pzmonitor:` | Prefixo aplicado a toda chave, garantindo que as chaves do PzMonitor nunca colidam com outras chaves da aplicação. |
| `PZMONITOR_WAIT_TIMEOUT` | `60` | Segundos que `lock()` e `acquire()` esperam antes de lançar timeout. |
| `PZMONITOR_LOCK_TTL` | `300` | Segundos até o cache expirar a chave sozinho, sem `release()`. |

### Precedência dos tempos

Os parâmetros `$waitTimeoutInSeconds` e `$lockTtlInSeconds` resolvem em três níveis:

**argumento da chamada > `config/pzmonitor.php` > padrão do pacote (60s / 300s)**

Omitir o argumento — ou passar `null` — cai para a config. Passar um valor
explícito vence a config, inclusive dentro de uma app que a customizou:

```php
PzMonitor::lock('fechamento-mensal', $callback);                       // usa a config
PzMonitor::lock('fechamento-mensal', $callback, lockTtlInSeconds: 1800); // vence a config
```

> **O `lock_ttl` da config é um piso, não uma solução.** O TTL adequado depende da
> duração de *cada rotina*, não da aplicação: fechamento de mês e envio de e-mail
> não querem o mesmo número. Rotina longa deve passar o TTL explícito na chamada.

---

## Limites e avisos

- **TTL é rede de segurança, não watchdog.** O TTL (padrão `300`s) protege contra
  processo morto (`kill -9`, OOM). Se a seção crítica passar do TTL, o lock expira e
  outro processo entra — **dimensione `$lockTtlInSeconds` pelo pior caso da operação**.
- **Não reentrante.** Chamar um método desta classe aninhado na mesma chave bloqueia
  até o timeout (`lock`/`acquire`) ou falha imediatamente (`tryLock`/`tryAcquire`).
- **Exclusão real exige cache compartilhado.** A exclusão só vale entre processos que
  usam o mesmo store. `file` exclui apenas dentro de uma máquina; `array` apenas dentro
  do processo. Em produção, aponte para Redis.
- **Lock de eficiência, não de correção absoluta.** Sob failover de Redis
  single-instance, dupla aquisição é raramente possível
  ([Kleppmann](https://martin.kleppmann.com/2016/02/08/how-to-do-distributed-locking.html)).
  Aceitável para os usos atuais; fluxos correção-críticos exigem outra abordagem.

### Fora do escopo (upgrade path, sem prazo)

Watchdog de renovação de TTL, reentrância por contador de aquisições, fencing tokens /
Redlock multi-nó, condition variables (`Wait`/`Pulse`) e métricas/eventos não fazem
parte desta versão.

---

## Exceptions

Todas em `Puzl\PzMonitor\Exception\`. Telas e logs da aplicação consumidora dependem desses textos.

| Exception | Quando | Mensagem |
|-----------|--------|----------|
| `PzMonitorException` | Base abstrata da família; estende `\Exception` e expõe a propriedade readonly `uniqueProcessKey`. | — |
| `PzMonitorBusyException` | `tryLock`, `tryLockMany` e `tryAcquire` com a chave ocupada. | `Already exists process. Process {chave}` |
| `PzMonitorTimeoutException` | `lock` e `acquire` com a espera esgotada. `getPrevious()` devolve o `LockTimeoutException` do framework. | `Maximum wait time reached. Process {chave}` |

```php
use Puzl\PzMonitor\Exception\PzMonitorException;
use Puzl\PzMonitor\PzMonitor;

try {
    PzMonitor::tryLock('minha-chave', $callback);
} catch (PzMonitorException $e) {
    // Captura busy e timeout de uma vez.
    logger()->warning($e->getMessage(), ['key' => $e->uniqueProcessKey]);
}
```

Além dessas, `tryLockMany()` lança `InvalidArgumentException` quando a lista de chaves
é vazia.

---

## Testes

```bash
composer test
composer test:coverage
```

As suítes `Unit` e `Features` rodam no driver de cache `array` — sem Redis,
Docker ou qualquer infraestrutura externa.

A suíte `Integration` é a única que verifica a promessa central do pacote —
exclusão mútua **entre processos** — disparando processos PHP concorrentes
contra um Redis real. Ela **se pula sozinha** quando não há Redis alcançável,
então localmente basta subir um:

```bash
docker run -d --rm -p 6379:6379 redis:7-alpine
composer test
```

Variáveis: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`. No CI o serviço está de
pé e o job falha se a suíte for pulada — teste que não roda não protege nada.
