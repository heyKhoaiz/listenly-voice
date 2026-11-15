# Listenly Voice (free)

The free WordPress.org plugin. Slug `listenly-voice`, namespace `ListenlyVoice\`, REST namespace `listenly/v1`.
Develop it from the parent repository, `listenly-voice-docker` (see its README).

```
backend/          PHP, PSR-4 (ListenlyVoice\)
  Admin/          menu page + Vite manifest loader
  Player/         frontend player markup, text extraction and chunking
  Rest/           /listenly/v1 routes
  Support/        settings
  Tts/            voice providers (browser, Google) + registry (filter: listenly/providers)
frontend/admin/   React admin app (uses WordPress's React in release builds)
frontend/player/  dependency-free frontend player (build/player/)
tests/php|js/     PHPUnit (Brain Monkey) and Vitest
```
