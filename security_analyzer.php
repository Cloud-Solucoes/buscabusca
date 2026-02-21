#!/usr/bin/env php
<?php

/**
 * BuscaBusca — Analisador de Segurança OWASP Top 10 2021
 *
 * Verifica as 9 vulnerabilidades identificadas na análise:
 *   A01 Broken Access Control
 *   A02 Cryptographic Failures
 *   A04 Insecure Design
 *   A05 Security Misconfiguration
 *   A07 Authentication Failures
 *   A08 Integrity Failures
 *   A09 Logging & Monitoring Failures
 *
 * Uso:
 *   php security_analyzer.php [base_url]
 *   php security_analyzer.php http://localhost:8090
 *
 * O servidor deve estar rodando antes de executar.
 */

// ---------------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------------

$BASE_URL = rtrim($argv[1] ?? 'http://localhost:8090', '/');
$DB_PATH  = __DIR__ . '/database/buscabusca.db';
$EMAIL    = 'admin@buscabusca.com';
$SENHA    = '123456';

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

const C_RESET  = "\033[0m";
const C_BOLD   = "\033[1m";
const C_RED    = "\033[31m";
const C_GREEN  = "\033[32m";
const C_YELLOW = "\033[33m";
const C_CYAN   = "\033[36m";
const C_GRAY   = "\033[90m";

function section(string $text): void
{
    echo "\n" . C_BOLD . C_CYAN . "═══ {$text} " . str_repeat('═', max(0, 60 - strlen($text))) . C_RESET . "\n";
}

function check(string $label, bool $passed, string $detail = ''): void
{
    $icon   = $passed ? C_GREEN . '  PASS' : C_RED . '  FAIL';
    $detail = $detail ? C_GRAY . "  → {$detail}" : '';
    echo "  {$icon}" . C_RESET . "  {$label}{$detail}" . C_RESET . "\n";
}

function warn(string $label, string $detail = ''): void
{
    $detail = $detail ? C_GRAY . "  → {$detail}" : '';
    echo "  " . C_YELLOW . "  WARN" . C_RESET . "  {$label}{$detail}" . C_RESET . "\n";
}

function info(string $text): void
{
    echo C_GRAY . "       {$text}" . C_RESET . "\n";
}

// ---------------------------------------------------------------------------
// HTTP helper
// ---------------------------------------------------------------------------

/**
 * @return array{status: int, body: array, headers: array<string,string>}
 */
function request(string $method, string $url, array $body = [], ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $reqHeaders = ['Content-Type: application/json'];
    if ($token) {
        $reqHeaders[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'body' => [], 'headers' => []];
    }

    // Parse response headers
    $rawHeaders = substr($raw, 0, $hSize);
    $rawBody    = substr($raw, $hSize);
    $headers    = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }

    $decoded = json_decode($rawBody, true);

    return [
        'status'  => $status,
        'body'    => is_array($decoded) ? $decoded : [],
        'headers' => $headers,
        'raw'     => $rawBody,
    ];
}

function rawRequest(string $method, string $url, string $rawBody, ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $reqHeaders = ['Content-Type: application/json'];
    if ($token) {
        $reqHeaders[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawBodyOut = $raw ? substr($raw, $hSize) : '';
    $headers    = [];
    if ($raw) {
        foreach (explode("\r\n", substr($raw, 0, $hSize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
    }

    return [
        'status'  => $status,
        'body'    => json_decode($rawBodyOut, true) ?? [],
        'headers' => $headers,
        'raw'     => $rawBodyOut,
    ];
}

// ---------------------------------------------------------------------------
// DB helper
// ---------------------------------------------------------------------------

function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        global $DB_PATH;
        $pdo = new PDO("sqlite:{$DB_PATH}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

// ---------------------------------------------------------------------------
// Resultado global
// ---------------------------------------------------------------------------

$results = [];   // ['owasp' => string, 'label' => string, 'passed' => bool]

function record(string $owasp, string $label, bool $passed): void
{
    global $results;
    $results[] = compact('owasp', 'label', 'passed');
}

// ---------------------------------------------------------------------------
// PREFLIGHT — API disponível?
// ---------------------------------------------------------------------------

section('PREFLIGHT — Verificando API');

$ping = request('GET', "{$BASE_URL}/");
if ($ping['status'] === 0) {
    echo C_RED . "\n  [ERRO] API inacessível em {$BASE_URL}\n" . C_RESET;
    echo "  Inicie o servidor antes de rodar o analisador:\n";
    echo C_GRAY . "    /usr/bin/php8.3 -S 0.0.0.0:8090 -t public/\n\n" . C_RESET;
    exit(1);
}

info("API respondendo em {$BASE_URL} (HTTP {$ping['status']})");

// Login inicial para obter token válido
$loginRes = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => $SENHA]);
if (!($loginRes['body']['success'] ?? false)) {
    echo C_RED . "\n  [ERRO] Login inicial falhou — verifique credenciais e migration.\n" . C_RESET;
    exit(1);
}

$token     = $loginRes['body']['data']['token'];
$userId    = $loginRes['body']['data']['usuario']['id'];
info("Login OK  (usuario_id={$userId}, token={$token})");

// ---------------------------------------------------------------------------
// A02 — Cryptographic Failures
// ---------------------------------------------------------------------------

section('A02 — Cryptographic Failures');

// Verificar formato do hash da senha no banco
$row = db()->query("SELECT senha FROM usuarios WHERE email = '{$EMAIL}'")->fetch(PDO::FETCH_ASSOC);
$hash = $row['senha'] ?? '';

$isBcrypt = str_starts_with($hash, '$2y$');
$isSha256 = (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
check('Senha armazenada em bcrypt (não SHA-256)', $isBcrypt, $isBcrypt ? "hash={$hash}" : "hash={$hash}");
record('A02', 'Bcrypt no banco', $isBcrypt);

// Verificar cost do bcrypt
if ($isBcrypt) {
    preg_match('/^\$2y\$(\d+)\$/', $hash, $m);
    $cost = (int)($m[1] ?? 0);
    $costOk = $cost >= 12;
    check("Bcrypt cost >= 12 (atual: {$cost})", $costOk);
    record('A02', 'Bcrypt cost >= 12', $costOk);
}

// Verificar formato do token (hex aleatório, não derivado de email)
$tokenLength = strlen($token);
$isHex64     = (bool) preg_match('/^[a-f0-9]{64}$/', $token);
check('Token em hex puro de 64 chars (bin2hex(random_bytes(32)))', $isHex64, "len={$tokenLength}");
record('A02', 'Token criptograficamente seguro', $isHex64);

// Verificar expiração de 1 hora (não 24)
$row = db()->query("SELECT token_expira_em FROM usuarios WHERE email = '{$EMAIL}'")->fetch(PDO::FETCH_ASSOC);
$expira = $row['token_expira_em'] ?? '';
$hoursLeft = ($expira ? (strtotime($expira) - time()) / 3600 : 0);
$is1h = $hoursLeft > 0 && $hoursLeft <= 1.1;
check("Expiração do token <= 1 hora (atual: " . number_format($hoursLeft, 1) . "h)", $is1h, $expira);
record('A02', 'Expiração 1 hora', $is1h);

// ---------------------------------------------------------------------------
// A05 — Security Misconfiguration
// ---------------------------------------------------------------------------

section('A05 — Security Misconfiguration');

// Verificar security headers em uma resposta qualquer
$res = request('GET', "{$BASE_URL}/registros", [], $token);
$h   = $res['headers'];

$secHeaders = [
    'x-content-type-options' => ['nosniff',       'X-Content-Type-Options: nosniff'],
    'x-frame-options'        => ['deny',           'X-Frame-Options: DENY'],
    'x-xss-protection'       => ['1; mode=block',  'X-XSS-Protection: 1; mode=block'],
    'referrer-policy'        => ['no-referrer',    'Referrer-Policy: no-referrer'],
    'cache-control'          => ['no-store',       'Cache-Control: no-store'],
];

foreach ($secHeaders as $name => [$expected, $label]) {
    $actual = $h[$name] ?? '';
    $passed = strtolower($actual) === strtolower($expected);
    check($label, $passed, $passed ? 'presente' : "ausente (recebido: '{$actual}')");
    record('A05', $label, $passed);
}

$csp = $h['content-security-policy'] ?? '';
$hasCsp = str_contains(strtolower($csp), "default-src 'none'");
check("Content-Security-Policy: default-src 'none'", $hasCsp, $hasCsp ? 'presente' : "ausente (recebido: '{$csp}')");
record('A05', 'Content-Security-Policy', $hasCsp);

// Verificar que rota inexistente não vaza método+URI
$notFound = request('GET', "{$BASE_URL}/rota-inexistente");
$msg      = $notFound['body']['message'] ?? '';
$noLeak   = !str_contains($msg, 'GET') && !str_contains($msg, '/rota-inexistente');
check("Rota 404 sem leak de método/URI", $noLeak, "mensagem: '{$msg}'");
record('A05', '404 sem information disclosure', $noLeak);

// Verificar que erros internos não vazam stack/exception
// Vamos forçar um erro de autenticação e ver se a mensagem é genérica
$badAuth = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => 'senha_errada']);
$errMsg  = $badAuth['body']['message'] ?? '';
$noExcLeak = !str_contains(strtolower($errMsg), 'exception')
          && !str_contains(strtolower($errMsg), 'stack')
          && !str_contains(strtolower($errMsg), 'line ')
          && !str_contains(strtolower($errMsg), '.php');
check("Mensagem de erro não vaza detalhes internos", $noExcLeak, "mensagem: '{$errMsg}'");
record('A05', 'Sem exception leak', $noExcLeak);

// ---------------------------------------------------------------------------
// A07 — Authentication Failures
// ---------------------------------------------------------------------------

section('A07 — Authentication Failures');

// 1. Login sem credenciais → 400
$r = request('POST', "{$BASE_URL}/login", []);
check('POST /login sem body retorna 400', $r['status'] === 400, "HTTP {$r['status']}");
record('A07', 'Login sem credenciais -> 400', $r['status'] === 400);

// 2. Login com senha errada → 401
$r = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => 'errada']);
check('Login com senha errada retorna 401', $r['status'] === 401, "HTTP {$r['status']}");
record('A07', 'Senha errada -> 401', $r['status'] === 401);

// 3. Coluna tentativas_login existe no banco
$cols = db()->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_COLUMN, 1);
$hasTentativas = in_array('tentativas_login', $cols);
$hasBloqueado  = in_array('bloqueado_ate', $cols);
check('Coluna tentativas_login existe em usuarios', $hasTentativas);
check('Coluna bloqueado_ate existe em usuarios', $hasBloqueado);
record('A07', 'Schema de lockout presente', $hasTentativas && $hasBloqueado);

// 4. Testar lockout: fazer 5 tentativas erradas e verificar bloqueio na 6ª
//    Reseta o estado antes de começar para ser idempotente
db()->exec("UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE email = '{$EMAIL}'");
info("Contadores resetados — iniciando teste de brute-force (5 tentativas)...");

$WRONG = 'senha_errada_lockout_test';
for ($i = 1; $i <= 5; $i++) {
    request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => $WRONG]);
}

$r6th = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => $WRONG]);
$lockoutMsg = $r6th['body']['message'] ?? '';
$locked6th  = str_contains(strtolower($lockoutMsg), 'bloqueado') || str_contains(strtolower($lockoutMsg), 'temporar');
check('Conta bloqueada após 5 tentativas (6ª retorna mensagem de lockout)', $locked6th, "'{$lockoutMsg}'");
record('A07', 'Lockout após 5 tentativas', $locked6th);

// Verificar que bloqueio está registrado no banco
$row = db()->query("SELECT tentativas_login, bloqueado_ate FROM usuarios WHERE email = '{$EMAIL}'")->fetch(PDO::FETCH_ASSOC);
$dbLocked = !empty($row['bloqueado_ate']) && strtotime($row['bloqueado_ate']) > time();
check('Campo bloqueado_ate preenchido no banco com data futura', $dbLocked, $row['bloqueado_ate'] ?? 'NULL');
record('A07', 'Lockout persistido no banco', $dbLocked);

// Resetar lockout para não afetar testes seguintes
db()->exec("UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE email = '{$EMAIL}'");
info("Lockout resetado — continuando testes...");

// Obter novo token após reset
$freshLogin = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => $SENHA]);
$token = $freshLogin['body']['data']['token'] ?? $token;

// 5. Logout — token inválido após POST /logout
$logoutRes = request('POST', "{$BASE_URL}/logout", [], $token);
$logoutOk  = ($logoutRes['body']['success'] ?? false) && $logoutRes['status'] === 200;
check('POST /logout retorna sucesso (200)', $logoutOk, "HTTP {$logoutRes['status']} — " . ($logoutRes['body']['message'] ?? ''));
record('A07', 'Rota /logout existe e funciona', $logoutOk);

// 6. Token deve estar inválido após logout
$afterLogout = request('GET', "{$BASE_URL}/registros", [], $token);
$tokenInvalidated = $afterLogout['status'] === 401;
check('Token inválido após logout (GET /registros retorna 401)', $tokenInvalidated, "HTTP {$afterLogout['status']}");
record('A07', 'Token revogado após logout', $tokenInvalidated);

// Renovar token para próximos testes
$freshLogin = request('POST', "{$BASE_URL}/login", ['email' => $EMAIL, 'senha' => $SENHA]);
$token = $freshLogin['body']['data']['token'] ?? '';

// 7. Acesso sem token → 401
$noToken = request('GET', "{$BASE_URL}/registros");
check('GET /registros sem token retorna 401', $noToken['status'] === 401, "HTTP {$noToken['status']}");
record('A07', 'Rota protegida sem token -> 401', $noToken['status'] === 401);

// 8. Token inválido (string aleatória) → 401
$fakeToken = bin2hex(random_bytes(32));
$badToken  = request('GET', "{$BASE_URL}/registros", [], $fakeToken);
check('GET /registros com token falso retorna 401', $badToken['status'] === 401, "HTTP {$badToken['status']}");
record('A07', 'Token inválido -> 401', $badToken['status'] === 401);

// ---------------------------------------------------------------------------
// A01 — Broken Access Control
// ---------------------------------------------------------------------------

section('A01 — Broken Access Control');

// Coluna user_id existe em lojistas
$cols = db()->query("PRAGMA table_info(lojistas)")->fetchAll(PDO::FETCH_COLUMN, 1);
$hasUserId = in_array('user_id', $cols);
check('Coluna user_id existe em lojistas', $hasUserId);
record('A01', 'Schema user_id presente', $hasUserId);

// Criar um registro para testar ownership
$payload = [
    'tipo_pessoa'      => 'PJ',
    'resp_nome'        => 'Teste Analyzer',
    'resp_email'       => 'analyzer@test.com',
    'aceite_termos'    => true,
    'aceite_veracidade'=> true,
];
$createRes = request('POST', "{$BASE_URL}/registros", $payload, $token);
$createdId = $createRes['body']['data']['id'] ?? null;
$createdUserId = $createRes['body']['data']['user_id'] ?? null;

check('POST /registros retorna user_id no response', $createdUserId !== null, "user_id={$createdUserId}");
record('A01', 'user_id retornado no response', $createdUserId !== null);

if ($createdId) {
    // Verificar no banco se user_id foi gravado corretamente
    $row = db()->query("SELECT user_id FROM lojistas WHERE id = {$createdId}")->fetch(PDO::FETCH_ASSOC);
    $dbUserId = (int)($row['user_id'] ?? 0);
    $ownershipSaved = $dbUserId === $userId;
    check("user_id gravado no banco = {$userId} (usuario autenticado)", $ownershipSaved, "banco: user_id={$dbUserId}");
    record('A01', 'Ownership gravado no banco', $ownershipSaved);

    // Tentar acessar o mesmo registro com token de outro usuário (token falso)
    $fakeToken2 = bin2hex(random_bytes(32));
    $stolen     = request('PUT', "{$BASE_URL}/registros/{$createdId}", ['resp_nome' => 'hacker'], $fakeToken2);
    check('PUT com token inválido retorna 401 (não permite acesso ao registro)', $stolen['status'] === 401, "HTTP {$stolen['status']}");
    record('A01', 'Token inválido não acessa registro de outro usuário', $stolen['status'] === 401);

    // GET /registros lista só registros do usuário — verificar que o recém-criado aparece
    $listRes = request('GET', "{$BASE_URL}/registros", [], $token);
    $found = false;
    foreach ($listRes['body']['data'] ?? [] as $item) {
        if ((int)($item['id'] ?? 0) === $createdId) {
            $found = true;
            break;
        }
    }
    check("Registro criado aparece na listagem do usuário dono", $found, "id={$createdId}");
    record('A01', 'GET /registros filtra por user_id', $found);

    // Limpeza
    request('DELETE', "{$BASE_URL}/registros/{$createdId}", [], $token);
    info("Registro id={$createdId} removido (limpeza)");
}

// Verificar que lojista com user_id = NULL não aparece na listagem (dados legados)
$nullRows = db()->query("SELECT COUNT(*) FROM lojistas WHERE user_id IS NULL")->fetchColumn();
if ($nullRows > 0) {
    $listRes = request('GET', "{$BASE_URL}/registros", [], $token);
    $total = count($listRes['body']['data'] ?? []);
    // Não podemos garantir que zero rows NULL aparecem sem saber o total esperado
    // Mas podemos verificar que a query filtra — se há rows NULL e a API retorna menos ou igual ao total sem NULL
    $allRows = db()->query("SELECT COUNT(*) FROM lojistas")->fetchColumn();
    $rowsWithUser = db()->query("SELECT COUNT(*) FROM lojistas WHERE user_id = {$userId}")->fetchColumn();
    $filterWorks = $total <= $rowsWithUser;
    check("GET /registros não expõe registros com user_id NULL ({$nullRows} rows legadas no banco)", $filterWorks, "API retornou {$total}, esperado <= {$rowsWithUser}");
    record('A01', 'Registros legados (user_id NULL) isolados', $filterWorks);
} else {
    info("Nenhum registro com user_id NULL no banco");
}

// ---------------------------------------------------------------------------
// A04 — Insecure Design
// ---------------------------------------------------------------------------

section('A04 — Insecure Design');

// POST sem campos obrigatórios deve retornar 400
$camposObrigatorios = ['tipo_pessoa', 'aceite_termos', 'aceite_veracidade', 'resp_nome', 'resp_email'];

foreach ($camposObrigatorios as $campo) {
    $bodyIncompleto = [
        'tipo_pessoa'       => 'PJ',
        'resp_nome'         => 'Teste',
        'resp_email'        => 'x@x.com',
        'aceite_termos'     => true,
        'aceite_veracidade' => true,
    ];
    unset($bodyIncompleto[$campo]);

    $r = request('POST', "{$BASE_URL}/registros", $bodyIncompleto, $token);
    $is400 = $r['status'] === 400;
    check("POST /registros sem '{$campo}' retorna 400", $is400, "HTTP {$r['status']} — " . ($r['body']['message'] ?? ''));
    record('A04', "Validação campo obrigatório: {$campo}", $is400);
}

// POST com user_id no body não deve ser aceito como owner (segurança: user_id vem do token)
$payloadComUserId = [
    'tipo_pessoa'       => 'PJ',
    'resp_nome'         => 'Inject Test',
    'resp_email'        => 'inject@test.com',
    'aceite_termos'     => true,
    'aceite_veracidade' => true,
    'user_id'           => 99999,  // tentativa de injetar user_id estranho
];
$injectRes   = request('POST', "{$BASE_URL}/registros", $payloadComUserId, $token);
$injectedId  = $injectRes['body']['data']['id'] ?? null;
if ($injectedId) {
    $row = db()->query("SELECT user_id FROM lojistas WHERE id = {$injectedId}")->fetch(PDO::FETCH_ASSOC);
    $actualUserId = (int)($row['user_id'] ?? 0);
    $injectionBlocked = $actualUserId !== 99999;
    check("user_id do body (99999) ignorado — gravado como {$actualUserId} (token owner)", $injectionBlocked, "banco: user_id={$actualUserId}");
    record('A04', 'user_id injection bloqueado', $injectionBlocked);
    request('DELETE', "{$BASE_URL}/registros/{$injectedId}", [], $token);
    info("Registro injetado id={$injectedId} removido (limpeza)");
}

// ---------------------------------------------------------------------------
// A08 — Integrity Failures
// ---------------------------------------------------------------------------

section('A08 — Integrity Failures');

// Body com JSON malformado deve retornar 400
$malformed = [
    '{sem fechar'          => '{sem fechar',
    '{"chave": sem aspas}' => '{"chave": sem aspas}',
    'texto puro'           => 'texto puro',
    '[1, 2,'               => '[1, 2,',
];

foreach ($malformed as $label => $raw) {
    $r = rawRequest('POST', "{$BASE_URL}/registros", $raw, $token);
    $is400 = $r['status'] === 400;
    check("JSON malformado '{$label}' retorna 400", $is400, "HTTP {$r['status']}");
    record('A08', "JSON malformado rejeitado: {$label}", $is400);
}

// Body vazio deve ser aceito (tratado como [])
$emptyBody = rawRequest('POST', "{$BASE_URL}/registros", '', $token);
$emptyOk = in_array($emptyBody['status'], [400, 422]);  // 400 por validação de campos
check("Body vazio retorna 400 por validação (não 500)", $emptyOk, "HTTP {$emptyBody['status']}");
record('A08', 'Body vazio não causa 500', $emptyOk);

// ---------------------------------------------------------------------------
// A09 — Logging & Monitoring
// ---------------------------------------------------------------------------

section('A09 — Logging & Monitoring');

// Verificar se LogService.php existe
$logServiceExists = file_exists(__DIR__ . '/app/service/LogService.php');
check('LogService.php existe', $logServiceExists);
record('A09', 'LogService presente', $logServiceExists);

if ($logServiceExists) {
    $src = file_get_contents(__DIR__ . '/app/service/LogService.php');

    // Verificar que tem os 3 níveis
    $hasInfo    = str_contains($src, 'public static function info');
    $hasWarning = str_contains($src, 'public static function warning');
    $hasError   = str_contains($src, 'public static function error');
    check('LogService implementa info(), warning(), error()', $hasInfo && $hasWarning && $hasError);
    record('A09', 'LogService com 3 níveis', $hasInfo && $hasWarning && $hasError);

    // Verificar sanitização de campos sensíveis
    $hasSanitize = str_contains($src, 'sensitiveFields') || str_contains($src, 'sanitize');
    check('LogService sanitiza campos sensíveis antes de logar', $hasSanitize);
    record('A09', 'Sanitização de dados sensíveis no log', $hasSanitize);
}

// Verificar que AuthService usa LogService
$authSrc = file_get_contents(__DIR__ . '/app/service/AuthService.php');
$authLogs = substr_count($authSrc, 'LogService::');
check("AuthService usa LogService ({$authLogs} chamadas)", $authLogs >= 4, "{$authLogs} chamadas");
record('A09', 'AuthService instrumentado com LogService', $authLogs >= 4);

// Verificar que RegistroService usa LogService
$regSrc = file_get_contents(__DIR__ . '/app/service/RegistroService.php');
$regLogs = substr_count($regSrc, 'LogService::');
check("RegistroService usa LogService ({$regLogs} chamadas)", $regLogs >= 6, "{$regLogs} chamadas");
record('A09', 'RegistroService instrumentado com LogService', $regLogs >= 6);

// Verificar que $e->getMessage() não aparece em respostas ao cliente.
// É aceitável em LogService::error() (log interno), mas não em arrays 'message' retornados.
// Padrão: 'message' => ... getMessage() na mesma linha → leak para cliente.
$authNoLeak = !preg_match("/'message'\s*=>\s*[^;]*getMessage\(\)/", $authSrc);
$regNoLeak  = !preg_match("/'message'\s*=>\s*[^;]*getMessage\(\)/", $regSrc);
check('AuthService não expõe $e->getMessage() em respostas ao cliente', $authNoLeak);
check('RegistroService não expõe $e->getMessage() em respostas ao cliente', $regNoLeak);
record('A09', 'AuthService sem exception leak', $authNoLeak);
record('A09', 'RegistroService sem exception leak', $regNoLeak);

// ---------------------------------------------------------------------------
// SUMÁRIO FINAL
// ---------------------------------------------------------------------------

section('SUMÁRIO');

$byOwasp = [];
foreach ($results as $r) {
    $byOwasp[$r['owasp']][] = $r;
}

ksort($byOwasp);

$totalPass = 0;
$totalFail = 0;

foreach ($byOwasp as $owasp => $checks) {
    $pass = count(array_filter($checks, fn($c) => $c['passed']));
    $fail = count($checks) - $pass;
    $totalPass += $pass;
    $totalFail += $fail;

    $status = $fail === 0
        ? C_GREEN . '  SEGURO  '
        : C_RED   . ' VULNERÁVEL';

    $bar = str_repeat('█', $pass) . str_repeat('░', $fail);

    echo C_BOLD . "  {$owasp}" . C_RESET
       . "  {$status}" . C_RESET
       . C_GRAY . "  {$bar}  {$pass}/" . count($checks) . C_RESET . "\n";

    if ($fail > 0) {
        foreach ($checks as $c) {
            if (!$c['passed']) {
                echo C_RED . "          ✗ {$c['label']}" . C_RESET . "\n";
            }
        }
    }
}

$total = $totalPass + $totalFail;
$pct   = $total > 0 ? round($totalPass / $total * 100) : 0;

echo "\n";
echo C_BOLD . "  Resultado: {$totalPass}/{$total} verificações passaram ({$pct}%)" . C_RESET . "\n";

if ($totalFail === 0) {
    echo C_GREEN . C_BOLD . "\n  ✓ Todas as verificações OWASP passaram.\n" . C_RESET . "\n";
} else {
    echo C_RED . C_BOLD . "\n  ✗ {$totalFail} verificação(ões) falharam — revisar itens acima.\n" . C_RESET . "\n";
}
