<?php
// Airtable клиент с поддержкой батч-операций и retry

require_once __DIR__ . '/../secret-manager.php';
require_once __DIR__ . '/http_retry.php';

final class Airtable {
    private string $baseId;
    private string $token;

    public function __construct(string $baseId) {
        $this->baseId = $baseId;
        $this->token = SecretManager::getAirtableToken();
    }

    private function headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json'
        ];
    }

    public function list(string $table, array $params = []): array {
        $qs = $params ? ('?' . http_build_query($params)) : '';
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$table}{$qs}";
        $res = call_with_retry(fn() => http_json('GET', $url, $this->headers()));
        return $res['json'] ?? [];
    }

    public function batchUpsert(string $table, array $records): array {
        if (!defined('BATCH_UPSERT_ENABLED') || !BATCH_UPSERT_ENABLED) {
            // fallback: по одной
            $ok = 0;
            $fail = [];
            foreach ($records as $r) {
                try {
                    $this->createOrUpdate($table, $r);
                    $ok++;
                } catch (Throwable $e) {
                    $fail[] = $r['idempotency_key'] ?? null;
                }
            }
            return ['ok' => $ok, 'fail' => $fail];
        }
        
        $chunks = array_chunk($records, 10);
        $ok = 0;
        $fail = [];
        
        foreach ($chunks as $ch) {
            $url = "https://api.airtable.com/v0/{$this->baseId}/{$table}";
            try {
                $res = call_with_retry(fn() => http_json('PATCH', $url, $this->headers(), ['records' => $ch]));
                $ok += count($res['json']['records'] ?? []);
            } catch (HttpException $e) {
                // частичные ошибки: пытаемся по одной
                foreach ($ch as $r) {
                    try {
                        $this->createOrUpdate($table, $r);
                        $ok++;
                    } catch (Throwable $t) {
                        $fail[] = $r['idempotency_key'] ?? null;
                    }
                }
            }
        }
        
        return ['ok' => $ok, 'fail' => $fail];
    }

    public function createOrUpdate(string $table, array $record): array {
        // record: ['id' => 'rec...',?] или ['fields'=>[...]]
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$table}";
        return call_with_retry(fn() => http_json('POST', $url, $this->headers(), ['records' => [$record]]));
    }
}
?>
