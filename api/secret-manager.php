<?php
// api/secret-manager.php

final class SecretError extends RuntimeException {
    public string $reason;
    public function __construct(string $reason, string $message, int $code = 0, ?Throwable $prev = null) {
        parent::__construct($message, $code, $prev);
        $this->reason = $reason;
    }
}

final class SecretManager {
    // Путь к файлу секрета: ENV → $HOME → legacy
    public static function path(): string {
        $env = getenv('KONSTRUCTOUR_SECRET_FILE');
        if (is_string($env) && $env !== '') return $env;

        $home = getenv('HOME') ?: '';
        if ($home) {
            $candidate = rtrim($home, '/').'/konstructour/secrets/airtable.json';
            if (file_exists($candidate)) return $candidate;
        }

        return '/var/konstructour/secrets/airtable.json';
    }

    public static function load(): array {
        $p = self::path();
        if (!file_exists($p)) {
            throw new SecretError('file_missing', "Secret file not found: {$p}");
        }
        if (!is_readable($p)) {
            throw new SecretError('permission_denied', "Secret file not readable: {$p}");
        }
        $raw = @file_get_contents($p);
        if ($raw === false) {
            throw new SecretError('io_error', "Cannot read secret file: {$p}");
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['current']) || !array_key_exists('next', $j)) {
            throw new SecretError('bad_format', 'Secret JSON must contain {current,next}');
        }
        return $j;
    }

    // ТОЛЬКО чтение токена для запросов к Airtable
    public static function getAirtableToken(): string {
        $j = self::load();
        $tok = $j['current']['token'] ?? null;
        if (!is_string($tok) || !str_starts_with($tok, 'pat.')) {
            throw new SecretError('token_missing', 'Airtable PAT missing or invalid in current slot');
        }
        return $tok;
    }
    
    // Для локальной разработки - более мягкая валидация
    public static function getAirtableTokenForDev(): string {
        $j = self::load();
        $tok = $j['current']['token'] ?? null;
        if (!is_string($tok)) {
            throw new SecretError('token_missing', 'Airtable PAT missing in current slot');
        }
        return $tok;
    }

    // Сохранение next (через админ-эндпоинт, не из публичного кода)
    public static function setNextToken(string $pat): void {
        if (!preg_match('/^pat\.[A-Za-z0-9_\-]{20,}$/', $pat)) {
            throw new SecretError('invalid_pat', 'PAT must start with "pat." and be >= 20 chars after dot');
        }
        $j = file_exists(self::path()) ? (self::load()) : ['current'=>['token'=>null,'since'=>null],'next'=>['token'=>null,'since'=>null]];
        $j['next'] = ['token'=>$pat,'since'=>gmdate('c')];
        self::atomicSave($j);
    }

    // Промоушн next → current
    public static function promoteNext(): bool {
        $j = self::load();
        $next = $j['next']['token'] ?? null;
        if (!is_string($next) || !str_starts_with($next, 'pat.')) return false;
        $j['current'] = ['token'=>$next,'since'=>gmdate('c')];
        $j['next']    = ['token'=>null,'since'=>null];
        self::atomicSave($j);
        return true;
    }

    // Атомарная запись + права 600
    private static function atomicSave(array $j): void {
        $p   = self::path();
        $dir = dirname($p);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            throw new SecretError('permission_denied', "Cannot create dir: {$dir}");
        }
        $tmp = $p.'.tmp.'.bin2hex(random_bytes(6));
        $json = json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new SecretError('io_error', "Cannot write temp secret: {$tmp}");
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $p)) {
            @unlink($tmp);
            throw new SecretError('io_error', "Cannot replace secret file: {$p}");
        }
        @chmod($p, 0600);
    }
}
?>
