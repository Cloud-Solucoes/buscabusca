# BuscaBusca API REST

API REST para cadastro de Lojistas, desenvolvida com **PHP 8.3**, **Adianti Framework 8.2** e banco de dados **SQLite**.

---

## Requisitos

| Item | Versão mínima |
|------|--------------|
| PHP  | 8.1+ |
| Composer | 2.x |
| Extensão PDO SQLite | habilitada |
| Extensão SQLite3 | habilitada |

---

## Instalação

```bash
# 1. Entrar no diretório do projeto
cd /var/www/html/buscabusca

# 2. Instalar dependências
composer install

# 3. Executar a migration de segurança (necessário apenas na primeira vez)
php database/migrate_security.php
```

O banco de dados já está incluído em `database/buscabusca.db` com o usuário seed criado.

---

## Como Executar

### Opção 1 — PHP Built-in Server (Recomendado para desenvolvimento)

```bash
# Inicia o servidor na porta 8090 com PHP 8.3
/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/

# A API estará disponível em:
# http://localhost:8090
```

### Opção 2 — Apache com PHP 8.3 FPM

Adicione no `.htaccess` dentro da pasta `public/`:

```apache
<FilesMatch "\.php$">
    SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
</FilesMatch>
```

> **Nota:** Requer `AllowOverride FileInfo` no Virtual Host do Apache e `mod_proxy_fcgi` carregado.

---

## Credenciais de Teste

| Campo | Valor |
|-------|-------|
| Email | `admin@buscabusca.com` |
| Senha | `123456` |

---

## Endpoints

### POST /login

Autentica e retorna o token de acesso (válido por **1 hora**).

```bash
curl -X POST http://localhost:8090/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@buscabusca.com","senha":"123456"}'
```

**Resposta (200):**
```json
{
  "success": true,
  "data": {
    "token": "d1ec6230b06cee1b967553e7120c4be1...",
    "expira_em": "2026-02-21 09:31:44",
    "usuario": { "id": 1, "email": "admin@buscabusca.com" }
  },
  "message": "Login realizado com sucesso"
}
```

---

### POST /logout

Invalida o token imediatamente. Requer token.

```bash
curl -X POST http://localhost:8090/logout \
  -H "Authorization: Bearer {token}"
```

**Resposta (200):**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

---

### GET /registros

Lista os lojistas do usuário autenticado. Requer token.

```bash
curl http://localhost:8090/registros \
  -H "Authorization: Bearer {token}"
```

**Resposta (200):**
```json
{
  "success": true,
  "data": [ { "id": 1, "tipo_pessoa": "PJ", "user_id": 1, "..." : "..." } ],
  "message": "1 registro(s) encontrado(s)"
}
```

---

### POST /registros

Cria um novo lojista. Requer token.

**Campos obrigatórios:** `tipo_pessoa`, `resp_nome`, `resp_email`, `aceite_termos`, `aceite_veracidade`

```bash
curl -X POST http://localhost:8090/registros \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_pessoa": "PJ",
    "cnpj": "12.345.678/0001-99",
    "razao_social": "Empresa Exemplo LTDA",
    "nome_fantasia": "Loja Exemplo",
    "data_abertura": "2020-01-15",
    "capital_social": 50000.00,
    "regime_tributario": "Simples Nacional",
    "inscricao_estadual": "123456789",
    "resp_nome": "João Silva",
    "resp_cpf": "123.456.789-00",
    "resp_email": "joao@exemplo.com",
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
  }'
```

**Resposta (201):**
```json
{
  "success": true,
  "data": { "id": 1, "tipo_pessoa": "PJ", "user_id": 1, "..." : "..." },
  "message": "Registro criado com sucesso"
}
```

---

### PUT /registros/{id}

Atualiza um lojista existente. Requer token. Só atualiza registros do próprio usuário.

```bash
curl -X PUT http://localhost:8090/registros/1 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"nome_fantasia": "Loja Atualizada", "segmento": "Moda"}'
```

**Resposta (200):**
```json
{
  "success": true,
  "data": { "id": 1, "nome_fantasia": "Loja Atualizada", "..." : "..." },
  "message": "Registro atualizado com sucesso"
}
```

---

### DELETE /registros/{id}

Remove um lojista. Requer token. Só remove registros do próprio usuário.

```bash
curl -X DELETE http://localhost:8090/registros/1 \
  -H "Authorization: Bearer {token}"
```

**Resposta (200):**
```json
{
  "success": true,
  "message": "Registro removido com sucesso"
}
```

---

## Padrão de Respostas

**Sucesso:**
```json
{ "success": true, "data": { ... }, "message": "..." }
```

**Erro:**
```json
{ "success": false, "message": "Descrição do erro" }
```

**Códigos HTTP:**

| Código | Situação |
|--------|----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Dados inválidos / campo obrigatório ausente / JSON malformado |
| 401 | Não autenticado / token inválido ou expirado |
| 404 | Registro não encontrado |
| 500 | Erro interno |

---

## Autenticação

Todos os endpoints exceto `POST /login` requerem o header:

```
Authorization: Bearer {token}
```

O token é válido por **1 hora** após o login. Use `POST /logout` para invalidá-lo antes do vencimento.

Após **5 tentativas consecutivas** com senha errada, a conta é bloqueada por **15 minutos**.

---

## Campos do Lojista (26 campos — 6 etapas)

| Campo | Tipo | Etapa | Obrigatório |
|-------|------|-------|-------------|
| `tipo_pessoa` | string | 1 — Identificação | Sim |
| `cnpj` | string | 1 | — |
| `razao_social` | string | 1 | — |
| `nome_fantasia` | string | 1 | — |
| `data_abertura` | date (YYYY-MM-DD) | 1 | — |
| `capital_social` | decimal | 1 | — |
| `regime_tributario` | string | 1 | — |
| `inscricao_estadual` | string | 1 | — |
| `resp_nome` | string | 1 — Responsável Legal | Sim |
| `resp_cpf` | string | 1 | — |
| `resp_email` | string | 1 | Sim |
| `resp_telefone` | string | 1 | — |
| `segmento` | string | 2 — Segmento & Produto | — |
| `descricao_produtos` | text | 2 | — |
| `origem_produtos` | string | 2 | — |
| `produtos_restritos` | boolean | 2 | — |
| `possui_loja` | boolean | 3 — Estrutura & Logística | — |
| `possui_estoque` | boolean | 3 | — |
| `estoque_cep` | string | 3 | — |
| `estoque_endereco` | string | 3 | — |
| `logistica_envio` | string | 3 | — |
| `emissao_nf` | string | 4 — Financeiro | — |
| `banco` | string | 4 | — |
| `agencia` | string | 4 | — |
| `conta` | string | 4 | — |
| `tipo_conta` | string | 4 | — |
| `volume_pedidos` | string | 5 — Estratégia | — |
| `aceite_termos` | boolean | 6 — Aceite | Sim |
| `aceite_veracidade` | boolean | 6 | Sim |

---

## Variáveis de Ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `ALLOWED_ORIGINS` | `*` | Origens permitidas no header `Access-Control-Allow-Origin`. Em produção, substitua por domínio específico. |

**Exemplo:**
```bash
ALLOWED_ORIGINS=https://meusite.com /usr/bin/php8.3 -S 0.0.0.0:8090 -t public/
```

---

## Testes

Consulte o **[MANUAL_DE_TESTES.md](MANUAL_DE_TESTES.md)** para o guia completo com 34 cenários de teste (funcionais + segurança OWASP), comandos cURL prontos e critérios de aceite.

**Resumo rápido:**

```bash
# Testes funcionais — importe no Postman:
buscabusca.postman_collection.json

# Testes de segurança OWASP automatizados (45 checks):
php security_analyzer.php http://localhost:8090
```

---

## Estrutura do Projeto

```
buscabusca/
├── app/
│   ├── model/
│   │   ├── Usuario.php              # TRecord — autenticação, lockout, logout
│   │   └── Lojista.php              # TRecord — recurso principal (26 campos)
│   └── service/
│       ├── AuthService.php          # login, logout, validação de token
│       ├── RegistroService.php      # CRUD lojistas (filtrado por user_id)
│       └── LogService.php           # logging de eventos de segurança
├── config/
│   ├── buscabusca.php               # config conexão SQLite (Adianti)
│   └── buscabusca.xml               # config referência
├── database/
│   ├── buscabusca.db                # banco SQLite
│   └── migrate_security.php         # migration OWASP (executar uma vez)
├── public/
│   └── index.php                    # entry point + router REST
├── vendor/                          # dependências Composer
├── .htaccess                        # rewrite Apache
├── composer.json
├── security_analyzer.php            # analisador OWASP automatizado (45 checks)
├── README.md
├── OWASP_ANALISE.md                 # análise de vulnerabilidades
├── PLANO_DE_ACAO.md                 # plano de implementação
├── PROCESSO.md                      # diário completo do projeto
└── buscabusca.postman_collection.json
```
