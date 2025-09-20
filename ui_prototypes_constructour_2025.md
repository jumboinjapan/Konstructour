# UI Prototypes (Constructour 2025, исправленная версия)

## Dashboard
- Панель со списком туров
- Кнопка "Создать тур"
- Статусы: Необработанный / В процессе / Завершённый

![Dashboard Prototype](figma_dashboard.png)

## Анкета Клиента
- 9 шагов с прогрессбаром
- Карточки фиксированного размера
- Поля: даты, состав группы, бюджет, регионы, питание и пр.

![Client Form](figma_client_form.png)

## Day Planner
- Таблица с фиксированными колонками (дата, регион, города)
- Динамические поля: POI, билеты, транспорт, сопровождение
- Drag-and-drop для добавления объектов

![Day Planner](figma_day_planner.png)

## PDF Preview
- Структура: обложка, сводка, дни, полезная информация, условия
- Кнопки: "Скачать", "Отправить клиенту"

![PDF Preview](figma_pdf.png)

## Код примера кнопок
```tsx
<button className="bg-secondary text-white px-4 py-2 rounded">Назад</button>
<button className="bg-primary text-white px-4 py-2 rounded">Далее</button>
```