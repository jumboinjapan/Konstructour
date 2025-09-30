<?php
// api/init-secrets.php
// Скрипт для инициализации секретов (запускать один раз)

function airtable_secret_path(): string {
  return '/var/konstructour/secrets/airtable.json';
}

// Создаем директорию если не существует
$dir = dirname(airtable_secret_path());
if (!is_dir($dir)) {
  if (!mkdir($dir, 0755, true)) {
    die("Cannot create directory: $dir\n");
  }
  echo "Created directory: $dir\n";
}

// Создаем файл секретов
$secretsFile = airtable_secret_path();
$initialData = [
  'current' => [
    'token' => null,
    'since' => null
  ],
  'next' => [
    'token' => null,
    'since' => null
  ]
];

// Если файл уже существует, читаем его
if (file_exists($secretsFile)) {
  $existing = json_decode(file_get_contents($secretsFile), true);
  if (is_array($existing)) {
    $initialData = array_merge($initialData, $existing);
  }
}

// Записываем файл
file_put_contents($secretsFile, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
chmod($secretsFile, 0600);

echo "Secrets file created: $secretsFile\n";
echo "File permissions: " . substr(sprintf('%o', fileperms($secretsFile)), -4) . "\n";

// Проверяем права
if (fileperms($secretsFile) & 0x0007) {
  echo "WARNING: File is readable by others! Run: chmod 600 $secretsFile\n";
} else {
  echo "File permissions are secure\n";
}

echo "\nTo set your Airtable PAT, run:\n";
echo "php api/set-token.php 'your-pat-token-here'\n";
?>
