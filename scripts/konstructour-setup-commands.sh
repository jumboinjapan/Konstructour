#!/usr/bin/env bash
# konstructour-setup-commands.sh
# Готовые команды для настройки Airtable на konstructour.com

echo "=== Готовые команды для konstructour.com ==="
echo

echo "1. Создание файла секретов:"
echo "sudo mkdir -p /var/konstructour/secrets"
echo "sudo bash -c 'cat > /var/konstructour/secrets/airtable.json <<EOF"
echo "{"
echo "  \"current\": { \"token\": null, \"since\": null },"
echo "  \"next\":    { \"token\": null, \"since\": null }"
echo "}"
echo "EOF'"
echo

echo "2. Установка прав доступа:"
echo "sudo chown www-data:www-data /var/konstructour/secrets/airtable.json"
echo "sudo chmod 600 /var/konstructour/secrets/airtable.json"
echo "sudo chown www-data:www-data /var/konstructour/secrets"
echo "sudo chmod 700 /var/konstructour/secrets"
echo

echo "3. Загрузка PAT токена:"
echo "export ADMIN_TOKEN=\"<ВАШ_ADMIN_TOKEN>\""
echo "export PAT=\"patYOUR_TOKEN_HERE\""
echo
echo "curl -sS -X POST https://konstructour.com/api/config-store-secure.php \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"X-Admin-Token: \$ADMIN_TOKEN\" \\"
echo "  -d \"{\\\"airtable\\\":{\\\"api_key\\\":\\\"\$PAT\\\"}}\""
echo

echo "4. Промоут токена:"
echo "curl -sS -X POST https://konstructour.com/api/test-proxy-secure.php?provider=airtable \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"whoami\":true}'"
echo

echo "5. Проверка health:"
echo "curl -sS https://konstructour.com/api/health-airtable.php"
echo

echo "6. Открыть Health Dashboard:"
echo "https://konstructour.com/site-admin/health-dashboard.html"
echo "Базовый URL: https://konstructour.com"
echo

echo "=== Готово! ==="
