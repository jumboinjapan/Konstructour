# 🔐 Настройка токенов API

## Безопасное хранение токенов

### 1. Создайте локальный файл с токеном
```bash
# Скопируйте пример файла
cp api/airtable.env api/airtable.env.local

# Отредактируйте файл и добавьте ваш токен
nano api/airtable.env.local
```

### 2. Добавьте ваш токен Airtable
В файле `api/airtable.env.local` замените:
```
AIRTABLE_PAT=your_airtable_token_here
```
на:
```
AIRTABLE_PAT=patXXXXXXXXXXXXXX.XXXXXXXXXXXXXX.XXXXXXXXXXXXXX
```

### 3. Проверьте настройку
```bash
# Проверьте, что токен загружается
php api/check-cities-structure.php
```

### 4. Запустите синхронизацию
- Откройте админ-панель
- Нажмите кнопку синхронизации
- Города Хоккайдо должны загрузиться

## Безопасность

✅ **Файлы с токенами НЕ коммитятся в Git:**
- `api/airtable.env.local` - исключен в `.gitignore`
- `.env` - исключен в `.gitignore`
- `api/.env` - исключен в `.gitignore`

✅ **Порядок загрузки токенов:**
1. `api/airtable.env.local` (локальный файл)
2. `api/airtable.env` (пример файла)
3. `api/.env` (если есть)
4. `api/.env.local` (если есть)
5. Переменные окружения системы

## Получение токена Airtable

1. Перейдите на https://airtable.com/create/tokens
2. Войдите в свой аккаунт Airtable
3. Создайте Personal Access Token
4. Выберите права:
   - `data.records:read` - чтение записей
   - `data.records:write` - запись записей
   - `schema.bases:read` - чтение структуры
5. Скопируйте токен (начинается с `pat-`)

## Альтернативные способы

### Через переменные окружения системы
```bash
export AIRTABLE_PAT="patXXXXXXXXXXXXXX.XXXXXXXXXXXXXX.XXXXXXXXXXXXXX"
```

### Через .env файл в корне проекта
```bash
echo "AIRTABLE_PAT=patXXXXXXXXXXXXXX.XXXXXXXXXXXXXX.XXXXXXXXXXXXXX" > .env
```

## Отладка

Если токен не загружается:
1. Проверьте права на файл: `chmod 600 api/airtable.env.local`
2. Проверьте синтаксис: `php -l api/airtable.env.local`
3. Проверьте загрузку: `php -r "require 'api/load-env.php'; echo getenv('AIRTABLE_PAT');"`
