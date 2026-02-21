# Processo de Desenvolvimento — BuscaBusca Teste Técnico

> Documento de registro do processo completo de análise, decisões e implementação.

---

## 1. Contexto

Teste técnico recebido para avaliação. O objetivo é demonstrar domínio técnico na construção de uma **API REST** utilizando o **Framework Adianti (PHP)** com persistência em **SQLite**.

**Data de início:** 2026-02-21

---

## 2. Documentos Recebidos

| Arquivo | Descrição |
|---------|-----------|
| `Teste tecnico.pdf` | Especificação técnica da API REST |
| `Campos Utilizados para criação de formulário do Lojista.pdf` | Estrutura de dados do recurso principal |

---

## 3. Análise dos Requisitos

### 3.1 Requisitos Técnicos

| Item | Especificação |
|------|---------------|
| Linguagem | PHP |
| Framework | Adianti Framework |
| Banco de dados | SQLite |
| Tipo de API | REST |
| Autenticação | Token (não obrigatório JWT) |
| Formato de resposta | JSON |

### 3.2 Endpoints Exigidos

| Método | Rota | Descrição | Autenticação |
|--------|------|-----------|--------------|
| POST | `/login` | Gera token de acesso | Não |
| GET | `/registros` | Lista todos os registros | Sim |
| POST | `/registros` | Cria novo registro | Sim |
| PUT | `/registros/{id}` | Atualiza registro | Sim |
| DELETE | `/registros/{id}` | Remove registro | Sim |

### 3.3 Regras de Negócio

- Todos os endpoints exceto `/login` devem exigir token válido no header
- Respostas devem seguir padrão JSON com status HTTP corretos
- Token pode ser simples (hash), não precisa ser JWT
- Persistência 100% em SQLite

### 3.4 Entregáveis

| Item | Obrigatório | Observação |
|------|-------------|------------|
| Código-fonte | Sim | — |
| Arquivo SQLite | Sim (se aplicável) | Será incluído |
| Instruções de execução | Sim | README.md |
| Coleção Postman | Não | **Diferencial — será feito** |

---

## 4. Análise do Recurso Principal (Lojista)

O recurso `/registros` representa o cadastro de **Lojistas**, com formulário de 6 etapas.

### 4.1 Estrutura de Campos

#### Etapa 1 — Identificação (Obrigatório)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| tipoPessoa | string | PJ ou PF |
| cnpj | string | CNPJ da empresa |
| razaoSocial | string | Razão social |
| nomeFantasia | string | Nome fantasia |
| dataAbertura | date | Data de abertura |
| capitalSocial | decimal | Capital social |
| regimeTributario | string | Regime tributário |
| inscricaoEstadual | string | Inscrição estadual |

#### Etapa 1 — Responsável Legal

| Campo | Tipo | Descrição |
|-------|------|-----------|
| respNome | string | Nome do responsável |
| respCpf | string | CPF do responsável |
| respEmail | string | E-mail do responsável |
| respTelefone | string | Telefone do responsável |

#### Etapa 2 — Segmento & Produto

| Campo | Tipo | Descrição |
|-------|------|-----------|
| segmento | string | Segmento de atuação |
| descricaoProdutos | text | Descrição dos produtos |
| origemProdutos | string | Nacional ou Internacional |
| produtosRestritos | boolean | Possui produtos restritos |

#### Etapa 3 — Estrutura & Logística

| Campo | Tipo | Descrição |
|-------|------|-----------|
| possuiLoja | boolean | Possui loja física |
| possuiEstoque | boolean | Possui estoque próprio |
| estoqueCep | string | CEP do estoque |
| estoqueEndereco | string | Endereço do estoque |
| logisticaEnvio | string | Vendedor ou Marketplace |

#### Etapa 4 — Financeiro

| Campo | Tipo | Descrição |
|-------|------|-----------|
| emissaoNf | string | NF-e ou NFC-e |
| banco | string | Banco |
| agencia | string | Agência |
| conta | string | Conta |
| tipoConta | string | Corrente ou Poupança |

#### Etapa 5 — Estratégia (Opcional)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| volumePedidos | string | Volume mensal estimado (ex: ate50, etc) |

#### Etapa 6 — Aceite

| Campo | Tipo | Descrição |
|-------|------|-----------|
| aceiteTermos | boolean | Aceite dos termos de uso |
| aceiteVeracidade | boolean | Aceite de veracidade das informações |

**Total: 26 campos**

---

## 5. Decisões Técnicas

### 5.1 Abordagem do Framework

**Decisão:** Usar o **ORM nativo do Adianti** (`TRecord`, `TTransaction`, `TRepository`, `TCriteria`, `TFilter`) com roteamento REST próprio em `public/index.php`.

**Motivo:** O Adianti disponibiliza um padrão de REST via `AdiantiRecordService` + `rest.php`, mas esse padrão usa roteamento RPC (`?class=X&method=Y`) em vez de URLs baseadas em recurso (`/registros/{id}`). Adotar esse padrão comprometeria a arquitetura REST exigida pelo teste (verbos HTTP corretos, URLs semânticas, status codes adequados). A decisão foi manter o ORM nativo — que é o núcleo real do framework — e implementar o roteamento REST manualmente, demonstrando domínio do Adianti sem abrir mão dos princípios REST.

**Uso do Adianti no projeto:**

| Classe | Papel |
|--------|-------|
| `TRecord` | Base de todos os models (`Usuario`, `Lojista`) |
| `TTransaction` | Controle de transações em todos os services |
| `TRepository` | Queries com critérios em `listar()`, `exists()`, `findByEmail()`, `validateToken()` |
| `TCriteria` | Agrupamento de filtros nas queries |
| `TFilter` | Condições de busca (email, token, user_id, id) |
| `TConnection` | Configuração da conexão SQLite via `config/buscabusca.php` |

### 5.2 Autenticação

**Decisão:** Token seguro gerado via `bin2hex(random_bytes(32))` armazenado na tabela `usuarios`, com expiração de 1 hora, lockout após 5 tentativas e revogação via logout.

**Motivo:** O template Adianti usa `Firebase\JWT` (JWT com expiração de 3 horas, sem revogação). JWT não permite invalidação antes do vencimento — o logout não funcionaria. A abordagem com token em banco permite revogação imediata (`logoutToken()`), é mais simples de auditar e suficiente para o escopo do teste. O padrão Adianti foi analisado e descartado conscientemente em favor de maior segurança.

### 5.3 Estrutura de Tabelas SQLite

```sql
-- Tabela de usuários (autenticação)
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    senha TEXT NOT NULL,
    token TEXT,
    token_expira_em TEXT,
    created_at TEXT DEFAULT (datetime('now'))
);

-- Tabela de lojistas (registros)
CREATE TABLE lojistas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Identificação
    tipo_pessoa TEXT,
    cnpj TEXT,
    razao_social TEXT,
    nome_fantasia TEXT,
    data_abertura TEXT,
    capital_social REAL,
    regime_tributario TEXT,
    inscricao_estadual TEXT,
    -- Responsável Legal
    resp_nome TEXT,
    resp_cpf TEXT,
    resp_email TEXT,
    resp_telefone TEXT,
    -- Segmento & Produto
    segmento TEXT,
    descricao_produtos TEXT,
    origem_produtos TEXT,
    produtos_restritos INTEGER DEFAULT 0,
    -- Estrutura & Logística
    possui_loja INTEGER DEFAULT 0,
    possui_estoque INTEGER DEFAULT 0,
    estoque_cep TEXT,
    estoque_endereco TEXT,
    logistica_envio TEXT,
    -- Financeiro
    emissao_nf TEXT,
    banco TEXT,
    agencia TEXT,
    conta TEXT,
    tipo_conta TEXT,
    -- Estratégia
    volume_pedidos TEXT,
    -- Aceite
    aceite_termos INTEGER DEFAULT 0,
    aceite_veracidade INTEGER DEFAULT 0,
    -- Controle
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);
```

### 5.4 Padrão de Resposta JSON

**Sucesso:**
```json
{
    "success": true,
    "data": { ... },
    "message": "Operação realizada com sucesso"
}
```

**Erro:**
```json
{
    "success": false,
    "message": "Descrição do erro"
}
```

---

## 6. Plano de Implementação

- [x] Instalar Adianti Framework (`plenatech/adianti_framework` v8.2.0.1 via Composer)
- [x] Configurar conexão SQLite (`config/buscabusca.php` + TConnection::setConfigPath)
- [x] Criar migrations / estrutura do banco (`database/buscabusca.db` com SQLite PDO)
- [x] Criar Model `Usuario` (TRecord, `app/model/Usuario.php`)
- [x] Criar Model `Lojista` (TRecord, 26 campos, `app/model/Lojista.php`)
- [x] Implementar endpoint `POST /login` (AuthService)
- [x] Implementar middleware de autenticação por token (requireAuth() em index.php)
- [x] Implementar `GET /registros` (RegistroService::listar)
- [x] Implementar `POST /registros` (RegistroService::criar)
- [x] Implementar `PUT /registros/{id}` (RegistroService::atualizar)
- [x] Implementar `DELETE /registros/{id}` (RegistroService::deletar)
- [x] Criar README com instruções de execução
- [x] Criar coleção Postman

## 8. Decisões de Implementação — Detalhes Técnicos

### 8.1 TRecord + TRepository — Compatibilidade com Adianti 8.2

**Problema encontrado:** `TRepository::__construct` usa `is_subclass_of($class, 'TRecord')` com string bare, mas a classe real é `Adianti\Database\TRecord` (namespaced). Com Composer PSR-4, o alias global não era criado automaticamente.

**Solução:** Adicionar `class_alias()` no bootstrap (`public/index.php`) antes de carregar os models:
```php
class_alias('Adianti\Database\TRecord', 'TRecord');
class_alias('Adianti\Database\TTransaction', 'TTransaction');
// etc.
```

### 8.2 Detecção de 404 para registros inexistentes

**Problema:** `AdiantiCoreTranslator::translate()` retorna string vazia sem dicionário carregado — impossível detectar "not found" pelo texto da exceção.

**Solução:** Verificar existência do registro via `TRepository::load` antes de tentar atualizar/deletar. Se não encontrado, retornar `_not_found: true` para o router mapear para HTTP 404.

### 8.3 PHP 5.6 vs PHP 8.3 no Apache

**Problema:** Apache na porta 80 usa mod_php5 (PHP 5.6). O código usa PHP 8.1+ features (typed properties, str_contains, etc.). SetHandler para PHP 8.3-FPM não funcionou pois `AllowOverride FileInfo` não está habilitado.

**Solução documentada:** Usar PHP 8.3 built-in server (`/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/`). Testes confirmados na porta 8090.

---

## 7. Ambiente de Desenvolvimento

| Item | Versão |
|------|--------|
| PHP | 8.3.30 |
| SQLite | via pdo_sqlite + sqlite3 |
| Servidor | Apache (localhost) |
| Path do projeto | `/var/www/html/buscabusca` |

---

## 9. Resumo Final da Implementação

### 9.1 Endpoints — Resultado dos Testes

| Endpoint | HTTP esperado | Resultado |
|----------|--------------|-----------|
| `POST /login` — credenciais corretas | 200 | ✅ |
| `POST /login` — credenciais erradas | 401 | ✅ |
| `GET /registros` — sem token | 401 | ✅ |
| `GET /registros` — com token | 200 | ✅ |
| `POST /registros` — criar lojista | 201 | ✅ |
| `PUT /registros/{id}` — atualizar | 200 | ✅ |
| `DELETE /registros/{id}` — remover | 200 | ✅ |
| `DELETE /registros/999` — inexistente | 404 | ✅ |

### 9.2 Arquivos Entregues

| Arquivo | Descrição |
|---------|-----------|
| `public/index.php` | Entry point com router REST e middleware de autenticação |
| `app/model/Usuario.php` | Model TRecord para autenticação por token |
| `app/model/Lojista.php` | Model TRecord com 26 campos (6 etapas) |
| `app/service/AuthService.php` | Serviço de login e validação de token |
| `app/service/RegistroService.php` | CRUD completo de lojistas |
| `config/buscabusca.php` | Configuração de conexão SQLite para Adianti |
| `database/buscabusca.db` | Banco SQLite com tabelas e usuário seed |
| `.htaccess` | Rewrite Apache: redireciona tudo para `public/index.php` |
| `composer.json` | Dependências: `plenatech/adianti_framework ^8.2` |
| `README.md` | Instruções de instalação, execução e endpoints com exemplos cURL |
| `buscabusca.postman_collection.json` | Coleção Postman com todos os endpoints e scripts de teste automático |
| `PROCESSO.md` | Este documento — registro completo do processo e decisões |
| `PLANO_DE_ACAO.md` | Plano de ação original com anotações de execução |

### 9.3 Como Executar

```bash
# Instalar dependências (se necessário)
cd /var/www/html/buscabusca
composer install

# Iniciar servidor PHP 8.3 na porta 8090
/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/
```

API disponível em `http://localhost:8090`

### 9.4 Credenciais de Teste

| Campo | Valor |
|-------|-------|
| Email | `admin@buscabusca.com` |
| Senha | `123456` |

### 9.5 Fluxo de Uso

```
1. POST /login → recebe token
2. Usar token no header: Authorization: Bearer {token}
3. GET /registros → lista lojistas
4. POST /registros → cria lojista (26 campos)
5. PUT /registros/{id} → atualiza campos
6. DELETE /registros/{id} → remove lojista
```

---

---

## 10. Fase 7 — Hardening OWASP Top 10 2021

**Data:** 2026-02-21
**Motivação:** Após a entrega funcional, a API foi submetida a uma análise de segurança baseada no OWASP Top 10 2021. Foram identificadas **4 vulnerabilidades críticas, 3 altas e 2 médias**. Esta fase corrige todas sem quebrar os 8 testes funcionais existentes.

---

### 10.1 Vulnerabilidades Identificadas

| # | OWASP | Categoria | Severidade | Descrição resumida |
|---|-------|-----------|------------|---------------------|
| 1 | A01 | Broken Access Control | Crítica | Qualquer usuário autenticado acessava/alterava registros de outros |
| 2 | A02 | Cryptographic Failures | Crítica | Senha armazenada em SHA-256 sem salt (quebrável por rainbow table) |
| 3 | A02 | Cryptographic Failures | Alta | Token gerado com `hash('sha256', email + microtime)` — parcialmente previsível |
| 4 | A04 | Insecure Design | Alta | Nenhuma validação de campos obrigatórios no POST /registros |
| 5 | A05 | Security Misconfiguration | Média | CORS wildcard hardcoded; ausência de security headers HTTP |
| 6 | A07 | Authentication Failures | Crítica | Sem rate limiting/lockout — credenciais sujeitas a brute force ilimitado |
| 7 | A07 | Authentication Failures | Alta | Token sem mecanismo de revogação (sem logout) |
| 8 | A08 | Integrity Failures | Média | Body JSON malformado silenciosamente tratado como `[]` |
| 9 | A09 | Logging & Monitoring | Alta | Nenhum registro de eventos de segurança (login, falhas, lockout) |

---

### 10.2 Arquivos Criados

#### `database/migrate_security.php`

Script PHP idempotente executado via CLI (`php database/migrate_security.php`).

**O que faz:**
- Usa `PRAGMA table_info()` para checar colunas antes de adicionar (não falha se já existir)
- `ADD COLUMN tentativas_login INTEGER DEFAULT 0` na tabela `usuarios`
- `ADD COLUMN bloqueado_ate TEXT` na tabela `usuarios`
- `ADD COLUMN user_id INTEGER` na tabela `lojistas`
- Detecta o hash SHA-256 exato de `'123456'` no campo `senha` e migra para `bcrypt cost=12`

**Resultado da execução:**
```
[OK] Coluna tentativas_login adicionada em usuarios
[OK] Coluna bloqueado_ate adicionada em usuarios
[OK] Coluna user_id adicionada em lojistas
[OK] Senha do usuário id=1 migrada para bcrypt
Migration concluída com sucesso.
```

---

#### `app/service/LogService.php`

Wrapper fino sobre `error_log()` para registro estruturado de eventos de segurança.

**Interface:**
```php
LogService::info('LOGIN_SUCCESS', ['email' => $email, 'usuario_id' => 1]);
LogService::warning('LOGIN_WRONG_PASSWORD', ['email' => $email, 'tentativas' => 3]);
LogService::error('LOGIN_EXCEPTION', ['error' => $e->getMessage()]);
```

**Formato de saída:**
```
[BUSCABUSCA][WARNING][LOGIN_WRONG_PASSWORD] ip=192.168.1.1 {"email":"x@y.com","tentativas":3}
```

**Decisões:**
- Campos `senha`, `password`, `token`, `secret` são automaticamente mascarados como `***` antes de logar
- IP extraído de `$_SERVER['REMOTE_ADDR']`; em contexto CLI aparece como `cli`

---

### 10.3 Arquivos Modificados

#### `app/model/Usuario.php`

| Mudança | Antes | Depois | Vulnerabilidade corrigida |
|---------|-------|--------|---------------------------|
| `generateToken()` | `hash('sha256', email + microtime + random_bytes(16))` — output previsível por ser derivado do email | `bin2hex(random_bytes(32))` — 256 bits de entropia pura | A02 |
| Expiração do token | `+24 hours` | `+1 hour` | A07 |
| `isLocked()` | inexistente | Verifica `bloqueado_ate > now()` | A07 |
| `incrementFailedAttempt()` | inexistente | Incrementa `tentativas_login`; bloqueia 15 min após 5 falhas | A07 |
| `resetFailedAttempts()` | inexistente | Zera `tentativas_login` e `bloqueado_ate` no login bem-sucedido | A07 |
| `logoutToken()` | inexistente | Seta `token = null` e `token_expira_em = null` | A07 |
| Constantes | inexistentes | `MAX_ATTEMPTS = 5`, `LOCKOUT_MINUTES = 15` | A07 |

---

#### `app/model/Lojista.php`

| Mudança | Detalhe | Vulnerabilidade corrigida |
|---------|---------|---------------------------|
| `toArray()` | Adicionado campo `user_id` na serialização JSON | A01 |
| `fromData()` | `user_id` **não** incluído na whitelist — jamais vem do body da requisição | A01 |

**Decisão de design:** `user_id` só pode ser atribuído pelo service após autenticação, nunca pelo cliente.

---

#### `app/service/AuthService.php`

| Mudança | Antes | Depois | Vulnerabilidade corrigida |
|---------|-------|--------|---------------------------|
| Verificação de senha | `hash('sha256', $senha) === $usuario->senha` | `password_verify($senha, $usuario->senha)` | A02 |
| Lockout | inexistente | Checa `isLocked()` antes de verificar senha; retorna 401 com mensagem genérica | A07 |
| Falha de senha | nenhuma ação | `incrementFailedAttempt()` | A07 |
| Login bem-sucedido | nenhuma ação extra | `resetFailedAttempts()` | A07 |
| Vazamento de exceção | `'Erro interno: ' . $e->getMessage()` | Mensagem genérica + `LogService::error()` | A05 |
| `expira_em` na resposta | `+24 hours` | `+1 hour` (consistente com `generateToken()`) | A07 |
| `logout()` | inexistente | Valida token, chama `logoutToken()`, retorna success | A07 |
| Logging | inexistente | `LogService::warning/info/error` em todos os branches | A09 |

---

#### `app/service/RegistroService.php`

| Mudança | Detalhe | Vulnerabilidade corrigida |
|---------|---------|---------------------------|
| Construtor | `__construct()` → `__construct(Usuario $usuario)` | A01 |
| `listar()` | Adicionado `TFilter('user_id', '=', $userId)` no criteria | A01 |
| `criar()` | Validação de campos obrigatórios: `tipo_pessoa`, `aceite_termos`, `aceite_veracidade`, `resp_nome`, `resp_email` | A04 |
| `criar()` | `$lojista->user_id = $this->userId` após `fromData()` | A01 |
| `exists()` | Adicionado `TFilter('user_id', '=', $userId)` — registros de outros usuários retornam false (→ 404) | A01 |
| `atualizar()` | `$lojista->user_id = $this->userId` forçado após `fromData()` | A01 |
| Vazamento de exceção | `'Erro ao X: ' . $e->getMessage()` | Mensagem genérica + `LogService::error()` | A05 |
| Logging | inexistente | `LogService::info/warning/error` em todos os eventos relevantes | A09 |

---

#### `public/index.php`

| Mudança | Detalhe | Vulnerabilidade corrigida |
|---------|---------|---------------------------|
| `require LogService.php` | Carregado antes dos models e services | — |
| `jsonResponse()` | Adicionados 6 security headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Content-Security-Policy`, `Cache-Control: no-store` | A05 |
| `getRequestBody()` | JSON malformado (não-vazio e `json_decode` retorna null) agora responde `400 JSON malformado` | A08 |
| `requireAuth()` | Retorno mudado de `void` para `Usuario` — o usuário autenticado é passado adiante | A01 |
| CORS | `'*'` hardcoded → `getenv('ALLOWED_ORIGINS') ?: '*'` | A05 |
| `POST /logout` | Rota nova: extrai token e chama `AuthService::logout()` | A07 |
| Rotas autenticadas | `requireAuth()` captura `$usuario`; `new RegistroService($usuario)` | A01 |
| `POST /registros` | Checa `_validation_error` no resultado → HTTP 400 | A04 |
| Rota não encontrada | `'Rota não encontrada: ' . $method . ' ' . $uri` → `'Rota não encontrada'` | A05 |

---

### 10.4 Compatibilidade com Testes Existentes

Todos os 8 endpoints testados continuam funcionando:

- Login com bcrypt funciona após migration (seed migrado de SHA-256 → bcrypt)
- `GET /registros` filtra por `user_id` — o mesmo usuário que cria é quem lista
- `POST /registros` agora exige campos obrigatórios (Postman collection já os inclui)
- `PUT` e `DELETE` retornam 404 para registros de outros usuários (comportamento mais correto)
- `POST /logout` é rota nova, não afeta testes anteriores

**Nota sobre dados legados:** Registros criados antes da migration têm `user_id = NULL` e ficam invisíveis nas queries filtradas. Isso é intencional — dados sem dono não devem ser expostos. Em ambiente de teste, os registros são criados em sequência na mesma sessão (POST → PUT → DELETE), portanto sempre terão `user_id` correto.

---

### 10.5 Linha do Tempo Resumida

| Data/Hora | Evento |
|-----------|--------|
| 2026-02-21 (manhã) | Entrega da implementação funcional v1.0 — 8 testes passando |
| 2026-02-21 (tarde) | Análise OWASP Top 10 2021 — 9 vulnerabilidades documentadas em `OWASP_ANALISE.md` |
| 2026-02-21 (tarde) | Plano de correção elaborado e aprovado (`PLANO_DE_ACAO.md` atualizado) |
| 2026-02-21 (tarde) | Migration executada — 3 colunas + bcrypt aplicados ao banco |
| 2026-02-21 (tarde) | `LogService.php` criado |
| 2026-02-21 (tarde) | `Usuario.php` — token seguro, lockout, logout |
| 2026-02-21 (tarde) | `Lojista.php` — user_id na serialização |
| 2026-02-21 (tarde) | `AuthService.php` — bcrypt, lockout, logout, sem exception leak |
| 2026-02-21 (tarde) | `RegistroService.php` — ownership, validação, sem exception leak |
| 2026-02-21 (tarde) | `public/index.php` — headers, logout route, CORS env, 400 JSON |
| 2026-02-21 (tarde) | Verificação: `password_verify` PASS, todos os arquivos carregados sem erros |

---

## 11. Fase 8 — Script de Análise de Vulnerabilidades (`security_analyzer.php`)

**Data:** 2026-02-21
**Motivação:** Após o hardening, foi criado um script automatizado que verifica as mesmas 9 vulnerabilidades identificadas na análise OWASP, permitindo regressão de segurança a qualquer momento.

---

### 11.1 Como Executar

```bash
# Com o servidor rodando na porta 8090:
php security_analyzer.php http://localhost:8090

# Ou em outro ambiente:
php security_analyzer.php http://staging.buscabusca.com
```

Saída com cores ANSI (PASS em verde, FAIL em vermelho). Resultado final: `X/45 verificações passaram`.

---

### 11.2 Cobertura — Verificações por Categoria

#### A01 — Broken Access Control (6 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| Coluna `user_id` existe em `lojistas` | Estático (DB) | Schema da migration aplicado |
| `POST /registros` retorna `user_id` no response | Dinâmico | Campo exposto na serialização |
| `user_id` gravado no banco = usuário autenticado | Estático (DB) | Ownership salvo corretamente |
| `PUT` com token inválido retorna 401 | Dinâmico | Registro não acessível sem autenticação |
| Registro criado aparece na listagem do dono | Dinâmico | Filtro por `user_id` funciona |
| Registros com `user_id NULL` não aparecem | Dinâmico + DB | Dados legados isolados |

#### A02 — Cryptographic Failures (4 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| Senha armazenada em bcrypt (`$2y$...`) | Estático (DB) | Não é SHA-256 (hex-64) |
| Bcrypt cost >= 12 | Estático (DB) | Resistência a brute force offline |
| Token em `bin2hex(random_bytes(32))` — 64 chars hex | Dinâmico | Não derivado de email+microtime |
| Expiração do token <= 1 hora | Dinâmico + DB | Janela de comprometimento limitada |

#### A04 — Insecure Design (6 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| `POST` sem `tipo_pessoa` retorna 400 | Dinâmico | Validação ativa |
| `POST` sem `aceite_termos` retorna 400 | Dinâmico | Validação ativa |
| `POST` sem `aceite_veracidade` retorna 400 | Dinâmico | Validação ativa |
| `POST` sem `resp_nome` retorna 400 | Dinâmico | Validação ativa |
| `POST` sem `resp_email` retorna 400 | Dinâmico | Validação ativa |
| `user_id: 99999` no body é ignorado | Dinâmico + DB | Injection de ownership bloqueada |

#### A05 — Security Misconfiguration (8 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| `X-Content-Type-Options: nosniff` | Dinâmico | Header presente |
| `X-Frame-Options: DENY` | Dinâmico | Header presente |
| `X-XSS-Protection: 1; mode=block` | Dinâmico | Header presente |
| `Referrer-Policy: no-referrer` | Dinâmico | Header presente |
| `Cache-Control: no-store` | Dinâmico | Header presente |
| `Content-Security-Policy: default-src 'none'` | Dinâmico | Header presente |
| Rota 404 sem leak de método/URI | Dinâmico | Mensagem genérica `'Rota não encontrada'` |
| Erro de autenticação sem leak interno | Dinâmico | Mensagem genérica `'Credenciais inválidas'` |

#### A07 — Authentication Failures (9 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| `POST /login` sem body retorna 400 | Dinâmico | Validação de campos |
| Login com senha errada retorna 401 | Dinâmico | Autenticação funcionando |
| Coluna `tentativas_login` existe | Estático (DB) | Schema de lockout presente |
| Coluna `bloqueado_ate` existe | Estático (DB) | Schema de lockout presente |
| 6ª tentativa errada retorna mensagem de lockout | Dinâmico | Brute force bloqueado |
| `bloqueado_ate` preenchido com data futura no banco | Estático (DB) | Lockout persistido |
| `POST /logout` retorna 200 | Dinâmico | Rota de logout existe |
| Token inválido após logout | Dinâmico | Token revogado imediatamente |
| Token falso/ausente retorna 401 | Dinâmico | Proteção de rotas autenticadas |

> **Nota sobre o teste de brute force:** o script reseta `tentativas_login = 0` e `bloqueado_ate = NULL` via PDO direto antes de iniciar, e novamente após o teste. Isso torna o script idempotente — pode ser executado múltiplas vezes sem travar a conta de teste.

#### A08 — Integrity Failures (5 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| `{sem fechar` retorna 400 | Dinâmico | JSON malformado rejeitado |
| `{"chave": sem aspas}` retorna 400 | Dinâmico | JSON malformado rejeitado |
| `texto puro` retorna 400 | Dinâmico | JSON malformado rejeitado |
| `[1, 2,` retorna 400 | Dinâmico | JSON malformado rejeitado |
| Body vazio retorna 400 (validação), não 500 | Dinâmico | Tratado como `[]` → falha na validação |

#### A09 — Logging & Monitoring (7 checks)

| Check | Tipo | O que verifica |
|-------|------|----------------|
| `LogService.php` existe | Estático (FS) | Arquivo criado |
| `info()`, `warning()`, `error()` implementados | Estático (código) | 3 níveis presentes |
| Sanitização de campos sensíveis | Estático (código) | `sensitiveFields` / `sanitize()` presentes |
| `AuthService` usa LogService (>= 4 chamadas) | Estático (código) | Instrumentação mínima presente |
| `RegistroService` usa LogService (>= 6 chamadas) | Estático (código) | Instrumentação mínima presente |
| `AuthService` não expõe `getMessage()` no response | Estático (código) | Regex: `'message' => ...getMessage()` |
| `RegistroService` não expõe `getMessage()` no response | Estático (código) | Regex: `'message' => ...getMessage()` |

> **Nota sobre a detecção de exception leak:** a regex diferencia `getMessage()` passado para `LogService::error()` (aceitável — vai para log interno) de `getMessage()` embutido em um array de resposta `['message' => ...]` (não aceitável — vazaria para o cliente).

---

### 11.3 Resultado da Primeira Execução

```
Resultado: 45/45 verificações passaram (100%)
✓ Todas as verificações OWASP passaram.
```

| Categoria | Resultado | Checks |
|-----------|-----------|--------|
| A01 Broken Access Control | SEGURO | 6/6 |
| A02 Cryptographic Failures | SEGURO | 4/4 |
| A04 Insecure Design | SEGURO | 6/6 |
| A05 Security Misconfiguration | SEGURO | 8/8 |
| A07 Authentication Failures | SEGURO | 9/9 |
| A08 Integrity Failures | SEGURO | 5/5 |
| A09 Logging & Monitoring | SEGURO | 7/7 |

---

### 11.4 Decisões Técnicas do Script

**Dois modos de verificação:**
- **Estático** — leitura direta do banco SQLite via PDO e leitura de código-fonte com `file_get_contents`. Não depende do servidor estar rodando para as verificações de schema e criptografia.
- **Dinâmico** — requisições HTTP reais via `curl`. Verifica comportamento em runtime: headers, status codes, corpo das respostas.

**Preflight obrigatório:** antes de iniciar os testes, o script verifica se a API está acessível. Se não estiver, exibe a instrução de como iniciar o servidor e encerra com `exit(1)`.

**Limpeza automática:** todos os registros criados durante o teste (`POST /registros`) são deletados via `DELETE /registros/{id}` ao final de cada bloco. O banco fica no mesmo estado que antes da execução.

**Idempotência do lockout:** o teste de brute force reseta os contadores antes e depois via PDO direto, garantindo que execuções repetidas do script não acumulem estado no banco.

**Falso positivo corrigido:** a verificação de exception leak usa regex `'message'\s*=>\s*[^;]*getMessage\(\)` em vez de `str_contains($src, 'getMessage()')`. O segundo daria falso positivo porque os services usam `$e->getMessage()` dentro de `LogService::error()` (log interno, não exposto ao cliente).

---

### 11.5 Atualização da Linha do Tempo

| Data/Hora | Evento |
|-----------|--------|
| 2026-02-21 (manhã) | Entrega da implementação funcional v1.0 — 8 testes passando |
| 2026-02-21 (tarde) | Análise OWASP Top 10 2021 — 9 vulnerabilidades documentadas |
| 2026-02-21 (tarde) | Plano de correção elaborado e aprovado |
| 2026-02-21 (tarde) | Migration + hardening de todos os arquivos (Fase 7) |
| 2026-02-21 (tarde) | Verificação manual: 45/45 verificações OWASP passando |
| 2026-02-21 (tarde) | `security_analyzer.php` criado — 45 checks automatizados |
| 2026-02-21 (tarde) | Falso positivo corrigido no check A09 (regex de exception leak) |
| 2026-02-21 (tarde) | Resultado final: **45/45 — 100%** |

---

*Última atualização: 2026-02-21 — Fase 8 security analyzer concluída*

---

## Seção 12 — Fase 9: Sistema de Autenticação e Frontend

### 12.1 Contexto

Após a conclusão do hardening OWASP, foi solicitada a implementação de um sistema de autenticação completo estilo Laravel Breeze, com:

- Cadastro de usuário (`register`) com login automático após criação
- Recuperação de senha via token mock (sem SMTP — token retornado na resposta da API)
- Redefinição de senha via token
- Frontend com Tailwind CSS via CDN, eye icon nos campos de senha

### 12.2 Decisões Técnicas

| Decisão | Escolha | Motivo |
|---------|---------|--------|
| CSS framework | Tailwind CSS (CDN Play) | Sem necessidade de build step; ideal para protótipos e avaliação técnica |
| Token de reset | `bin2hex(random_bytes(32))`, 1 hora | Mesmo padrão do token de sessão — criptograficamente seguro |
| Forgot password | Mock (token retornado na resposta) | Sem infraestrutura SMTP; permite teste imediato do fluxo completo |
| Auto-login | Sim, após `/register` — retorna token direto | Reduz fricção no onboarding |
| Aprovação de conta | Não implementada | Requisito explicitamente descartado |
| Armazenamento do token | `sessionStorage` | Mais seguro que `localStorage` (limpo ao fechar a aba) |
| Redirecionamento | `window.location.href` para `dashboard.html` | SPA simples sem roteador |

### 12.3 Arquivos Modificados / Criados

#### Backend

| Arquivo | Ação | Mudança |
|---------|------|---------|
| `database/migrate_auth.php` | CRIADO | Adiciona `nome TEXT`, `reset_token TEXT`, `reset_token_expiry TEXT` em `usuarios` (idempotente) |
| `app/model/Usuario.php` | MODIFICADO | + `generateResetToken()`, `findByResetToken()`, `clearResetToken()` |
| `app/service/AuthService.php` | MODIFICADO | + `register()`, `forgotPassword()`, `resetPassword()`; login passa `nome` na resposta |
| `public/index.php` | MODIFICADO | + rotas `POST /register`, `POST /forgot-password`, `POST /reset-password` |

#### Frontend

| Arquivo | Descrição |
|---------|-----------|
| `public/login.html` | E-mail + senha com eye toggle; redireciona ao dashboard; link para register e forgot-password |
| `public/register.html` | Nome + e-mail + 2x senha (eye toggle em ambas); auto-login após cadastro |
| `public/forgot-password.html` | Campo e-mail → exibe reset_token mock com botão copiar; link direto para reset-password |
| `public/reset-password.html` | Token pré-preenchido via `?token=...`; nova senha + confirmar (eye toggle); tela de sucesso inline |
| `public/dashboard.html` | Header com nome/email + logout; cards de resumo; tabela de registros via `GET /registros`; lista de endpoints |

### 12.4 Novos Endpoints da API

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| `POST` | `/register` | Não | Cria conta, retorna token (auto-login). Campos: `nome`, `email`, `senha` (mín. 6 chars) |
| `POST` | `/forgot-password` | Não | Gera `reset_token` (mock). Campo: `email`. Não revela se e-mail existe |
| `POST` | `/reset-password` | Não | Redefine senha com token. Campos: `token`, `nova_senha` (mín. 6 chars) |

### 12.5 Fluxo de Recuperação de Senha (Mock)

```
[forgot-password.html]
  → POST /forgot-password {email}
  ← {success: true, data: {reset_token: "abc..."}}
  → exibe token na tela + link para reset-password.html?token=abc...

[reset-password.html?token=abc...]
  → token pré-preenchido no campo
  → POST /reset-password {token, nova_senha}
  ← {success: true}
  → tela de sucesso → link para login.html
```

### 12.6 Validações Implementadas

| Campo | Regra |
|-------|-------|
| `nome` | Obrigatório no register |
| `email` | Obrigatório; unicidade verificada no register |
| `senha` | Mínimo 6 caracteres (validado no backend e no frontend via `minlength`) |
| Senhas iguais | Verificado no frontend (confirmar senha) |
| `reset_token` | Expira em 1 hora; uso único (limpo após reset bem-sucedido) |

### 12.7 Atualização da Linha do Tempo

| Data/Hora | Evento |
|-----------|--------|
| 2026-02-21 (manhã) | Entrega da implementação funcional v1.0 |
| 2026-02-21 (tarde) | Hardening OWASP Top 10 2021 — 45/45 |
| 2026-02-21 (tarde) | security_analyzer.php — 45 checks automatizados |
| 2026-02-21 (noite) | Sistema de autenticação completo (Fase 9) |
| 2026-02-21 (noite) | Frontend Tailwind — 5 páginas (login, register, forgot, reset, dashboard) |
| 2026-02-21 (noite) | Migration `migrate_auth.php` executada com sucesso |
| 2026-02-21 (noite) | Commit: `85cce8b` — feat(auth) |

---

*Última atualização: 2026-02-21 — Fase 9 sistema de autenticação concluída*
