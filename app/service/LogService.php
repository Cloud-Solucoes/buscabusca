<?php

/**
 * Wrapper de logging de eventos de segurança sobre error_log().
 *
 * Formato: [BUSCABUSCA][LEVEL][EVENT] ip=X {context_json}
 *
 * Campos sensíveis removidos do contexto antes de logar.
 */
class LogService
{
    private static array $sensitiveFields = ['senha', 'password', 'token', 'secret'];

    private static function sanitize(array $context): array
    {
        foreach (self::$sensitiveFields as $field) {
            if (array_key_exists($field, $context)) {
                $context[$field] = '***';
            }
        }
        return $context;
    }

    private static function write(string $level, string $event, array $context): void
    {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $clean   = self::sanitize($context);
        $ctx     = json_encode($clean, JSON_UNESCAPED_UNICODE);
        error_log("[BUSCABUSCA][{$level}][{$event}] ip={$ip} {$ctx}");
    }

    public static function info(string $event, array $context = []): void
    {
        self::write('INFO', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('WARNING', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('ERROR', $event, $context);
    }
}
