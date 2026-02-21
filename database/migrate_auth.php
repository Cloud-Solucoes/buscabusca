<?php
/**
 * Migration: Sistema de Autenticação
 * Adiciona campos de perfil e recuperação de senha na tabela usuarios.
 *
 * Execute uma vez via CLI:
 *   php database/migrate_auth.php
 */

$dbPath = __DIR__ . '/buscabusca.db';

if (!file_exists($dbPath)) {
    echo "ERRO: banco de dados não encontrado em {$dbPath}\n";
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = $pdo->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_COLUMN, 1);

$migrations = [
    'nome'                => "ALTER TABLE usuarios ADD COLUMN nome TEXT",
    'reset_token'         => "ALTER TABLE usuarios ADD COLUMN reset_token TEXT",
    'reset_token_expiry'  => "ALTER TABLE usuarios ADD COLUMN reset_token_expiry TEXT",
];

echo "Iniciando migration de autenticação...\n\n";

foreach ($migrations as $col => $sql) {
    if (in_array($col, $cols)) {
        echo "  [SKIP] coluna '{$col}' já existe\n";
    } else {
        $pdo->exec($sql);
        echo "  [OK]   coluna '{$col}' adicionada\n";
    }
}

echo "\nMigration auth concluída com sucesso.\n";
