# 🏗️ Konstructour Project

Проект Konstructour с автоматической синхронизацией GitHub и деплоем на сервер Bluehost.

## 🚀 Быстрый старт

### Отправка изменений на GitHub
```bash
git add .
git commit -m "Описание изменений"
git push origin main
```

**Автоматический деплой** произойдет сразу после push!

## ⚙️ Автоматический деплой

GitHub Actions автоматически деплоит проект на сервер при каждом push в main ветку.

### Настроенные секреты в GitHub:
- `DEPLOY_SSH_KEY` - SSH ключ для деплоя
- `SERVER_HOST` - `revidovich.net`
- `SERVER_USER` - `revidovi`
- `SERVER_PATH` - `/home2/revidovi/public_html/konstructour`

## 📁 Структура проекта

```
├── .github/workflows/deploy.yml    # GitHub Actions workflow
├── DEPLOYMENT.md                   # Инструкция по деплою
├── *.md                           # Техническая документация
└── ...                            # Файлы проекта
```

## 📖 Документация

- [DEPLOYMENT.md](DEPLOYMENT.md) - Подробная инструкция по автоматическому деплою
- Все остальные `.md` файлы содержат техническую документацию проекта

## 🔄 Процесс работы

1. **Разработка** → Вносите изменения в код
2. **Push** → `git push origin main`  
3. **Автодеплой** → GitHub Actions автоматически деплоит на сервер
4. **Готово** → Изменения доступны на https://revidovich.net/konstructour

## 🛠️ Основные команды

```bash
# Отправить изменения (автоматический деплой)
git add .
git commit -m "Сообщение коммита"
git push origin main

# Проверить статус Git
git status

# Посмотреть историю коммитов
git log --oneline
```

## 📞 Поддержка

При возникновении проблем обращайтесь к [DEPLOYMENT.md](DEPLOYMENT.md) для детальных инструкций по настройке и решению проблем.
