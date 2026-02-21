<?php
/**
 * BuscaBusca — Bridge para Shared Hosting (Locaweb)
 *
 * No shared hosting, o DocumentRoot nao aponta para public/.
 * Este arquivo verifica se o request e para um arquivo estatico
 * em public/ e o serve diretamente, ou encaminha para o API router.
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remover prefixo do subdiretorio
$uri = preg_replace('#^(/demos)?/buscabusca#', '', $uri);
$uri = $uri ?: '/';

// Verificar se e um arquivo estatico em public/
$publicFile = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($publicFile)) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));

    // Arquivos PHP: incluir diretamente (API router)
    if ($ext === 'php') {
        require $publicFile;
        exit;
    }

    // Arquivos estaticos: servir com Content-Type correto
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];

    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($publicFile));
    readfile($publicFile);
    exit;
}

// Tudo mais vai para o API router (public/index.php)
require __DIR__ . '/public/index.php';
