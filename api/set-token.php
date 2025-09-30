<?php
// api/set-token.php
// Скрипт для установки Airtable токена

require_once __DIR__ . '/secret-airtable.php';

if ($argc < 2) {
  echo "Usage: php set-token.php 'pat-token-here' [current|next]\n";
  echo "  current - set as current token (default)\n";
  echo "  next    - set as next token (for rotation)\n";
  exit(1);
}

$token = $argv[1];
$slot = $argv[2] ?? 'current';

// Валидация токена
if (!preg_match('~^pat\\.[A-Za-z0-9_\\-]{20,}$~', $token)) {
  echo "ERROR: Invalid PAT format. Must start with 'pat.' and be at least 20 characters.\n";
  exit(1);
}

// Проверяем токен через whoami
echo "Testing token...\n";
$ch = curl_init('https://api.airtable.com/v0/meta/whoami');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
  CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
  echo "ERROR: Token validation failed (HTTP $httpCode)\n";
  echo "Response: $response\n";
  exit(1);
}

echo "Token validation successful!\n";

// Сохраняем токен
try {
  store_airtable_token($slot, $token);
  echo "Token saved to '$slot' slot successfully!\n";
  
  // Показываем текущее состояние
  $tokens = load_airtable_tokens();
  echo "\nCurrent state:\n";
  echo "  Current: " . ($tokens['current'] ? 'SET' : 'EMPTY') . "\n";
  echo "  Next:    " . ($tokens['next'] ? 'SET' : 'EMPTY') . "\n";
  
} catch (Exception $e) {
  echo "ERROR: Failed to save token: " . $e->getMessage() . "\n";
  exit(1);
}
?>
