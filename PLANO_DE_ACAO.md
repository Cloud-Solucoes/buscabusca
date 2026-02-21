# Plano de Ação — BuscaBusca API REST (Adianti + SQLite)

## Contexto

Teste técnico que exige a construção de uma API REST em PHP usando o Adianti Framework com banco SQLite. O recurso principal é o cadastro de Lojistas (26 campos, 6 etapas). A API precisa de autenticação por token e CRUD completo em `/registros`. Não há Adianti instalado no sistema — instalação será feita via Composer 2.9.4 disponível.

---

## Estrutura Final do Projeto

```
/var/www/html/buscabusca/
├── app/
│   ├── model/
│   │   ├── Usuario.php          # TRecord - autenticação
│   │   └── Lojista.php          # TRecord - registro principal
│   └── service/
│       ├── AuthService.php      # login, geração e validação de token
│       └── RegistroService.php  # CRUD lojistas
├── config/
│   └── buscabusca.php           # config PHP para TDatabase (Adianti)
├── database/
│   └── buscabusca.db            # arquivo SQLite
├── public/
│   └── index.php                # entry point + router REST
├── .htaccess                    # rewrite rules
├── composer.json
├── vendor/
├── README.md
├── buscabusca.postman_collection.json
└── PROCESSO.md
```

---

## Fases de Implementação

### Fase 1 — Setup do Ambiente ✅

1. Inicializar `composer.json` no projeto
2. Instalar Adianti Framework via Composer: `composer require plenatech/adianti_framework`
   - **Nota:** O package no Packagist é `plenatech/adianti_framework` (v8.2.0.1), não `adianti/framework`
3. Criar estrutura de pastas (`app/model`, `app/service`, `config`, `database`, `public`)
4. Criar `.htaccess` com rewrite para `public/index.php`
5. Criar `config/buscabusca.php` com configuração SQLite para o TConnection

**Config PHP (Adianti 8.x usa PHP array, não XML):**
```php
<?php
return [
    'host'  => '',
    'port'  => '',
    'name'  => '/var/www/html/buscabusca/database/buscabusca.db',
    'user'  => '',
    'pass'  => '',
    'type'  => 'sqlite',
    'prep'  => '1'
];
```

---

### Fase 2 — Banco de Dados ✅

6. Criar o arquivo SQLite (`database/buscabusca.db`)
7. Executar SQL para criar tabelas `usuarios` e `lojistas`
8. Inserir usuário de teste seed:
   - email: `admin@buscabusca.com`
   - senha: `123456` (armazenada como hash sha256)

---

### Fase 3 — Models (Adianti TRecord) ✅

9. `app/model/Usuario.php` — estende `TRecord`, tabela `usuarios`
   - Métodos: `findByEmail()`, `generateToken()`, `validateToken()`

10. `app/model/Lojista.php` — estende `TRecord`, tabela `lojistas`
    - 26 campos mapeados
    - Métodos: `toArray($filter_attributes = null)` para serialização JSON
    - **Nota:** Assinatura deve ser compatível com `TRecord::toArray($filter_attributes = null)`

**Alias globais necessários (Adianti 8.x + Composer PSR-4):**
```php
class_alias('Adianti\Database\TRecord',      'TRecord');
class_alias('Adianti\Database\TTransaction', 'TTransaction');
class_alias('Adianti\Database\TRepository',  'TRepository');
class_alias('Adianti\Database\TCriteria',    'TCriteria');
class_alias('Adianti\Database\TFilter',      'TFilter');
```

---

### Fase 4 — Services ✅

11. `app/service/AuthService.php`
    - `login(email, senha)` → retorna token ou erro
    - `validateToken(token)` → retorna usuário ou null

12. `app/service/RegistroService.php`
    - `listar()` → GET /registros
    - `criar(dados)` → POST /registros
    - `atualizar(id, dados)` → PUT /registros/{id}
    - `deletar(id)` → DELETE /registros/{id}
    - `exists(id)` → verifica se registro existe antes de atualizar/deletar

---

### Fase 5 — Router REST (public/index.php) ✅

13. Entry point com roteamento manual simples:
    - Ler `$_SERVER['REQUEST_METHOD']` e `$_SERVER['REQUEST_URI']`
    - Extrair segmentos da URL e parâmetros
    - Aplicar middleware de token em todos exceto `POST /login`
    - Chamar service correspondente
    - Retornar JSON com `header('Content-Type: application/json')`

**Mapeamento de rotas:**
| Método | URI | Service |
|--------|-----|---------|
| POST | /login | AuthService::login |
| GET | /registros | RegistroService::listar |
| POST | /registros | RegistroService::criar |
| PUT | /registros/{id} | RegistroService::atualizar |
| DELETE | /registros/{id} | RegistroService::deletar |

---

### Fase 6 — Documentação e Postman ✅

14. `README.md` com:
    - Requisitos e instalação
    - Como executar (PHP built-in server PHP 8.3)
    - Lista de endpoints com exemplos cURL
    - Usuário de teste

15. `buscabusca.postman_collection.json` com todos os endpoints e scripts de teste

16. `PROCESSO.md` com etapas concluídas e decisões técnicas registradas

---

## Padrão de Respostas

**Sucesso (200/201):**
```json
{ "success": true, "data": { ... }, "message": "..." }
```

**Erro (400/401/404/500):**
```json
{ "success": false, "message": "Descrição do erro" }
```

**Header de autenticação:**
```
Authorization: Bearer {token}
```

---

## Como Executar

```bash
cd /var/www/html/buscabusca
composer install
/usr/bin/php8.3 -S 0.0.0.0:8090 -t public/
```

API disponível em `http://localhost:8090`

---

## Verificação Final ✅

- [x] `POST /login` retorna token com credenciais corretas
- [x] `POST /login` retorna 401 com credenciais erradas
- [x] `GET /registros` sem token retorna 401
- [x] `GET /registros` com token retorna lista JSON
- [x] `POST /registros` cria lojista e retorna 201
- [x] `PUT /registros/{id}` atualiza e retorna 200
- [x] `DELETE /registros/{id}` remove e retorna 200
- [x] `DELETE /registros/999` retorna 404
- [x] Coleção Postman importável e funcional
- [x] README com instruções claras de execução

---

## Arquivos Críticos

| Arquivo | Papel |
|---------|-------|
| `public/index.php` | Entry point + router |
| `app/model/Usuario.php` | Auth model (TRecord) |
| `app/model/Lojista.php` | Recurso principal (TRecord) |
| `app/service/AuthService.php` | Lógica de autenticação |
| `app/service/RegistroService.php` | CRUD registros |
| `config/buscabusca.php` | Conexão SQLite Adianti |
| `database/buscabusca.db` | Banco SQLite |
| `.htaccess` | Rewrite Apache |
| `README.md` | Instruções de execução |
| `buscabusca.postman_collection.json` | Coleção Postman |
