<?php
// Скрипт для обновления sync-correct.php на сервере

$serverUrl = 'https://www.konstructour.com/api/update-sync-file.php';
$localFile = 'api/sync-correct.php';

if (!file_exists($localFile)) {
    die("Файл $localFile не найден\n");
}

$content = file_get_contents($localFile);
if ($content === false) {
    die("Не удалось прочитать файл $localFile\n");
}

echo "Отправляем обновленный файл на сервер...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $serverUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['content' => $content]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("CURL Error: $curlError\n");
}

$result = json_decode($response, true);

if ($httpCode === 200 && $result['ok']) {
    echo "✅ Файл успешно обновлен на сервере!\n";
    echo "📁 Резервная копия: " . $result['backup'] . "\n";
    echo "📊 Размер: " . $result['size'] . " байт\n";
} else {
    echo "❌ Ошибка обновления файла:\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}
?>
