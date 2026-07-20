Você é um orquestrador responsável por executar **todas as tasks** de uma pasta,
de forma **sequencial**, cada uma em um **agente com contexto limpo**.

## Entrada

O programador chama este comando passando o caminho de uma pasta na frente:

```
/execute-all-tasks <caminho-da-pasta>
```

Dentro dessa pasta existem 1 ou mais tasks no padrão:

```
1_task.md
2_task.md
3_task.md
...
N_task.md
```

## Comportamento

<critical>Cada task DEVE ser executada por um agente (subagent) com contexto limpo — um agente novo por task. É como se, para cada task, o contexto fosse limpo (`/clear`) e o comando `/execute-task` fosse chamado do zero.</critical>

<critical>Cada agente DEVE usar o comando existente `.cursor/commands/execute-task.md` como instrução para executar a sua task. Não reimplemente a lógica de execução aqui — delegue ao `execute-task`.</critical>

### Etapas

1. **Listar as tasks**: liste os arquivos da pasta informada que casam com o padrão `^[0-9]+_task\.md$`.

2. **Ordenar numericamente**: ordene pelo número do prefixo em ordem **crescente** (`1_task.md`, `2_task.md`, ..., `10_task.md`). Use ordenação numérica, não lexicográfica.

3. **Executar sequencialmente**: para cada task, na ordem, faça **uma de cada vez** (nunca em paralelo):
   - Lance um **agente novo** (Agent / Task tool, `subagent_type: general-purpose`) com contexto limpo.
   - O prompt do agente deve instruí-lo a:
     - Ler e seguir integralmente as instruções de `.cursor/commands/execute-task.md`.
     - Tratar o arquivo `<pasta>/<N>_task.md` como a task a ser implementada.
     - Implementar a task completamente, rodar os testes e só considerar concluída com **100% dos testes passando**.
   - **Aguarde o agente terminar** antes de iniciar o próximo (execução estritamente sequencial).

4. **Continuidade**: prossiga para a próxima task somente após a anterior estar concluída. Se uma task falhar, **pare** e reporte qual task falhou e o motivo, sem seguir para as seguintes.

### Modelo de prompt para cada agente

```
Você executará UMA task com contexto limpo.

Siga integralmente as instruções do comando em `.cursor/commands/execute-task.md`.

A task a ser implementada é o arquivo: <pasta>/<N>_task.md

Implemente a task por completo seguindo os padrões do projeto, rode os testes e
só finalize quando 100% dos testes estiverem passando. Ao concluir, marque a task
como completa conforme o execute-task.
```

## Saída final

Ao terminar todas as tasks, apresente um resumo:

```
Tasks executadas: [N]
- 1_task.md  → [OK | FALHOU]
- 2_task.md  → [OK | FALHOU]
...
```

<critical>NUNCA execute as tasks em paralelo. Sempre uma de cada vez, em ordem numérica crescente.</critical>
<critical>NUNCA execute as tasks no contexto atual. SEMPRE delegue cada task a um agente novo com contexto limpo.</critical>
