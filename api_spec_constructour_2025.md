# API Specification for Constructour (2025)

Документ описывает спецификацию API для проекта Constructour. Анализ выявил отсутствие подробного описания эндпоинтов, форматов запросов и ответов, что затрудняет разработку и интеграцию【534680704413190†L579-L597】. Настоящий гайд восполняет этот пробел и служит основой для реализации серверной части и клиентских вызовов.

## 1 Архитектура API

API имеет REST‑ориентированный стиль с возможностью расширения до GraphQL. Аутентификация основана на JWT (JSON Web Token); пользователи получают токен после входа в систему и отправляют его в заголовке `Authorization: Bearer <token>`.

Все ответы возвращаются в формате JSON. Для ошибок используется единый формат с полями `status`, `message` и, при необходимости, `errors`.

## 2 Ресурсы и эндпоинты

### 2.1 Clients

| Метод | URL | Описание |
|---|---|---|
| `GET` | `/api/clients` | Возвращает список клиентов. Поддерживает параметр `?search=` для фильтрации по имени или email. |
| `POST` | `/api/clients` | Создаёт клиента. Тело запроса: `{ name, email, phone, preferences, pricingCoefficients }`. |
| `GET` | `/api/clients/{id}` | Возвращает данные одного клиента. |
| `PUT` | `/api/clients/{id}` | Обновляет данные клиента. |
| `DELETE` | `/api/clients/{id}` | Удаляет клиента (soft‑delete). |

### 2.2 Tours

| Метод | URL | Описание |
|---|---|---|
| `GET` | `/api/tours` | Получает список туров. Поддерживает параметры `?clientId=`, `?status=`, `?region=`. |
| `POST` | `/api/tours` | Создаёт тур. Тело запроса: `{ clientId, name, startDate, endDate, regions, party, budgetTier, experienceJapan, notes }`. |
| `GET` | `/api/tours/{id}` | Получает один тур со связанными днями (`expandDays=true` для вложенных дней). |
| `PUT` | `/api/tours/{id}` | Обновляет тур. |
| `DELETE` | `/api/tours/{id}` | Удаляет тур. |

### 2.3 Tour Days

| Метод | URL | Описание |
|---|---|---|
| `GET` | `/api/tours/{tourId}/days` | Возвращает дни конкретного тура. |
| `POST` | `/api/tours/{tourId}/days` | Добавляет день. Тело: `{ date, region, cityDay, cityNight, items, accompaniment }`. |
| `PUT` | `/api/tours/{tourId}/days/{dayId}` | Обновляет данные дня (изменение города, объектов, времени). |
| `DELETE` | `/api/tours/{tourId}/days/{dayId}` | Удаляет день тура. |

### 2.4 Locations

| Метод | URL | Описание |
|---|---|---|
| `GET` | `/api/locations` | Возвращает справочник локаций; поддерживает фильтры `?type=region/city/poi`, `?parentId=`. |
| `POST` | `/api/locations` | Добавляет запись в справочник. Тело: `{ name, type, parentId, coordinates, category, description, duration }`. |
| `PUT` | `/api/locations/{id}` | Обновляет локацию. |
| `DELETE` | `/api/locations/{id}` | Удаляет запись (soft‑delete). |

### 2.5 Auth

| Метод | URL | Описание |
|---|---|---|
| `POST` | `/api/auth/login` | Аутентификация пользователя. Тело: `{ email, password }`. Ответ: `{ token, user }`. |
| `POST` | `/api/auth/refresh` | Обновление access‑token. |
| `POST` | `/api/auth/logout` | Инвалидация токена. |
| `POST` | `/api/auth/register` | Регистрация нового пользователя (admin/guide). |

## 3 Примеры запросов и ответов

### 3.1 Создание клиента

**Запрос**

```http
POST /api/clients
Authorization: Bearer eyJhbGciOi...
Content-Type: application/json

{
  "name": "Иван Иванов",
  "email": "ivan@example.com",
  "phone": "+81 90 1234 5678",
  "preferences": {
    "budgetTier": "medium",
    "travelStyle": "active",
    "mobility": "regular",
    "dietary": ["vegetarian"]
  },
  "pricingCoefficients": {
    "guide": 1.2,
    "transport": 1.0
  }
}
```

**Ответ**

```json
{
  "status": "success",
  "data": {
    "id": "rec123",
    "name": "Иван Иванов",
    "email": "ivan@example.com",
    "phone": "+81 90 1234 5678",
    "preferences": {
      "budgetTier": "medium",
      "travelStyle": "active",
      "mobility": "regular",
      "dietary": ["vegetarian"]
    },
    "pricingCoefficients": {
      "guide": 1.2,
      "transport": 1.0
    }
  }
}
```

### 3.2 Получение тура с днями

```http
GET /api/tours/recTour123?expandDays=true
Authorization: Bearer eyJhbGciOi...
```

**Ответ**

```json
{
  "status": "success",
  "data": {
    "id": "recTour123",
    "clientId": "rec123",
    "name": "Токийские каникулы",
    "startDate": "2025-04-10",
    "endDate": "2025-04-14",
    "regions": ["tokyo", "yokohama"],
    "party": { "adults": 2, "children": 1, "seniors": 0 },
    "status": "planned",
    "days": [
      {
        "id": "day1",
        "date": "2025-04-10",
        "cityDay": "Tokyo",
        "cityNight": "Tokyo",
        "items": [
          { "poiId": "sensoji", "timeStart": "09:00", "timeEnd": "10:30", "notes": "Буддийский храм" }
        ],
        "accompaniment": "guide"
      }
    ]
  }
}
```

## 4 Обработка ошибок

При возникновении ошибки API возвращает код состояния HTTP 4xx или 5xx и тело:

```json
{
  "status": "error",
  "message": "Invalid input data",
  "errors": {
    "email": "Email already exists"
  }
}
```

## 5 GraphQL (опционально)

При необходимости можно реализовать GraphQL API поверх существующих сервисов. Это позволит запрашивать только необходимые поля и объединять данные из разных таблиц одним запросом. Схема GraphQL будет содержать типы `Client`, `Tour`, `TourDay`, `Location` и соответствующие запросы (`clients`, `tours`, `tour`, `locations`), мутации (`createClient`, `updateTour`, etc.) и подписки для realtime‑обновлений.

## 6 Аутентификация и авторизация

- **JWT**: используйте короткоживущие access‑token (например, 15 минут) и долгоживущие refresh‑token. Обновляйте access‑token через эндпоинт `/api/auth/refresh`.
- **Роли**: реализуйте проверку прав (RBAC). Например, администратор может управлять всеми клиентами и турами, гид — только своими турами, а клиент — только собственной анкетой. Добавляйте `role` в полезную нагрузку токена.
- **Защита CSRF**: используйте `SameSite` cookies для хранения refresh‑token, проверяйте заголовки `X-CSRF-Token` на сервере.

## 7 Заключение

Спецификация API является ключевой частью проекта и служит контрактом между фронтендом, бэкендом и сторонними интеграциями. Следуя этим эндпоинтам и форматам, разработчики и AI‑ассистенты смогут создавать стабильные и предсказуемые интеграции. В случае изменений версии API следует обновлять этот документ и обеспечивать обратную совместимость.