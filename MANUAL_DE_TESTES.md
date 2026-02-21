# Manual de Testes — BuscaBusca API REST

> Documento de referência para execução e validação de todos os cenários de teste da API.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Ambiente de Teste](#2-ambiente-de-teste)
3. [Convenções](#3-convenções)
4. [Testes Funcionais — Autenticação](#4-testes-funcionais--autenticação)
5. [Testes Funcionais — Proteção de Rotas](#5-testes-funcionais--proteção-de-rotas)
6. [Testes Funcionais — Listagem de Registros](#6-testes-funcionais--listagem-de-registros)
7. [Testes Funcionais — Criação de Registros](#7-testes-funcionais--criação-de-registros)
8. [Testes Funcionais — Atualização de Registros](#8-testes-funcionais--atualização-de-registros)
9. [Testes Funcionais — Remoção de Registros](#9-testes-funcionais--remoção-de-registros)
10. [Testes de Segurança — OWASP](#10-testes-de-segurança--owasp)
11. [Testes Automatizados](#11-testes-automatizados)
12. [Critérios de Aceite](#12-critérios-de-aceite)

---

## 1. Visão Geral

Este manual cobre **34 cenários de teste** distribuídos em duas categorias:

| Categoria | Cenários | Ferramenta |
|-----------|----------|------------|
| Funcionais | 22 | Postman / cURL |
| Segurança OWASP | 12 | cURL / security_analyzer.php |
| **Total** | **34** | |

**Escopo:** endpoints REST da API BuscaBusca, comportamento de autenticação, controle de acesso, validação de dados e cabeçalhos de segurança.

**Fora do escopo:** testes de carga, testes de interface, testes de banco de dados direto (cobertos pelo script automatizado).

---

## 2. Ambiente de Teste

### 2.1 Iniciar o servidor

```bash
cd /var/www/html/buscabusca
/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/
```

### 2.2 Verificar que está no ar

```bash
curl -s http://localhost:8090/ | python3 -m json.tool
# Esperado: {"success":false,"message":"Rota não encontrada"}
```

### 2.3 Credenciais do usuário seed

| Campo | Valor |
|-------|-------|
| Email | `admin@buscabusca.com` |
| Senha | `123456` |

### 2.4 Variáveis usadas neste manual

Ao longo dos testes, as variáveis abaixo são reutilizadas. Preencha-as à medida que obtiver os valores:

```bash
BASE="http://localhost:8090"
TOKEN=""          # preenchido após AUTH-01
REGISTRO_ID=""    # preenchido após CRIAR-01
```

---

## 3. Convenções

| Símbolo | Significado |
|---------|-------------|
| ✅ PASS | Resultado obtido = resultado esperado |
| ❌ FAIL | Divergência — investigar |
| `→` | Resultado esperado |
| `[pré]` | Pré-condição obrigatória |

Cada cenário segue o formato:

```
ID      Identificador único
OBJ     O que está sendo verificado
PRÉ     Estado necessário antes de executar
CMD     Comando cURL
ESP     Resultado esperado (HTTP status + campos do body)
```

---

## 4. Testes Funcionais — Autenticação

### AUTH-01 — Login com credenciais válidas

```
OBJ  Token é gerado e retornado ao autenticar com sucesso
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"123456"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 200
     success = true
     data.token  → string hexadecimal de 64 caracteres
     data.expira_em → timestamp ~1 hora à frente
     data.usuario.id = 1
     data.usuario.email = "admin@buscabusca.com"
```

> Salve o token: `TOKEN="<valor de data.token>"`

---

### AUTH-02 — Login com senha errada

```
OBJ  Credenciais inválidas retornam 401 com mensagem genérica
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"errada"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message = "Credenciais inválidas"
     message NÃO contém "Exception", ".php" ou stack trace
```

---

### AUTH-03 — Login sem email

```
OBJ  Campo obrigatório ausente retorna 400
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"senha":"123456"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 400
     success = false
     message = "email e senha são obrigatórios"
```

---

### AUTH-04 — Login sem senha

```
OBJ  Campo obrigatório ausente retorna 400
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 400
     success = false
     message = "email e senha são obrigatórios"
```

---

### AUTH-05 — Login com email inexistente

```
OBJ  Email não cadastrado retorna 401 (sem revelar que o email não existe)
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"naoexiste@buscabusca.com","senha":"123456"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message = "Credenciais inválidas"   ← mesma mensagem do AUTH-02 (sem revelar qual campo errou)
```

---

### AUTH-06 — Lockout após 5 tentativas com senha errada

```
OBJ  Conta é bloqueada temporariamente após 5 falhas consecutivas
PRÉ  Conta NÃO estar bloqueada (tentativas_login = 0)
     Se necessário, resetar: UPDATE usuarios SET tentativas_login=0, bloqueado_ate=NULL;
```

Execute o bloco abaixo **em sequência** (6 chamadas):

```bash
# Tentativas 1 a 5 (senha errada)
for i in 1 2 3 4 5; do
  echo "--- Tentativa $i ---"
  curl -s -X POST $BASE/login \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@buscabusca.com","senha":"errada"}' \
    | python3 -m json.tool
done

# Tentativa 6 — deve retornar lockout
echo "--- Tentativa 6 (esperado: lockout) ---"
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"errada"}' \
  | python3 -m json.tool
```

```
ESP  Tentativas 1-5: HTTP 401, success=false, message="Credenciais inválidas"
     Tentativa 6:    HTTP 401, success=false
                     message contém "bloqueado" ou "temporariamente"
```

---

### AUTH-07 — Acesso bloqueado durante lockout (senha correta)

```
OBJ  Mesmo com senha correta, conta bloqueada rejeita login
PRÉ  [AUTH-06] executado (conta em lockout)
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"123456"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message contém "bloqueado" ou "temporariamente"
```

> Após validar, resete o lockout para continuar os demais testes:
> ```bash
> sqlite3 database/buscabusca.db \
>   "UPDATE usuarios SET tentativas_login=0, bloqueado_ate=NULL"
> ```

---

### AUTH-08 — Logout invalida token imediatamente

```
OBJ  Token não pode ser usado após POST /logout
PRÉ  [AUTH-01] — TOKEN preenchido
```

```bash
# Passo 1: logout
curl -s -X POST $BASE/logout \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool

# Passo 2: tentar usar o token invalidado
curl -s $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

```
ESP  Passo 1: HTTP 200, success=true, message="Logout realizado com sucesso"
     Passo 2: HTTP 401, success=false, message="Token inválido ou expirado"
```

> Após este teste, faça login novamente para renovar o token:
> ```bash
> TOKEN=$(curl -s -X POST $BASE/login \
>   -H "Content-Type: application/json" \
>   -d '{"email":"admin@buscabusca.com","senha":"123456"}' \
>   | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")
> ```

---

### AUTH-09 — Logout sem token

```
OBJ  POST /logout sem token retorna 401
PRÉ  Servidor rodando
```

```bash
curl -s -X POST $BASE/logout \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message = "Token não informado"
```

---

## 5. Testes Funcionais — Proteção de Rotas

### PROT-01 — GET /registros sem token

```
OBJ  Rota protegida rejeita acesso sem autenticação
PRÉ  Servidor rodando
```

```bash
curl -s $BASE/registros | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message = "Token não informado"
```

---

### PROT-02 — GET /registros com token inválido

```
OBJ  Token falso (string aleatória) é rejeitado
PRÉ  Servidor rodando
```

```bash
curl -s $BASE/registros \
  -H "Authorization: Bearer 0000000000000000000000000000000000000000000000000000000000000000" \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
     message = "Token inválido ou expirado"
```

---

### PROT-03 — Rota inexistente não expõe detalhes internos

```
OBJ  404 retorna mensagem genérica sem vazar método, URI ou stack trace
PRÉ  Servidor rodando
```

```bash
curl -s $BASE/rota-que-nao-existe | python3 -m json.tool
```

```
ESP  HTTP 404
     success = false
     message = "Rota não encontrada"   ← sem método ou URI na mensagem
```

---

## 6. Testes Funcionais — Listagem de Registros

### LIST-01 — Listar registros do usuário autenticado

```
OBJ  GET /registros retorna apenas registros do usuário dono do token
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

```bash
curl -s $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

```
ESP  HTTP 200
     success = true
     data = array (pode ser vazio se nenhum registro criado ainda)
     message = "X registro(s) encontrado(s)"
     Cada item do array contém user_id = ID do usuário autenticado
```

---

### LIST-02 — Security headers presentes na resposta

```
OBJ  Resposta inclui os cabeçalhos de segurança obrigatórios
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

```bash
curl -si $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  | grep -iE "x-content-type|x-frame|x-xss|referrer|content-security|cache-control"
```

```
ESP  X-Content-Type-Options: nosniff
     X-Frame-Options: DENY
     X-XSS-Protection: 1; mode=block
     Referrer-Policy: no-referrer
     Content-Security-Policy: default-src 'none'
     Cache-Control: no-store
```

---

## 7. Testes Funcionais — Criação de Registros

### CRIAR-01 — Criar lojista com todos os campos

```
OBJ  POST /registros cria registro com 26 campos e retorna 201
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_pessoa": "PJ",
    "cnpj": "12.345.678/0001-99",
    "razao_social": "Empresa Teste LTDA",
    "nome_fantasia": "Loja Teste",
    "data_abertura": "2020-01-15",
    "capital_social": 50000.00,
    "regime_tributario": "Simples Nacional",
    "inscricao_estadual": "123456789",
    "resp_nome": "João Silva",
    "resp_cpf": "123.456.789-00",
    "resp_email": "joao@teste.com",
    "resp_telefone": "(11) 99999-0000",
    "segmento": "Eletronicos",
    "descricao_produtos": "Smartphones e acessórios",
    "origem_produtos": "Nacional",
    "produtos_restritos": false,
    "possui_loja": true,
    "possui_estoque": true,
    "estoque_cep": "01310-100",
    "estoque_endereco": "Av. Paulista, 1000",
    "logistica_envio": "Vendedor",
    "emissao_nf": "NF-e",
    "banco": "Itaú",
    "agencia": "1234",
    "conta": "56789-0",
    "tipo_conta": "Corrente",
    "volume_pedidos": "ate50",
    "aceite_termos": true,
    "aceite_veracidade": true
  }' | python3 -m json.tool
```

```
ESP  HTTP 201
     success = true
     data.id → inteiro > 0
     data.user_id → ID do usuário autenticado
     data.tipo_pessoa = "PJ"
     data.resp_nome = "João Silva"
     data.aceite_termos = true
     message = "Registro criado com sucesso"
```

> Salve o ID: `REGISTRO_ID=<valor de data.id>`

---

### CRIAR-02 — Criar com apenas campos obrigatórios

```
OBJ  Campos opcionais podem ser omitidos; apenas os 5 obrigatórios bastam
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_pessoa": "PF",
    "resp_nome": "Maria Souza",
    "resp_email": "maria@teste.com",
    "aceite_termos": true,
    "aceite_veracidade": true
  }' | python3 -m json.tool
```

```
ESP  HTTP 201
     success = true
     data.id → inteiro > 0
     data.cnpj = null   (campos opcionais ausentes retornam null)
     data.user_id → ID do usuário autenticado
```

> Lembre de deletar este registro depois para não poluir a listagem.

---

### CRIAR-03 a CRIAR-07 — Campos obrigatórios ausentes

```
OBJ  Cada campo obrigatório, quando ausente, retorna 400 identificando o campo
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

Execute um por vez, removendo o campo indicado:

**CRIAR-03 — sem `tipo_pessoa`:**
```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"resp_nome":"Teste","resp_email":"x@x.com","aceite_termos":true,"aceite_veracidade":true}' \
  | python3 -m json.tool
```

**CRIAR-04 — sem `resp_nome`:**
```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tipo_pessoa":"PJ","resp_email":"x@x.com","aceite_termos":true,"aceite_veracidade":true}' \
  | python3 -m json.tool
```

**CRIAR-05 — sem `resp_email`:**
```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tipo_pessoa":"PJ","resp_nome":"Teste","aceite_termos":true,"aceite_veracidade":true}' \
  | python3 -m json.tool
```

**CRIAR-06 — sem `aceite_termos`:**
```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tipo_pessoa":"PJ","resp_nome":"Teste","resp_email":"x@x.com","aceite_veracidade":true}' \
  | python3 -m json.tool
```

**CRIAR-07 — sem `aceite_veracidade`:**
```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tipo_pessoa":"PJ","resp_nome":"Teste","resp_email":"x@x.com","aceite_termos":true}' \
  | python3 -m json.tool
```

```
ESP (todos)  HTTP 400
             success = false
             message = "Campo obrigatório ausente: <nome_do_campo>"
```

---

### CRIAR-08 — Tentativa de injetar user_id pelo body

```
OBJ  user_id informado no body é ignorado; valor vem do token autenticado
PRÉ  [AUTH-01] — TOKEN válido preenchido
```

```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_pessoa": "PJ",
    "resp_nome": "Injeção Teste",
    "resp_email": "inject@teste.com",
    "aceite_termos": true,
    "aceite_veracidade": true,
    "user_id": 99999
  }' | python3 -m json.tool
```

```
ESP  HTTP 201
     success = true
     data.user_id ≠ 99999   ← valor do body ignorado
     data.user_id = ID do usuário autenticado pelo token
```

> Lembre de deletar este registro depois.

---

## 8. Testes Funcionais — Atualização de Registros

### ATU-01 — Atualizar campos parcialmente

```
OBJ  PUT /registros/{id} atualiza apenas os campos enviados
PRÉ  [CRIAR-01] — REGISTRO_ID preenchido
     [AUTH-01]  — TOKEN válido
```

```bash
curl -s -X PUT $BASE/registros/$REGISTRO_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nome_fantasia":"Loja Atualizada","segmento":"Moda"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 200
     success = true
     data.id = REGISTRO_ID
     data.nome_fantasia = "Loja Atualizada"
     data.segmento = "Moda"
     data.tipo_pessoa = "PJ"   ← campos não enviados permanecem intactos
     message = "Registro atualizado com sucesso"
```

---

### ATU-02 — Atualizar ID inexistente

```
OBJ  ID que não existe retorna 404
PRÉ  [AUTH-01] — TOKEN válido
```

```bash
curl -s -X PUT $BASE/registros/999999 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nome_fantasia":"Fantasma"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 404
     success = false
     message contém "não encontrado"
```

---

### ATU-03 — Atualizar sem token

```
OBJ  Rota protegida rejeita sem autenticação
PRÉ  REGISTRO_ID preenchido
```

```bash
curl -s -X PUT $BASE/registros/$REGISTRO_ID \
  -H "Content-Type: application/json" \
  -d '{"nome_fantasia":"Tentativa"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
```

---

## 9. Testes Funcionais — Remoção de Registros

### DEL-01 — Remover lojista existente

```
OBJ  DELETE /registros/{id} remove o registro com sucesso
PRÉ  [CRIAR-01] — REGISTRO_ID preenchido
     [AUTH-01]  — TOKEN válido
```

```bash
curl -s -X DELETE $BASE/registros/$REGISTRO_ID \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

```
ESP  HTTP 200
     success = true
     message = "Registro removido com sucesso"
```

---

### DEL-02 — Remover ID inexistente

```
OBJ  ID que não existe retorna 404
PRÉ  [AUTH-01] — TOKEN válido
```

```bash
curl -s -X DELETE $BASE/registros/999999 \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

```
ESP  HTTP 404
     success = false
     message contém "não encontrado"
```

---

### DEL-03 — Remover sem token

```
OBJ  Rota protegida rejeita sem autenticação
PRÉ  Servidor rodando
```

```bash
curl -s -X DELETE $BASE/registros/1 | python3 -m json.tool
```

```
ESP  HTTP 401
     success = false
```

---

## 10. Testes de Segurança — OWASP

### SEC-01 — A02 | Senha armazenada em bcrypt

```
OBJ  Hash no banco começa com $2y$ (bcrypt), não é SHA-256 (hex de 64 chars)
PRÉ  Acesso ao SQLite
```

```bash
sqlite3 database/buscabusca.db \
  "SELECT substr(senha,1,7) as prefixo FROM usuarios WHERE email='admin@buscabusca.com'"
```

```
ESP  $2y$12$   ← prefixo bcrypt com cost 12
     NÃO deve ser sequência hexadecimal de 64 caracteres
```

---

### SEC-02 — A02 | Token com alta entropia

```
OBJ  Token gerado é hexadecimal de 64 chars derivado de random_bytes (não de email+microtime)
PRÉ  [AUTH-01] — TOKEN preenchido
```

```bash
echo $TOKEN | wc -c          # deve ser 65 (64 chars + newline)
echo $TOKEN | grep -E '^[a-f0-9]{64}$' && echo "Formato OK"
```

```
ESP  65 (wc -c)
     "Formato OK"
```

---

### SEC-03 — A02 | Expiração em 1 hora

```
OBJ  Token expira em até 1 hora (não 24 horas)
PRÉ  [AUTH-01] — login recém-realizado
```

```bash
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"123456"}' \
  | python3 -c "
import sys, json
from datetime import datetime
d = json.load(sys.stdin)['data']
expira = datetime.strptime(d['expira_em'], '%Y-%m-%d %H:%M:%S')
agora  = datetime.now()
horas  = (expira - agora).seconds / 3600
print(f'Expira em: {d[\"expira_em\"]}')
print(f'Horas restantes: {horas:.2f}')
print('OK' if horas <= 1.1 else 'FAIL — maior que 1 hora')
"
```

```
ESP  Horas restantes entre 0.9 e 1.1
     "OK"
```

---

### SEC-04 — A01 | Isolamento por user_id

```
OBJ  Registros criados por um usuário não aparecem para outro token
PRÉ  [CRIAR-01] — REGISTRO_ID criado com TOKEN do usuário 1
     Simularemos outro usuário com um token falso (comportamento: 401)
```

```bash
TOKEN_FALSO="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

curl -s -X PUT $BASE/registros/$REGISTRO_ID \
  -H "Authorization: Bearer $TOKEN_FALSO" \
  -H "Content-Type: application/json" \
  -d '{"nome_fantasia":"Invasor"}' \
  | python3 -m json.tool
```

```
ESP  HTTP 401   ← token inválido rejeitado antes de chegar ao registro
     success = false
```

---

### SEC-05 — A05 | Security headers completos

```
OBJ  Todos os 6 headers de segurança estão presentes em qualquer resposta
PRÉ  [AUTH-01] — TOKEN válido
```

```bash
curl -sI $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  | grep -iE "x-content-type-options|x-frame-options|x-xss-protection|referrer-policy|content-security-policy|cache-control"
```

```
ESP  x-content-type-options: nosniff
     x-frame-options: DENY
     x-xss-protection: 1; mode=block
     referrer-policy: no-referrer
     content-security-policy: default-src 'none'
     cache-control: no-store
```

---

### SEC-06 — A07 | Lockout após 5 tentativas

Ver **AUTH-06** e **AUTH-07** — já cobre este requisito.

---

### SEC-07 — A08 | JSON malformado rejeitado

```
OBJ  Body com JSON inválido retorna 400 em vez de ser silenciosamente ignorado
PRÉ  [AUTH-01] — TOKEN válido
```

```bash
curl -s -X POST $BASE/registros \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{chave sem aspas: valor}' \
  | python3 -m json.tool
```

```
ESP  HTTP 400
     success = false
     message = "JSON malformado"
```

---

### SEC-08 — A09 | Log gerado em eventos de segurança

```
OBJ  Tentativa de login falha produz entrada no log do PHP
PRÉ  Servidor rodando em terminal (logs visíveis no stdout)
```

```bash
# Em um terminal: iniciar servidor e redirecionar log
/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/ 2>&1 | grep BUSCABUSCA &

# Em outro terminal: fazer login com senha errada
curl -s -X POST $BASE/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"errada"}'
```

```
ESP  Linha de log no formato:
     [BUSCABUSCA][WARNING][LOGIN_WRONG_PASSWORD] ip=127.0.0.1 {"email":"admin@buscabusca.com","tentativas":1}
     Campo "senha" NÃO aparece no log
```

---

## 11. Testes Automatizados

### AUTO-01 — Security Analyzer (45 checks)

```
OBJ  Script automatizado valida todas as 7 categorias OWASP em uma única execução
PRÉ  Servidor rodando na porta 8090
     Conta NÃO bloqueada (script reseta automaticamente, mas confirme antes)
```

```bash
cd /var/www/html/buscabusca
php security_analyzer.php http://localhost:8090
```

```
ESP  45/45 verificações passaram (100%)
     ✓ Todas as verificações OWASP passaram.

     Sumário por categoria:
     A01  SEGURO   6/6
     A02  SEGURO   4/4
     A04  SEGURO   6/6
     A05  SEGURO   8/8
     A07  SEGURO   9/9
     A08  SEGURO   5/5
     A09  SEGURO   7/7
```

---

### AUTO-02 — Postman Collection Runner

```
OBJ  Todos os 10 requests da coleção executam com os testes passando
PRÉ  Postman instalado
     Coleção importada (buscabusca.postman_collection.json)
```

**Passos:**
1. Abra o Postman
2. Selecione a coleção **BuscaBusca API REST**
3. Clique em **Run collection**
4. Confirme a ordem padrão das requests
5. Clique em **Run BuscaBusca API REST**

```
ESP  10/10 requests executadas
     Todos os testes (pm.test) marcados como PASSED
     Sem erros de conexão
```

---

## 12. Critérios de Aceite

### 12.1 Resultado esperado por cenário

| ID | Cenário | HTTP | success |
|----|---------|------|---------|
| AUTH-01 | Login válido | 200 | true |
| AUTH-02 | Senha errada | 401 | false |
| AUTH-03 | Sem email | 400 | false |
| AUTH-04 | Sem senha | 400 | false |
| AUTH-05 | Email inexistente | 401 | false |
| AUTH-06 | Lockout na 6ª tentativa | 401 | false |
| AUTH-07 | Login durante lockout | 401 | false |
| AUTH-08 | Logout — passo 1 | 200 | true |
| AUTH-08 | Logout — passo 2 (token inválido) | 401 | false |
| AUTH-09 | Logout sem token | 401 | false |
| PROT-01 | Sem token | 401 | false |
| PROT-02 | Token inválido | 401 | false |
| PROT-03 | Rota inexistente | 404 | false |
| LIST-01 | Listar registros | 200 | true |
| LIST-02 | Security headers | 200 | — |
| CRIAR-01 | Criar com 26 campos | 201 | true |
| CRIAR-02 | Criar com mínimo | 201 | true |
| CRIAR-03 | Sem tipo_pessoa | 400 | false |
| CRIAR-04 | Sem resp_nome | 400 | false |
| CRIAR-05 | Sem resp_email | 400 | false |
| CRIAR-06 | Sem aceite_termos | 400 | false |
| CRIAR-07 | Sem aceite_veracidade | 400 | false |
| CRIAR-08 | user_id injetado | 201 | true |
| ATU-01 | Atualizar campos | 200 | true |
| ATU-02 | ID inexistente | 404 | false |
| ATU-03 | Sem token | 401 | false |
| DEL-01 | Remover lojista | 200 | true |
| DEL-02 | ID inexistente | 404 | false |
| DEL-03 | Sem token | 401 | false |
| SEC-01 | Bcrypt no banco | — | — |
| SEC-02 | Token 64 chars hex | — | — |
| SEC-03 | Expiração 1h | — | — |
| SEC-04 | Isolamento user_id | 401 | false |
| SEC-05 | 6 security headers | — | — |
| SEC-07 | JSON malformado | 400 | false |
| SEC-08 | Log em evento de falha | — | — |
| AUTO-01 | Security analyzer | — | 45/45 |

### 12.2 Definição de PASS / FAIL

| Critério | PASS | FAIL |
|----------|------|------|
| HTTP status | Igual ao esperado | Diferente |
| Campo `success` | Igual ao esperado | Diferente |
| Conteúdo de `message` | Não contém detalhes internos (stack, .php, Exception) | Contém informação interna |
| Security headers | Todos os 6 presentes com valor correto | Ausente ou valor errado |
| Bcrypt | Começa com `$2y$12$` | Qualquer outro formato |
| Token | 64 chars hexadecimais | Comprimento ou charset diferente |
| Lockout | Bloqueio efetivo na 6ª tentativa | Permite acesso ilimitado |
| AUTO-01 | 45/45 | Qualquer número < 45 |

### 12.3 Ordem de execução recomendada

Para garantir que as variáveis (`TOKEN`, `REGISTRO_ID`) estejam disponíveis quando necessário, execute na seguinte ordem:

```
AUTH-01 → AUTH-02 → AUTH-03 → AUTH-04 → AUTH-05
→ AUTH-06 → AUTH-07 → [reset lockout]
→ AUTH-01 (novo token) → AUTH-08 → AUTH-09
→ AUTH-01 (novo token) → PROT-01 → PROT-02 → PROT-03
→ LIST-01 → LIST-02
→ CRIAR-01 → CRIAR-02 → CRIAR-03..07 → CRIAR-08
→ ATU-01 → ATU-02 → ATU-03
→ DEL-01 → DEL-02 → DEL-03
→ SEC-01..08
→ AUTO-01
```

---

*Última atualização: 2026-02-21 — v1.0 do Manual de Testes*
