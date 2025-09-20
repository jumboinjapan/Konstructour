# AI Workflow (Constructour 2025, исправленная версия)

## Генерация маршрута

Алгоритм выбора шаблонов маршрутов использует метрику схожести:

```ts
const similarityScore = compareTemplate(userPreferences, template);
if (similarityScore > 0.8) {
  suggestTemplate(template);
  showPreviewDiff(currentRoute, template);
}
```

- AI предлагает максимум **3 шаблона**.
- Перед заменой показывается **diff-просмотр** (preview-diff).
- Пользователь может отклонить или принять шаблон частично.

## Сценарии Prompt Engineering
- "Сгенерируй маршрут на основе анкеты клиента, сохраняя бюджет и предпочтения."
- "Сравни текущий маршрут с шаблоном, верни diff в формате JSON."

## Best Practices
- Минимизировать галлюцинации: AI работает только с предоставленными Airtable данными.
- Всегда запрашивать подтверждение перед внесением изменений.
