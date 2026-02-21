# Análise de Segurança OWASP Top 10 — BuscaBusca API

**Data:** 2026-02-21
**Versão analisada:** 1.0.0
**Escopo:** API REST PHP (Adianti 8.2 + SQLite)

---

## Resumo Executivo

| Categoria | Status | Severidade |
|-----------|--------|------------|
| A01 — Broken Access Control | ❌ VULNERÁVEL | CRÍTICA |
| A02 — Cryptographic Failures | ❌ VULNERÁVEL | CRÍTICA |
| A03 — Injection | ⚠️ PARCIALMENTE PROTEGIDO | MÉDIA |
| A04 — Insecure Design | ❌ VULNERÁVEL | CRÍTICA |
| A05 — Security Misconfiguration | ❌ VULNERÁVEL | ALTA |
| A06 — Vulnerable and Outdated Components | ⚠️ PARCIALMENTE PROTEGIDO | MÉDIA |
| A07 — Identification and Authentication Failures | ❌ VULNERÁVEL | CRÍTICA |
| A08 — Software and Data Integrity Failures | ❌ VULNERÁVEL | ALTA |
| A09 — Security Logging and Monitoring Failures | ❌ VULNERÁVEL | ALTA |
| A10 — Server-Side Request Forgery (SSRF) | ✅ PROTEGIDO | N/A |

**Resultado: 4 críticas · 3 altas · 2 médias · 1 protegida**

---

## A01 — Broken Access Control

**Status: ❌ VULNERÁVEL — CRÍTICA**

### Problemas identificados

**1. Sem autorização por recurso — qualquer usuário acessa qualquer registro**

Qualquer token válido pode listar, modificar ou deletar registros de qualquer outro usuário. Não há verificação de propriedade.

```php
// RegistroService.php — listar() retorna TODOS os lojistas sem filtro de dono
$repo = new TRepository('Lojista');
$lojistas = $repo->load(new TCriteria); // sem filtro por usuário
```

**2. Modelo Lojista não tem campo de proprietário**

A tabela `lojistas` não possui `user_id`, impossibilitando verificar quem criou cada registro.

**3. Sem RBAC (controle de acesso baseado em papéis)**

O model `Usuario` não possui campo `role` ou `permissoes`. Todos os usuários autenticados têm exatamente o mesmo nível de acesso.

**4. DELETE sem verificação de propriedade**

```php
// index.php
if ($method === 'DELETE' && preg_match('#^/registros/(\d+)$#', $uri, $matches)) {
    requireAuth(); // só verifica se está autenticado, não se é dono
    $service->deletar($id); // deleta qualquer registro de qualquer usuário
}
```

### Correções necessárias

- Adicionar `user_id` na tabela `lojistas` referenciando `usuarios.id`
- Filtrar todas as queries por `user_id = :usuario_autenticado`
- Verificar propriedade antes de UPDATE e DELETE
- Considerar RBAC se houver necessidade de papéis (admin, operador, etc.)

---

## A02 — Cryptographic Failures

**Status: ❌ VULNERÁVEL — CRÍTICA**

### Problemas identificados

**1. SHA-256 para senhas — algoritmo inadequado**

SHA-256 é um hash criptográfico genérico, não um KDF (Key Derivation Function) para senhas. É extremamente rápido e vulnerável a ataques de força bruta e rainbow tables.

```php
// AuthService.php:32
$senhaHash = hash('sha256', $senha); // ❌ sem salt, sem custo computacional
```

Sem salt: dois usuários com a mesma senha terão o mesmo hash no banco.
Sem custo: hardware moderno consegue bilhões de tentativas/segundo.

**2. Geração de token com entropia questionável**

```php
// Usuario.php:42
$token = hash('sha256', $this->email . microtime() . random_bytes(16));
// ❌ email e microtime são previsíveis; random_bytes(16) é bom mas diluído
```

**3. Banco SQLite sem criptografia em repouso**

O arquivo `database/buscabusca.db` é armazenado em texto claro. Se o servidor for comprometido, todos os dados (incluindo hashes de senhas e tokens) ficam expostos.

**4. Token com validade excessiva**

```php
// Usuario.php:43
$expira = date('Y-m-d H:i:s', strtotime('+24 hours')); // ❌ 24h é muito
```

### Correções necessárias

```php
// Senha — usar bcrypt
$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
// Verificação
password_verify($senhaInformada, $hashBanco);

// Token — entropia pura
$token = bin2hex(random_bytes(32));

// Validade — reduzir para 1 hora
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
```

---

## A03 — Injection

**Status: ⚠️ PARCIALMENTE PROTEGIDO — MÉDIA**

### O que está protegido

- O Adianti Framework usa **prepared statements** via PDO internamente — SQL Injection direto não é possível pelo ORM
- O ID de rota é validado com regex `\d+` e convertido para `(int)` antes de qualquer uso

```php
// index.php — ID validado
preg_match('#^/registros/(\d+)$#', $uri, $matches)
$id = (int) $matches[1]; // ✅
```

- O modelo `Lojista::fromData()` usa lista branca de campos permitidos:

```php
// Lojista.php — whitelist
$fields = ['tipo_pessoa', 'cnpj', 'razao_social', ...]; // ✅ apenas campos conhecidos
```

### O que ainda é vulnerável

**1. Mensagens de exceção expostas ao cliente**

```php
// RegistroService.php
'message' => 'Erro ao criar registro: ' . $e->getMessage(), // ❌ pode vazar estrutura do BD
```

Se uma query falhar, a mensagem de erro do PDO pode expor nomes de tabelas, colunas e estrutura interna.

**2. Sem sanitização/validação de tipos de entrada**

```php
// Lojista.php — apenas copia o valor, sem validar
$this->$field = $data[$field]; // ❌ aceita qualquer tipo/valor sem validar
```

Um payload como `{"capital_social": "'; DROP TABLE lojistas; --"}` é bloqueado pelo ORM, mas o valor inválido é salvo no banco sem aviso ao usuário.

### Correções necessárias

- Nunca expor `$e->getMessage()` em produção — logar internamente e retornar mensagem genérica
- Adicionar validação de tipos (verificar se `capital_social` é numérico, `aceite_termos` é boolean, etc.)

---

## A04 — Insecure Design

**Status: ❌ VULNERÁVEL — CRÍTICA**

### Problemas identificados

**1. Sem validação de campos obrigatórios**

```php
// RegistroService.php — POST /registros aceita body vazio sem erro
$lojista = new Lojista;
$lojista->fromData($dados); // ❌ sem checar se campos mínimos estão presentes
$lojista->store();
```

Um lojista pode ser criado com todos os 26 campos nulos, inclusive `aceite_termos = null`.

**2. Sem rate limiting na rota de login**

```php
// index.php — sem nenhum controle de tentativas
if ($method === 'POST' && $uri === '/login') {
    $result = $authService->login($email, $senha); // ❌ ilimitado
}
```

Brute force na senha fica completamente desprotegido.

**3. CORS com wildcard**

```php
// index.php
header('Access-Control-Allow-Origin: *'); // ❌ qualquer origem pode fazer requests
```

**4. Sem validação de formato de campos**

- CNPJ: aceita qualquer string (ex: `"abc"`)
- CPF: sem validação de dígitos verificadores
- Email: sem validação de formato
- CEP: sem validação de 8 dígitos

### Correções necessárias

- Implementar validação de campos obrigatórios (`tipo_pessoa`, `aceite_termos`, `aceite_veracidade`)
- Validar formatos com regex (CNPJ, CPF, email, CEP)
- Implementar rate limiting (ex: máx. 5 tentativas de login em 15 min por IP)
- Restringir CORS para origens específicas

---

## A05 — Security Misconfiguration

**Status: ❌ VULNERÁVEL — ALTA**

### Problemas identificados

**1. Sem headers de segurança HTTP**

Nenhum dos seguintes headers está presente nas respostas:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: default-src 'none'
X-XSS-Protection: 1; mode=block
```

**2. Mensagens de erro internas expostas**

```php
// AuthService.php:62
'message' => 'Erro interno: ' . $e->getMessage(), // ❌ stack trace pode vazar
```

**3. Sem HTTPS enforcement**

Não há redirecionamento forçado para HTTPS, sem HSTS. Credenciais trafegam em plaintext se o servidor não tiver TLS configurado corretamente.

**4. Caminho absoluto do banco hardcoded**

```php
// config/buscabusca.php
'name' => '/var/www/html/buscabusca/database/buscabusca.db', // ❌ path exposto
```

Esse path vaza em mensagens de erro e revela a estrutura de diretórios do servidor.

**5. Arquivo `.htaccess_php83` e `.htaccess.test` no repositório**

Arquivos temporários de debug deixados no projeto expõem informações sobre a infraestrutura.

### Correções necessárias

```php
// Adicionar no início de todas as respostas JSON
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
// Não expor mensagem da exceção:
'message' => 'Erro interno do servidor'
// Usar variável de ambiente para o path do banco
```

---

## A06 — Vulnerable and Outdated Components

**Status: ⚠️ PARCIALMENTE PROTEGIDO — MÉDIA**

### O que está razoável

- Composer está sendo usado, o que permite `composer audit` para checar CVEs
- `composer.lock` registra as versões exatas instaladas

### Problemas identificados

**1. Framework desconhecido no contexto de segurança**

`plenatech/adianti_framework` é um wrapper não-oficial do Adianti. Pode não receber patches de segurança na mesma velocidade que o upstream oficial.

**2. Sem verificação automatizada de vulnerabilidades**

Nenhum processo documentado de `composer audit` ou scan de dependências. Não há CI/CD descrito.

**3. PHP 5.6 no Apache de produção**

O Apache está rodando PHP 5.6 (EOL desde dezembro de 2018), versão sem suporte de segurança. Embora a API use PHP 8.3, a infraestrutura convive com uma versão altamente vulnerável.

### Verificação imediata recomendada

```bash
cd /var/www/html/buscabusca
composer audit
composer outdated
```

---

## A07 — Identification and Authentication Failures

**Status: ❌ VULNERÁVEL — CRÍTICA**

### Problemas identificados

**1. Hash de senha inadequado** (ver A02)

**2. Sem política de senha mínima**

```php
// index.php — sem validação de força de senha
$senha = $body['senha'] ?? ''; // aceita "1", "a", ""
```

**3. Sem bloqueio por tentativas falhas**

O sistema não rastreia tentativas falhas de login. Brute force é trivial.

**4. Token não pode ser revogado antes do prazo**

Se um token for comprometido, não há endpoint `POST /logout` para invalidá-lo. O atacante mantém acesso por até 24 horas.

**5. Sem renovação segura de token**

Não existe mecanismo de refresh token. O usuário precisa reautenticar com senha toda vez que o token expira.

**6. Token armazenado diretamente no banco**

```sql
-- tabela usuarios
token TEXT,
token_expira_em TEXT
```

Se o banco for comprometido, todos os tokens ativos ficam expostos.

**7. Sem verificação de conta ativa/banida**

O modelo `Usuario` não tem campo `ativo`, `bloqueado` ou `tentativas_login`. Não é possível desativar uma conta sem deletá-la.

### Correções necessárias

- Substituir SHA-256 por bcrypt (`password_hash`)
- Implementar `POST /logout` que limpa o token no banco
- Adicionar campo `tentativas_login` e bloquear após N falhas
- Adicionar campo `ativo` em `usuarios`
- Armazenar apenas o hash do token no banco (não o token bruto)
- Implementar política mínima de senha (8+ chars, 1 número, 1 maiúscula)

---

## A08 — Software and Data Integrity Failures

**Status: ❌ VULNERÁVEL — ALTA**

### Problemas identificados

**1. JSON inválido ignorado silenciosamente**

```php
// index.php
$data = json_decode($raw, true);
return is_array($data) ? $data : []; // ❌ erro de parsing não reportado ao cliente
```

Um body malformado retorna array vazio e o registro é criado com dados nulos, sem aviso.

**2. Sem versão de API nas rotas**

```
/registros      ❌ deveria ser /v1/registros
```

Mudanças quebram todos os clientes sem possibilidade de manter compatibilidade.

**3. Sem audit trail de modificações**

O campo `updated_at` registra quando foi atualizado, mas não **quem** atualizou e **o que** mudou. Não é possível auditar alterações.

**4. `updated_at` não atualiza via SQL DEFAULT**

```php
// RegistroService.php
$lojista->updated_at = date('Y-m-d H:i:s'); // definido em código, não por trigger
```

Se alguém modificar o banco diretamente, `updated_at` não será atualizado.

### Correções necessárias

- Retornar HTTP 400 quando JSON for inválido
- Versionar a API: `/v1/registros`
- Adicionar tabela de auditoria `lojistas_log` com `user_id`, `acao`, `dados_anteriores`, `dados_novos`, `created_at`
- Tratar `json_last_error()` após `json_decode()`

---

## A09 — Security Logging and Monitoring Failures

**Status: ❌ VULNERÁVEL — ALTA**

### Problemas identificados

**1. Nenhum log de autenticação**

Logins com sucesso, logins falhos e tentativas com token inválido não são registrados em nenhum lugar.

**2. Nenhum log de operações CRUD**

Criações, atualizações e deleções de lojistas não geram nenhum registro auditável.

**3. Exceções não são logadas no servidor**

```php
// RegistroService.php — exceção retornada ao cliente mas não logada
} catch (\Exception $e) {
    TTransaction::rollback();
    return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    // ❌ sem error_log(), sem Monolog, sem nada
}
```

**4. Sem monitoramento de padrões suspeitos**

- Sem detecção de múltiplas tentativas de login falhas
- Sem alerta para acesso a IDs sequencialmente (enumeração)
- Sem rastreamento de IP por request

### Correções necessárias

```php
// Exemplo mínimo — logar tentativas de login
error_log(sprintf(
    '[AUTH] %s | IP=%s | email=%s | resultado=%s',
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'],
    $email,
    $result['success'] ? 'OK' : 'FALHA'
));
```

Solução ideal: usar **Monolog** com handlers para arquivo, Slack ou serviço de SIEM.

---

## A10 — Server-Side Request Forgery (SSRF)

**Status: ✅ PROTEGIDO — N/A**

### Por que está protegido

- A API não faz nenhuma requisição HTTP para URLs externas
- Não há processamento de upload de arquivos com caminhos arbitrários
- A conexão com o banco de dados é exclusivamente local (SQLite)
- Não há `curl`, `file_get_contents` com URLs remotas, ou bibliotecas de webhook

Esta categoria **não se aplica** à arquitetura atual da API.

---

## Plano de Correção Priorizado

### Prioridade 1 — Crítica (fazer antes de ir para produção)

| # | Problema | Arquivo | Correção |
|---|----------|---------|----------|
| 1 | SHA-256 para senha | `AuthService.php:32` | Substituir por `password_hash(..., PASSWORD_BCRYPT)` |
| 2 | Sem ownership nos registros | `Lojista.php`, `RegistroService.php` | Adicionar `user_id` e filtrar por usuário autenticado |
| 3 | Sem bloqueio por brute force | `index.php` (rota /login) | Rate limiting por IP + lockout após 5 falhas |
| 4 | Sem validação de campos | `RegistroService.php` | Validar obrigatórios + formatos (CNPJ, CPF, email) |

### Prioridade 2 — Alta (fazer em seguida)

| # | Problema | Arquivo | Correção |
|---|----------|---------|----------|
| 5 | Sem headers de segurança HTTP | `index.php` | Adicionar X-Content-Type-Options, HSTS, etc. |
| 6 | CORS wildcard | `index.php:97` | Restringir para origem específica |
| 7 | Sem logout / revogação de token | `Usuario.php` | Endpoint `POST /logout` que limpa o token |
| 8 | Mensagens de erro internas expostas | `AuthService.php:62`, `RegistroService.php` | Logar internamente, retornar mensagem genérica |
| 9 | Sem log de segurança | todos os services | Implementar Monolog ou `error_log()` básico |
| 10 | Token sem revogação e validade de 24h | `Usuario.php:43` | Reduzir para 1h + implementar logout |

### Prioridade 3 — Média (melhorias incrementais)

| # | Problema | Arquivo | Correção |
|---|----------|---------|----------|
| 11 | JSON inválido silencioso | `index.php:49` | Retornar HTTP 400 quando body for inválido |
| 12 | Sem versionamento de API | rotas em `index.php` | Adicionar prefixo `/v1/` |
| 13 | Sem auditoria de alterações | `RegistroService.php` | Tabela `lojistas_log` com histórico |
| 14 | Path do banco hardcoded | `config/buscabusca.php` | Usar variável de ambiente `$_ENV['DB_PATH']` |
| 15 | `composer audit` não automatizado | `composer.json` | Adicionar ao pipeline de CI |

---

## Contexto: API de Teste vs. Produção

Esta análise foi feita sobre um sistema desenvolvido como **teste técnico**, não como sistema de produção. Muitas das vulnerabilidades identificadas são comuns em projetos de demonstração onde o foco é a funcionalidade sobre a segurança.

As categorias OWASP **A03 (Injection)** e **A10 (SSRF)** estão razoavelmente cobertas pela arquitetura escolhida (Adianti ORM com prepared statements). Os demais pontos são melhorias esperadas para um sistema em produção real.

---

*Gerado em: 2026-02-21*
*Metodologia: OWASP Top 10 2021*
