# 🚀 Автоматический деплой Konstructour

## 📋 Обзор

Проект настроен на автоматический деплой через GitHub Actions при каждом push в ветку `main`. Все изменения автоматически синхронизируются с сервером Bluehost.

## 🔧 Текущая настройка

### GitHub Actions Workflow
- **Файл**: `.github/workflows/deploy.yml`
- **Триггер**: Push в ветку `main` или ручной запуск
- **Сервер**: Bluehost (revidovich.net)
- **Путь**: `/home2/revidovi/public_html/konstructour`

### Используемые секреты в GitHub
- `DEPLOY_SSH_KEY` - SSH ключ для подключения к серверу
- `SERVER_HOST` - `revidovich.net`
- `SERVER_USER` - `revidovi`
- `SERVER_PATH` - `/home2/revidovi/public_html/konstructour`

## 🎯 Как использовать

### Автоматический деплой
```bash
# Внесите изменения в файлы проекта
# Затем выполните:
git add .
git commit -m "Описание изменений"
git push origin main
```

### Ручной запуск деплоя
1. Перейдите в GitHub Actions: https://github.com/jumboinjapan/Konstructour/actions
2. Найдите workflow "Deploy to Bluehost"
3. Нажмите "Run workflow"

## 📁 Что деплоится

### Включается:
- Все файлы проекта
- Документация (.md файлы)
- Конфигурационные файлы
- Изображения и ресурсы

### Исключается:
- `.git/` - git метаданные
- `node_modules/` - зависимости Node.js
- `.github/` - GitHub Actions файлы
- `*.log` - лог файлы
- `deploy*.sh` - старые скрипты деплоя

## 🔍 Мониторинг

### Проверка статуса деплоя
- **GitHub Actions**: https://github.com/jumboinjapan/Konstructour/actions
- **Сайт**: https://revidovich.net/konstructour

### Логи деплоя
В GitHub Actions можно посмотреть детальные логи каждого шага:
1. Setup SSH key
2. Deploy to server (создание бэкапа, синхронизация, установка прав)

## 🛠️ Технические детали

### Процесс деплоя
1. **Подготовка SSH**: Создание SSH ключа и добавление хоста в known_hosts
2. **Создание бэкапа**: Автоматическое резервное копирование текущей версии
3. **Синхронизация**: rsync с удалением устаревших файлов
4. **Установка прав**: chmod 644 для файлов, 755 для директорий

### SSH ключ
- **Тип**: ED25519
- **Формат**: OpenSSH private key
- **Авторизован**: В Bluehost SSH Access

## 🚨 Устранение проблем

### Если деплой не запускается
1. Проверьте GitHub Actions: https://github.com/jumboinjapan/Konstructour/actions
2. Убедитесь, что все секреты настроены в Settings → Secrets and variables → Actions

### Если деплой падает с ошибкой
1. Откройте логи в GitHub Actions
2. Проверьте подключение к серверу
3. Убедитесь, что SSH ключ активен в Bluehost

### Ручной деплой (резервный вариант)
```bash
# Если GitHub Actions недоступен, можно деплоить вручную:
rsync -avz --delete -e "ssh -i ~/.ssh/id_ed25519" \
  --exclude='.git' --exclude='node_modules' --exclude='.github' \
  ./ revidovi@revidovich.net:/home2/revidovi/public_html/konstructour/
```

## 📞 Поддержка

При возникновении проблем:
1. Проверьте логи в GitHub Actions
2. Убедитесь в корректности секретов
3. Проверьте статус SSH ключа в Bluehost

---
*Последнее обновление: 20 сентября 2025*
