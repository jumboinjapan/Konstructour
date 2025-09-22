#!/usr/bin/env bash
set -euo pipefail

echo "▶️  Проверка macOS…"
if [[ "$(uname -s)" != "Darwin" ]]; then
  echo "Это решение рассчитано на macOS."; exit 1
fi

# ---------- Homebrew ----------
if ! command -v brew >/dev/null 2>&1; then
  echo "📦 Устанавливаю Homebrew…"
  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
fi

# подключаем brew в PATH для текущей сессии
if [[ -d /opt/homebrew ]]; then
  eval "$(/opt/homebrew/bin/brew shellenv)"
elif [[ -d /usr/local/Homebrew ]]; then
  eval "$(/usr/local/bin/brew shellenv)"
fi

# ---------- PHP ----------
if ! command -v php >/dev/null 2>&1; then
  echo "🐘 Устанавливаю PHP через Homebrew…"
  brew install php
fi

PHP_PATH="$(command -v php)"
echo "✅ Найден PHP: ${PHP_PATH}"

# ---------- Обновление settings.json ----------
update_settings() {
  local FILE="$1"
  local DIR
  DIR="$(dirname "$FILE")"
  mkdir -p "$DIR"
  [[ -f "$FILE" ]] || echo "{}" > "$FILE"

  /usr/bin/python3 - <<PY
import json, sys, os
php_path = os.environ.get("PHP_PATH")
path = "$FILE"
try:
    with open(path, "r", encoding="utf-8") as f:
        data = json.load(f)
except Exception:
    data = {}
data["php.validate.executablePath"] = php_path
with open(path, "w", encoding="utf-8") as f:
    json.dump(data, f, ensure_ascii=False, indent=2)
print(f"📝 Обновлён: {path}")
PY
}

# Кандидаты настроек для VS Code и Cursor (обычные и Insiders)
CANDIDATES=(
  "$HOME/Library/Application Support/Code/User/settings.json"
  "$HOME/Library/Application Support/Code - Insiders/User/settings.json"
  "$HOME/Library/Application Support/Cursor/User/settings.json"
  "$HOME/Library/Application Support/Cursor - Insiders/User/settings.json"
)

echo "⚙️  Обновляю настройки редакторов (если найдены)…"
for f in "${CANDIDATES[@]}"; do
  # обновляем, только если папка редактора существует
  if [[ -d "$(dirname "$f")" ]]; then
    update_settings "$f"
  fi
done

echo
echo "🎉 Готово!"
echo "PHP: $PHP_PATH"
echo "Перезапустите VS Code/Cursor, чтобы пропало предупреждение."
