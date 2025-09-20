# Testing and Deployment (Constructour 2025, исправленная версия)

## Testing Levels
- Unit Tests: coverage ≥ 80%
- Integration Tests: coverage ≥ 70%
- E2E Tests: coverage ≥ 60%

## CI/CD
- GitHub Actions workflow (build, test, lint, deploy)
- Automatic deployment to Vercel
- Alerts via Slack integration

## Performance Benchmarks
- Desktop: LCP < 2s, CLS < 0.1
- Mobile: FID < 100ms, Lighthouse ≥ 90

## Monitoring
- Lighthouse CI
- Sentry (error tracking)
- Uptime Robot (availability)
