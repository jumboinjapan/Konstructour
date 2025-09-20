# 🔧 Настройка секретов GitHub для Bluehost

Пошаговая инструкция для настройки автоматического деплоя на Bluehost с доменом revidovich.net.

## 📋 Ваша конфигурация

- **Хостинг**: Bluehost
- **Основной домен**: www.revidovich.net
- **Поддомен проекта**: Konstructour.com
- **Локальная папка на сервере**: konstructour.com (в public_html)

---

## 🔍 Определяем каждый секрет

### 1. `SERVER_HOST` - Адрес сервера ✅ ЛЕГКО

**Что это**: IP-адрес или доменное имя вашего сервера Bluehost

**Где найти**:
1. Войдите в панель управления Bluehost (cPanel)
2. В правой колонке найдите раздел **"Server Information"** или **"Account Information"**
3. Найдите строку **"Server Name"** или **"Shared IP Address"**

**Возможные варианты**:
```
box2345.bluehost.com
или
198.46.xxx.xxx (IP адрес)
или
revidovich.net (ваш основной домен)
```

**Для GitHub секрета**: Используйте любой из найденных вариантов (лучше доменное имя сервера).

---

### 2. `SERVER_USER` - Имя пользователя 🤔 СРЕДНЕ

**Что это**: НЕ ваше имя для входа в панель Bluehost! Это имя пользователя для SSH/FTP доступа.

**Где найти**:

#### Способ 1: В cPanel
1. Войдите в cPanel Bluehost
2. Найдите раздел **"Files"** → **"FTP Accounts"**
3. Посмотрите на **главный FTP аккаунт** - это и есть ваш SERVER_USER
4. Обычно выглядит как: `username` или `username@revidovich.net`

#### Способ 2: В Account Information
1. В cPanel найдите **"Account Information"** 
2. Строка **"Username"** или **"cPanel Username"**
3. Это короткое имя (обычно 8-10 символов)

**По вашему дереву директорий**: 
```
revidovi
```

**Это видно из пути**: `/home2/revidovi/`

---

### 3. `SERVER_PATH` - Путь к папке проекта ✅ ЛЕГКО

**Что это**: Полный путь к папке где должен лежать ваш сайт konstructour.com

**По вашему дереву директорий точный путь**:
```
/home2/revidovi/public_html/konstructour
```

**Из скриншота видно**:
- Корневая папка: `/home2/revidovi/` (не /home/)
- Папка сайтов: `public_html/`
- Папка проекта: `konstructour` (без .com)

---

### 4. `DEPLOY_SSH_KEY` - SSH ключ 😰 СЛОЖНО

**Что это**: Специальный ключ для безопасного подключения к серверу без пароля.

#### Шаг 1: Проверяем, включен ли SSH на Bluehost

**⚠️ ВАЖНО**: Не все планы Bluehost поддерживают SSH!

1. Войдите в cPanel
2. Найдите раздел **"Security"** → **"SSH Access"**
3. Если раздела нет - SSH недоступен на вашем плане

#### Шаг 2А: Если SSH доступен - создаем ключ

```bash
# В терминале на вашем Mac
cd ~/.ssh
ssh-keygen -t rsa -b 4096 -C "deploy-konstructour" -f konstructour_deploy

# Это создаст два файла:
# konstructour_deploy (приватный ключ)
# konstructour_deploy.pub (публичный ключ)
```

#### Шаг 2Б: Добавляем публичный ключ на сервер

1. Скопируйте содержимое публичного ключа:
```bash
cat ~/.ssh/konstructour_deploy.pub
```

2. В cPanel → SSH Access → **"Manage SSH Keys"**
3. **"Import Key"** или **"Add Key"**
4. Вставьте содержимое публичного ключа
5. **Активируйте** ключ

#### Шаг 2В: Получаем приватный ключ для GitHub

```bash
# Показать приватный ключ (для копирования в GitHub)
cat ~/.ssh/konstructour_deploy
```

**Скопируйте ВЕСЬ текст включая**:
```
-----BEGIN OPENSSH PRIVATE KEY-----
...весь ключ...
-----END OPENSSH PRIVATE KEY-----
```

#### Шаг 3: Если SSH НЕ доступен - используем FTP альтернативу

Создайте файл альтернативного деплоя через FTP:

```yaml
# .github/workflows/deploy-ftp.yml
name: FTP Deploy
on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v4
    
    - name: FTP Deploy
      uses: SamKirkland/FTP-Deploy-Action@4.3.3
      with:
        server: ${{ secrets.SERVER_HOST }}
        username: ${{ secrets.FTP_USERNAME }}
        password: ${{ secrets.FTP_PASSWORD }}
        server-dir: /public_html/konstructour.com/
```

**В этом случае нужны секреты**:
- `FTP_USERNAME` - ваш FTP пользователь (как SERVER_USER)
- `FTP_PASSWORD` - пароль от FTP (НЕ от cPanel!)

---

## 🔑 Добавление секретов в GitHub

1. Откройте ваш репозиторий на GitHub
2. **Settings** → **Secrets and variables** → **Actions**
3. **"New repository secret"**
4. Добавьте каждый секрет:

### Если SSH доступен:
```
SERVER_HOST: box2345.bluehost.com (найдите в cPanel)
SERVER_USER: revidovi  
SERVER_PATH: /home2/revidovi/public_html/konstructour
DEPLOY_SSH_KEY: -----BEGIN OPENSSH PRIVATE KEY----- (весь ключ)
```

### Если только FTP:
```
SERVER_HOST: box2345.bluehost.com (найдите в cPanel)
FTP_USERNAME: revidovi
FTP_PASSWORD: ваш_ftp_пароль
```

---

## 🧪 Как проверить правильность данных

### Проверка SERVER_HOST и SERVER_USER:
```bash
# В терминале Mac попробуйте подключиться
ssh revidovi@SERVER_HOST

# Пример:
ssh revidovi@box2345.bluehost.com
```

### Проверка SERVER_PATH:
После подключения по SSH:
```bash
ls -la /home2/revidovi/public_html/
# Должна быть папка konstructour
```

### Проверка FTP (если SSH недоступен):
```bash
# Установите lftp если нет
brew install lftp

# Подключитесь
lftp ftp://SERVER_USER@SERVER_HOST
# Введите пароль FTP
# Проверьте: ls public_html/
```

---

## 📞 Что делать если что-то не работает

### SSH недоступен на вашем плане Bluehost:
- Используйте FTP деплой (инструкция выше)
- Или обновите план хостинга

### Не можете найти SERVER_USER:
1. В cPanel → **File Manager**
2. Посмотрите в адресной строке: `/home/USERNAME/`
3. USERNAME и есть ваш SERVER_USER

### Забыли FTP пароль:
1. cPanel → **FTP Accounts**
2. Найдите главный аккаунт
3. **"Change Password"**

### SSH ключ не работает:
1. Проверьте формат ключа (должен быть OpenSSH)
2. Убедитесь что ключ активирован в cPanel
3. Попробуйте пересоздать ключ

---

## 📝 Чек-лист перед запуском

- [ ] SERVER_HOST найден в cPanel
- [ ] SERVER_USER найден в Account Info или FTP Accounts  
- [ ] SERVER_PATH составлен: /home/[USER]/public_html/konstructour.com
- [ ] SSH ключ создан и добавлен (или настроен FTP)
- [ ] Все секреты добавлены в GitHub
- [ ] Тестовый коммит отправлен для проверки

**После настройки**: Сделайте тестовый push и проверьте логи GitHub Actions!
