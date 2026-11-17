# PostAloud (free)

The free WordPress.org plugin. Slug `postaloud`, namespace `PostAloud\`, REST namespace `postaloud/v1`.
This repository is a git submodule of the private `heyKhoaiz/postaloud-docker` repo, which holds
the Docker environment, the Pro add-on and the release scripts. Develop from there:

```bash
git clone --recurse-submodules git@github.com:heyKhoaiz/postaloud-docker.git
```

Never add Pro code here; the parent's pre-push hook and CI check it (`scripts/assert-no-pro.mjs`).

```
backend/          PHP, PSR-4 (PostAloud\)
  Admin/          menu page + Vite manifest loader
  Player/         frontend player markup, text extraction and chunking
  Rest/           /postaloud/v1 routes
  Support/        settings
  Tts/            voice providers (browser, Google) + registry (filter: postaloud/providers)
frontend/admin/   React admin app (uses WordPress's React in release builds)
frontend/player/  dependency-free frontend player (build/player/)
tests/php|js/     PHPUnit (Brain Monkey) and Vitest
```
