<?php

/**
 * BuscaBusca API REST — Entry Point & Router
 *
 * Rotas:
 *   POST   /login               → AuthService::login
 *   POST   /logout              → AuthService::logout
 *   GET    /registros           → RegistroService::listar
 *   POST   /registros           → RegistroService::criar
 *   PUT    /registros/{id}      → RegistroService::atualizar
 *   DELETE /registros/{id}      → RegistroService::deletar
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader Composer
require BASE_PATH . '/vendor/autoload.php';

// Configurar path dos arquivos de conexão Adianti
\Adianti\Database\TConnection::setConfigPath(BASE_PATH . '/config');

// Criar aliases globais para as classes Adianti (necessário para TRepository::is_subclass_of check)
class_alias('Adianti\Database\TRecord',      'TRecord');
class_alias('Adianti\Database\TTransaction', 'TTransaction');
class_alias('Adianti\Database\TRepository',  'TRepository');
class_alias('Adianti\Database\TCriteria',    'TCriteria');
class_alias('Adianti\Database\TFilter',      'TFilter');

// Carregar models e services manualmente (sem PSR-4 namespace)
require BASE_PATH . '/app/service/LogService.php';
require BASE_PATH . '/app/model/Usuario.php';
require BASE_PATH . '/app/model/Lojista.php';
require BASE_PATH . '/app/service/AuthService.php';
require BASE_PATH . '/app/service/RegistroService.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function jsonResponse(array $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'");
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['success' => false, 'message' => 'JSON malformado'], 400);
    }
    return is_array($data) ? $data : [];
}

function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

function requireAuth(): Usuario
{
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'Token não informado'], 401);
    }

    $authService = new AuthService;
    $usuario = $authService->validateToken($token);

    if (!$usuario) {
        jsonResponse(['success' => false, 'message' => 'Token inválido ou expirado'], 401);
    }

    return $usuario;
}

// ---------------------------------------------------------------------------
// Roteamento
// ---------------------------------------------------------------------------

$method = strtoupper($_SERVER['REQUEST_METHOD']);

// Extrair path da URI removendo base e query string
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Remover prefixo /buscabusca se executando como subdiretório
$uri = preg_replace('#^/buscabusca#', '', $uri);
$uri = $uri ?: '/';

// CORS — origem configurável via variável de ambiente
$allowedOrigin = getenv('ALLOWED_ORIGINS') ?: '*';

// Tratar OPTIONS (CORS preflight)
if ($method === 'OPTIONS') {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    http_response_code(204);
    exit;
}

// Headers CORS para todas as respostas
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// ---------------------------------------------------------------------------
// POST /login
// ---------------------------------------------------------------------------
if ($method === 'POST' && $uri === '/login') {
    $body = getRequestBody();

    $email = trim($body['email'] ?? '');
    $senha = $body['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        jsonResponse(['success' => false, 'message' => 'email e senha são obrigatórios'], 400);
    }

    $authService = new AuthService;
    $result = $authService->login($email, $senha);

    $status = $result['success'] ? 200 : 401;
    jsonResponse($result, $status);
}

// ---------------------------------------------------------------------------
// POST /logout
// ---------------------------------------------------------------------------
if ($method === 'POST' && $uri === '/logout') {
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'Token não informado'], 401);
    }

    $authService = new AuthService;
    $result = $authService->logout($token);

    $status = $result['success'] ? 200 : 401;
    jsonResponse($result, $status);
}

// ---------------------------------------------------------------------------
// GET /registros
// ---------------------------------------------------------------------------
if ($method === 'GET' && $uri === '/registros') {
    $usuario = requireAuth();

    $service = new RegistroService($usuario);
    $result = $service->listar();

    $status = $result['success'] ? 200 : 500;
    jsonResponse($result, $status);
}

// ---------------------------------------------------------------------------
// POST /registros
// ---------------------------------------------------------------------------
if ($method === 'POST' && $uri === '/registros') {
    $usuario = requireAuth();

    $dados = getRequestBody();

    $service = new RegistroService($usuario);
    $result = $service->criar($dados);

    if (!$result['success']) {
        $isValidation = $result['_validation_error'] ?? false;
        unset($result['_validation_error']);
        $status = $isValidation ? 400 : 500;
        jsonResponse($result, $status);
    }

    jsonResponse($result, 201);
}

// ---------------------------------------------------------------------------
// PUT /registros/{id}
// ---------------------------------------------------------------------------
if ($method === 'PUT' && preg_match('#^/registros/(\d+)$#', $uri, $matches)) {
    $usuario = requireAuth();

    $id   = (int) $matches[1];
    $dados = getRequestBody();

    $service = new RegistroService($usuario);
    $result = $service->atualizar($id, $dados);

    if (!$result['success']) {
        $status = ($result['_not_found'] ?? false) ? 404 : 500;
        unset($result['_not_found']);
        jsonResponse($result, $status);
    }

    jsonResponse($result, 200);
}

// ---------------------------------------------------------------------------
// DELETE /registros/{id}
// ---------------------------------------------------------------------------
if ($method === 'DELETE' && preg_match('#^/registros/(\d+)$#', $uri, $matches)) {
    $usuario = requireAuth();

    $id = (int) $matches[1];

    $service = new RegistroService($usuario);
    $result = $service->deletar($id);

    if (!$result['success']) {
        $status = ($result['_not_found'] ?? false) ? 404 : 500;
        unset($result['_not_found']);
        jsonResponse($result, $status);
    }

    jsonResponse($result, 200);
}

// ---------------------------------------------------------------------------
// Rota não encontrada
// ---------------------------------------------------------------------------
jsonResponse([
    'success' => false,
    'message' => 'Rota não encontrada',
], 404);
