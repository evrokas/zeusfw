# CLAUDE.md — ZeusFW

The shared PHP framework behind erweb, medscribe, mweb, neurolog, zgeotrack, zpms, zprime and zweb (each links
it as `<app>/fw`, and `<app>/web/core -> ../fw/core`).

- **How the framework works and how to use it**: the `zeusfw` skill, `.claude/skills/zeusfw/` (also linked into
  every app and `~/.claude/skills/`). Load it for any framework or app work.
- **Why something is the way it is**: `docs/CHANGELOG.md` — dated entries; search it, don't read it whole.

## Working here

- Every change under `core/` or `bin/` ships to every app. Keep it additive and opt-in (config key,
  `function_exists()` hook, module listed in `modules:`). Don't change an existing signature or default without
  the owner agreeing.
- New `core/lib/*.php` file: add its `require_once` to `core/bootstrap.php` by hand (no autoloader).
- Verify against a real app on a real MySQL/MariaDB (zpms has the fullest suite: `bin/run_tests.sh` there).
  Say which apps you could not test.
- After a change: add a dated entry at the end of `docs/CHANGELOG.md` (what, why, verified, files), and update
  the skill's reference file if usage changed.
- Commit and push only when asked. Remote: github `evrokas/zeusfw`.
