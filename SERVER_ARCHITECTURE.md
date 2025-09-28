# 🏗️ Серверная архитектура Konstructour

## 📊 Обзор

Система теперь использует **серверную SQLite базу данных** вместо браузерного кэша для хранения всех данных (регионы, города, POI).

## 🗄️ Структура базы данных

### Таблицы:
- **`regions`** - Регионы Японии
- **`cities`** - Города и локации
- **`pois`** - Точки интереса
- **`tickets`** - Билеты для POI
- **`sync_log`** - Лог синхронизации

### Связи:
- `cities.region_id` → `regions.id`
- `pois.city_id` → `cities.id`
- `pois.region_id` → `regions.id`
- `tickets.poi_id` → `pois.id`

## 🔄 Синхронизация

### Автоматическая синхронизация:
```bash
# Добавить в crontab для синхронизации каждые 6 часов
0 */6 * * * /usr/bin/php /path/to/api/cron-sync.php
```

### Ручная синхронизация:
```bash
# Первоначальная синхронизация
php api/init-sync.php

# Синхронизация через API
curl -X POST http://yoursite.com/api/sync-airtable.php
```

## 📁 Файлы системы

### API файлы:
- `api/database.php` - Класс для работы с БД
- `api/data-api.php` - REST API для админ-панели
- `api/sync-airtable.php` - Синхронизатор Airtable → БД
- `api/cron-sync.php` - Cron-скрипт для автосинхронизации
- `api/init-sync.php` - Первоначальная синхронизация

### Конфигурация:
- `api/config.php` - Настройки Airtable
- `api/konstructour.db` - SQLite база данных (создается автоматически)

## 🚀 Установка

1. **Настройте Airtable токен** в `api/config.php`
2. **Запустите первоначальную синхронизацию:**
   ```bash
   php api/init-sync.php
   ```
3. **Настройте cron для автосинхронизации:**
   ```bash
   crontab -e
   # Добавьте: 0 */6 * * * /usr/bin/php /path/to/api/cron-sync.php
   ```

## 📈 Производительность

### Объем данных:
- **50,000 POI** ≈ 65 МБ
- **500 городов** ≈ 100 КБ
- **50 регионов** ≈ 7.5 КБ
- **Общий объем** ≈ 65 МБ

### Скорость:
- **Загрузка регионов** < 100ms
- **Загрузка городов** < 50ms
- **Загрузка POI** < 200ms
- **Синхронизация** < 30 секунд

## 🔧 API Endpoints

### Регионы:
```
GET /api/data-api.php?action=regions
```

### Города:
```
GET /api/data-api.php?action=cities&region_id=REGION_ID
```

### POI:
```
GET /api/data-api.php?action=pois&city_id=CITY_ID
```

### Статистика:
```
GET /api/data-api.php?action=stats
```

### Синхронизация:
```
POST /api/sync-airtable.php
```

## 🛡️ Безопасность

- Все API запросы логируются
- SQLite файл защищен правами доступа
- Синхронизация только с авторизованными Airtable токенами

## 📝 Логирование

Логи синхронизации сохраняются в:
- `api/sync.log` - Детальные логи синхронизации
- `api/konstructour.db` (таблица `sync_log`) - Лог операций

## 🔄 Миграция с браузерного кэша

Система автоматически переключилась на серверную БД. Старый браузерный кэш больше не используется.

## 📞 Поддержка

При проблемах проверьте:
1. Логи в `api/sync.log`
2. Права доступа к `api/konstructour.db`
3. Настройки Airtable в `api/config.php`
4. Статус cron-задач
