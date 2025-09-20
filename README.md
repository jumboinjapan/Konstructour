# Konstructour

Веб-платформа для архитектурного проектирования с AI-интеграцией.

## 🚀 Автоматический деплой

Проект настроен на автоматический деплой на Bluehost при каждом push в main ветку.

### Настройка GitHub Secrets

В настройках репозитория GitHub → Settings → Secrets and variables → Actions добавьте:

1. **SERVER_HOST**: `162.241.225.33` (IP адрес Bluehost)
2. **SERVER_USER**: `revidovi@revidovich.net` (ваш SSH пользователь)
3. **SERVER_PATH**: `/home/revidovi/public_html/konstructour` (путь на сервере)
4. **DEPLOY_SSH_KEY**: (приватный SSH ключ - см. ниже)

### SSH ключ для деплоя

```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBfBJDCM895LuJwWh7k09s72FX7TOjCnl1BfJEn3USqZwAAAKA8X+GcPF/h
nAAAAAtzc2gtZWQyNTUxOQAAACBfBJDCM895LuJwWh7k09s72FX7TOjCnl1BfJEn3USqZw
AAAEA8gmy+qY4+MCkgZz/oPBOwOcSO3S//cHUjo7oqRArAC18EkMIzz3ku4nBaHuTT2zvY
VftM6MKeXUF8kSfdRKpnAAAAG2tvbnN0cnVjdG91ci1jbGVhbi0yMDI1MDkyMAEC
-----END OPENSSH PRIVATE KEY-----
```

### Структура проекта

```
├── admin/              # Админ панель
│   ├── index.html      # Страница входа
│   └── dashboard.html  # Панель управления
├── styles/             # CSS стили
├── js/                 # JavaScript
├── index.html          # Главная страница
└── .htaccess           # Конфигурация сервера
```

### Домен

- **Основной сайт**: https://www.konstructour.com
- **Админ панель**: https://www.konstructour.com/admin

### Деплой

Деплой происходит автоматически при:
- Push в main ветку
- Ручном запуске через GitHub Actions

Логи деплоя можно посмотреть в разделе Actions на GitHub.

## 🔧 Разработка

Для локальной разработки просто откройте `index.html` в браузере.

## 📝 Примечания

- SSH ключ уже добавлен в Bluehost cPanel
- Все файлы автоматически синхронизируются с сервером
- Создаются резервные копии перед каждым деплоем