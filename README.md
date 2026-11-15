# Listenly Voice (free)

The free WordPress.org plugin. Slug `listenly-voice`, namespace `ListenlyVoice\`, REST namespace `listenly/v1`.
This repository is a git submodule of the private `heyKhoaiz/listenly-voice-docker` repo, which holds
the Docker environment, the Pro add-on and the release scripts. Develop from there:

```bash
git clone --recurse-submodules git@github.com:heyKhoaiz/listenly-voice-docker.git
```

Never add Pro code here; the parent's pre-push hook and CI check it (`scripts/assert-no-pro.mjs`).

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
