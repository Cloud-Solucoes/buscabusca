<?php

/**
 * Migration de segurança OWASP — executar uma vez via CLI:
 *   php database/migrate_security.php
 *
 * Idempotente: verifica colunas antes de adicionar.
 */

define('BASE_PATH', dirname(__DIR__));

$dbPath = BASE_PATH . '/database/buscabusca.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    $hasColumn = function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        return false;
    };

    // -------------------------------------------------------------------------
    // usuarios — tentativas_login
    // -------------------------------------------------------------------------
    if (!$hasColumn('usuarios', 'tentativas_login')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN tentativas_login INTEGER DEFAULT 0");
        echo "[OK] Coluna tentativas_login adicionada em usuarios\n";
    } else {
        echo "[SKIP] tentativas_login já existe em usuarios\n";
    }

    // -------------------------------------------------------------------------
    // usuarios — bloqueado_ate
    // -------------------------------------------------------------------------
    if (!$hasColumn('usuarios', 'bloqueado_ate')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN bloqueado_ate TEXT");
        echo "[OK] Coluna bloqueado_ate adicionada em usuarios\n";
    } else {
        echo "[SKIP] bloqueado_ate já existe em usuarios\n";
    }

    // -------------------------------------------------------------------------
    // lojistas — user_id
    // -------------------------------------------------------------------------
    if (!$hasColumn('lojistas', 'user_id')) {
        $pdo->exec("ALTER TABLE lojistas ADD COLUMN user_id INTEGER");
        echo "[OK] Coluna user_id adicionada em lojistas\n";
    } else {
        echo "[SKIP] user_id já existe em lojistas\n";
    }

    // -------------------------------------------------------------------------
    // Migrar senha do seed user de SHA-256 para bcrypt
    // SHA-256('123456') = 8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92
    // -------------------------------------------------------------------------
    $sha256 = '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92';

    $stmt = $pdo->prepare("SELECT id, senha FROM usuarios WHERE senha = ?");
    $stmt->execute([$sha256]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $bcrypt = password_hash('123456', PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $upd->execute([$bcrypt, $row['id']]);
        echo "[OK] Senha do usuário id={$row['id']} migrada para bcrypt\n";
    }

    if (empty($rows)) {
        echo "[SKIP] Nenhuma senha SHA-256 encontrada para migrar\n";
    }

    echo "\nMigration concluída com sucesso.\n";

} catch (Exception $e) {
    echo "[ERRO] " . $e->getMessage() . "\n";
    exit(1);
}
