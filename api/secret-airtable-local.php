<?php
// api/secret-airtable-local.php
// Локальная версия для тестирования

function airtable_secret_path(): string {
  // Для локального тестирования используем /tmp
  return '/tmp/konstructour/secrets/airtable.json';
}

function load_airtable_tokens(): array {
  $path = airtable_secret_path();
  if (!is_readable($path)) {
    throw new RuntimeException("Airtable secret not readable: $path");
  }
  $raw = file_get_contents($path);
  $j = json_decode($raw, true);
  if (!is_array($j)) throw new RuntimeException("Invalid secret JSON");
  return [
    'current' => $j['current']['token'] ?? null,
    'next'    => $j['next']['token'] ?? null
  ];
}

/**
 * Атомарное обновление одного из слотов: current|next
 */
function store_airtable_token(string $slot, ?string $token): void {
  if (!in_array($slot, ['current','next'], true)) {
    throw new InvalidArgumentException("Invalid slot: $slot");
  }
  $path = airtable_secret_path();
  $j = ['current'=>['token'=>null,'since'=>null], 'next'=>['token'=>null,'since'=>null]];
  if (is_readable($path)) {
    $old = json_decode(file_get_contents($path), true);
    if (is_array($old)) $j = array_merge($j, $old);
  }
  $j[$slot]['token'] = $token;
  $j[$slot]['since'] = $token ? gmdate('c') : null;

  $tmp = $path.'.tmp';
  file_put_contents($tmp, json_encode($j, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  chmod($tmp, 0600);
  rename($tmp, $path); // атомарно
}

/**
 * Пробуем current, при 401 пробуем next.
 * Возвращает ['token'=>..., 'promote'=>bool]
 */
function get_airtable_token_with_failover(callable $whoami): array {
  $tokens = load_airtable_tokens();
  foreach (['current','next'] as $which) {
    $tok = $tokens[$which] ?? null;
    if (!$tok) continue;
    $ok = $whoami($tok); // true если авторизован
    if ($ok) {
      // Если это next — предлагаем promote снаружи
      return ['token'=>$tok, 'promote'=>($which === 'next')];
    }
  }
  throw new RuntimeException("No working Airtable token");
}

/**
 * Проверка whoami для токена
 */
function airtable_whoami_check(string $token): bool {
  $ch = curl_init('https://api.airtable.com/v0/meta/whoami');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token],
    CURLOPT_TIMEOUT => 10,
  ]);
  curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code >= 200 && $code < 300;
}
?>
