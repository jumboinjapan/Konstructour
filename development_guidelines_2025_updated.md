# Development Guidelines (Constructour 2025, исправленная версия)

## Framework and Stack
- **React 19** (использовать compiler и actions)
- **Next.js 14**
- **Tailwind CSS v4** (с поддержкой color engines и okLCH tokens)
- Zustand (глобальное состояние)
- React Query (работа с Airtable API)
- dnd-kit (drag-and-drop)
- Date-fns, React-PDF, Lucide-React

## Performance Benchmarks
- Web: LCP < 2s, CLS < 0.1, FID < 100ms
- Mobile: Lighthouse Mobile Score ≥ 90
- Coverage: Unit ≥ 80%, Integration ≥ 70%, E2E ≥ 60%

## Upgrade Policy
- Обновлять стек каждые 12 месяцев
- Проверять миграцию React/Tailwind при каждом мажорном релизе