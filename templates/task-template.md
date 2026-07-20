# Task [Número]: [Título da Tarefa]

## Informações Gerais

**Status**: 📦 Pronta para Iniciar
**Prioridade**: [Alta/Média/Baixa]
**Dependências**: [Lista de tasks ou "Nenhuma"]
**Assignee**: [Nome ou "Não atribuído"]

---

## Objetivo

[Descrição clara e concisa do objetivo desta tarefa e o valor que ela entrega]

---

## Contexto

[Informações de contexto necessárias para entender a tarefa, incluindo referências ao PRD/Tech Spec]

**Referências**:
- PRD: `tasks/prd-[nome-funcionalidade]/prd.md` - Seção [X]
- Tech Spec: `tasks/prd-[nome-funcionalidade]/techspec.md` - Seção [X]

---

## Escopo

### O que ESTÁ no escopo:
- ✅ [Item 1]
- ✅ [Item 2]
- ✅ [Item 3]

### O que NÃO está no escopo:
- ❌ [Item 1]
- ❌ [Item 2]

---

## Subtarefas

### 1. [Subtarefa 1]
**Descrição**: [Descrição detalhada]
**Arquivos afetados**:
- `path/to/file1.php`
- `path/to/file2.php`

**Implementação**:
```php
// Exemplo de código ou estrutura esperada
```

---

### 2. [Subtarefa 2]
**Descrição**: [Descrição detalhada]
**Arquivos afetados**:
- `path/to/file3.php`

**Implementação**:
```php
// Exemplo de código ou estrutura esperada
```

---

### 3. Testes Unitários
**Descrição**: Criar testes unitários para validar a lógica implementada

**Arquivos de teste**:
- `path/to/__tests__/file1.test.php`
- `path/to/__tests__/file2.test.php`

**Casos de teste obrigatórios**:
1. **[Nome do teste 1]**
   - **Cenário**: [Descrição]
   - **Expectativa**: [O que deve acontecer]

2. **[Nome do teste 2]**
   - **Cenário**: [Descrição]
   - **Expectativa**: [O que deve acontecer]

**Cobertura mínima**: [X%] das linhas de código

---

### 4. Testes de Integração
**Descrição**: Criar testes de integração para validar a comunicação entre componentes

**Arquivos de teste**:
- `path/to/__tests__/integration.test.php`

**Cenários de teste obrigatórios**:
1. **[Nome do cenário 1]**
   - **Fluxo**: [Passo a passo]
   - **Expectativa**: [Resultado esperado]

2. **[Nome do cenário 2]**
   - **Fluxo**: [Passo a passo]
   - **Expectativa**: [Resultado esperado]

---

## Critérios de Aceitação

- [ ] Todos os arquivos criados/modificados conforme especificado
- [ ] Código segue os padrões definidos em `.cursor/rules/`
- [ ] Tipagem php sem uso de `mixed`
- [ ] Todos os testes unitários passando
- [ ] Todos os testes de integração passando
- [ ] Cobertura de testes >= [X%]
- [ ] Code review aprovado
- [ ] Documentação inline (PHPDoc) adicionada
- [ ] Sem erros de lint/type-check

---

## Entregáveis

**Arquivos Criados**:
- [ ] `path/to/new-file1.php`
- [ ] `path/to/new-file2.php`
- [ ] `path/to/__tests__/test1.test.php`

**Arquivos Modificados**:
- [ ] `path/to/existing-file.php`

**Documentação**:
- [ ] README atualizado (se aplicável)
- [ ] Comentários inline em código complexo

---

## Guia de Implementação

### Passo 1: [Título do Passo]
[Instruções detalhadas para implementação]

```bash
# Comandos necessários
composer require [package-name]
```

### Passo 2: [Título do Passo]
[Instruções detalhadas para implementação]

```php
// Exemplo de código
```

### Passo 3: [Título do Passo]
[Instruções detalhadas para implementação]

---

## Validação

### Checklist de Validação Manual:
- [ ] [Passo de validação 1]
- [ ] [Passo de validação 2]
- [ ] [Passo de validação 3]

### Comandos de Validação:
```bash
# Type check
./vendor/bin/phpstan analyse

# Lint
./vendor/bin/pint --test

# Testes
./vendor/bin/phpunit path/to/tests

# Build
composer install --no-dev --optimize-autoloader
```

---

## Riscos e Mitigações

| Risco | Impacto | Probabilidade | Mitigação |
|-------|---------|---------------|-----------|
| [Risco 1] | [Alto/Médio/Baixo] | [Alta/Média/Baixa] | [Como mitigar] |
| [Risco 2] | [Alto/Médio/Baixo] | [Alta/Média/Baixa] | [Como mitigar] |

---

## Notas Adicionais

[Qualquer informação adicional relevante para a implementação desta tarefa]

---

## Histórico

| Data | Autor | Mudança |
|------|-------|---------|
| [Data] | [Nome] | Criação da tarefa |
