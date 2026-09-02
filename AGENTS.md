<!-- fleet-template: v1 | reconciled-against: fleet-command/docs/fleet/AGENT-CONTEXT-TEMPLATE.md @ c0c8dd5 2026-09-02 -->
# AutoAcceptOrders-WHMCS — agent context

## What this repo is

WHMCS addon module that auto-accepts qualifying orders — installs into a WHMCS installation
to automatically accept paid and free pending orders that meet configured criteria, without
manual admin review. **Public repo** (`wikithoughts/AutoAcceptOrders-WHMCS`): unlimited
GitHub Actions minutes.

## Stack & layout

- **Language:** PHP `^8.1` (`composer.json`; CI matrix covers PHP 8.1+, 7.4/8.0 dropped 2026-05)
- **Package manager:** composer
- **Frameworks/tooling:** WHMCS addon API, PHPUnit, PHPStan, PHP-CS-Fixer

## Layout

- `modules/addons/auto_accept_orders/` — the module: `auto_accept_orders.php` (config/admin UI), `hooks.php` (WHMCS hook registration), `autoload.php`, `lib/` (logic)
- `tests/` — PHPUnit (WHMCS APIs stubbed under `tests/Stub`, excluded from phpstan)
- `vendor/` — composer output; never edit. `composer.lock` is deliberately untracked.

## Commands

Verified against `composer.json` scripts, `phpunit.xml.dist`, `phpstan.neon.dist`, and
`.orchestration/lanes.yml`. (The repo's own prior heading pinned these to "PHP 8.5 via
Homebrew; ensure `/opt/homebrew/bin` on PATH" — that's a Mac-specific PATH note; composer/php
are already on PATH here.)

Setup:
```bash
composer install
```

Test:
```bash
vendor/bin/phpunit              # 56 tests
composer test                   # = phpunit (composer.json scripts.test)
```

Typecheck:
```bash
vendor/bin/phpstan analyse      # level 5, phpstan.neon.dist
composer stan                   # = phpstan analyse --no-progress
```

Lint:
```bash
composer cs                     # php-cs-fixer fix --dry-run --diff
```

Format:
```bash
composer cs-fix                 # php-cs-fixer fix
```

Verify (what `.orchestration/lanes.yml` runs):
```bash
composer test && composer stan
```

Both `phpunit` and `phpstan` must pass before any push — CI (`.github/workflows/ci.yml`,
github-hosted runner, PHP 8.1/8.2/8.3 matrix) runs the same checks.

## Deployment

Copy `modules/addons/auto_accept_orders/` into a WHMCS installation's `modules/addons/` and
activate in the WHMCS admin. No build step. Not deployed via CI (`.orchestration/lanes.yml`'s
`deploy_on_merge` is empty) — deployment to hosting is a manual step.

## Verification before done

1. `composer install` if `vendor/` is stale or missing.
2. `composer test` (or `vendor/bin/phpunit` directly) — 56 tests.
3. `composer stan` (or `vendor/bin/phpstan analyse`) — level 5.
4. `composer cs` to check style before a PR; `composer cs-fix` to auto-fix.
5. Or the one-line combined check the harness itself runs: `composer test && composer stan`.

This repo is a backend WHMCS addon module with no dev server or browser-facing UI of its own
— it only runs once installed inside a separate WHMCS site — so there is no page belonging to
this repo's own flow to browser-preview or screenshot. That's a fact about this repo, not
about host capability: headless Chrome, Xvfb, and the chrome-devtools MCP server are available
on this VPS, and browser verification (browser-preview, screenshots) can and should be used
for web/admin repos that do have a UI to check. Lint/typecheck/tests above are the full "done"
bar for this repo; the only checks genuinely unavailable on this host remain Xcode and
iOS/Android simulator boots — moot here anyway, since this repo has no mobile target (see
Host boundaries below).

## Guardrails

**Tier: unguarded** (not present in `~/.claude/hooks/billed_repos.json`) — direct push to
`main` is allowed; the global `git-safety-guard.py` PreToolUse hook does not block it here.

- `.orchestration/lanes.yml` `hot_files` is empty — no file/line is currently locked against
  the harness.
- `.orchestration/lanes.yml` `deploy_on_merge` is empty — nothing here auto-deploys on merge
  (see Deployment above).
- **Do not touch or force-push `feat/v1.2.0-overhaul` or `fix/review-hardening-v1.1.0`.** Per
  `.orchestration/lanes.yml`'s own comment, both branches back open PRs against the upstream
  fork source (`VercaaLLC/AutoAcceptOrders-WHMCS` #1, #2) — they belong to that upstream PR
  flow, not this harness. `force_push: allowed` in `lanes.yml` is a repo-wide default and does
  **not** override this exception.
- No Supabase/RLS, payments, or auth surface in this repo (`facts.supabase.present: false`;
  this is a standalone WHMCS addon with no external service credentials).

## Git & PR flow

Unguarded: direct push to `main` is fine — this repo is not in the guarded-repo map, and its
own docs have always said so. `.orchestration/lanes.yml` sets `merge: squash` and
`force_push: allowed` for ordinary branches (the two upstream-tracking branches above are the
one exception). No repo-specific shipper skill exists for this repo — use plain `git`/`gh`, or
`/ship` if a guarded-repo-style branch → PR → squash-merge flow is ever wanted anyway.

## Host boundaries — VPS vs Mac

Nothing here is Mac-only. This is a pure PHP/composer project with no `ios/` directory, no
Xcode project, and no Expo/EAS native build step (`facts.mac_only_paths` is empty). Fully
workable from the VPS or the Mac with no host-specific restriction. Verification is unaffected
by host either way: headless Chrome, Xvfb, and the chrome-devtools MCP server are available on
this VPS for browser-preview work, but this repo simply has no browser-facing UI of its own to
check (see Verification before done above), so that capability never comes up here. The only
host limits that remain real are Xcode and iOS/Android simulators — and this repo has no
mobile build to trigger them.

## Orca conventions

- Update the worktree comment at meaningful checkpoints:
  `orca-ide worktree set --worktree active --comment "<status>" --json`
- Set `--workspace-status in-review` when a PR opens on this repo's work.
- A dispatched worker sends `worker_done` exactly once, with an explicit
  `--outcome`, when finishing supervised orchestration work here — see
  fleet-command's `ORCHESTRATION.md` for the full coordinator recipe.

## Where to find more

| Topic | File |
|---|---|
| Full addon description, install steps | `README.md` |
| Version history | `CHANGELOG.md` |
| Lane config, protected upstream-PR branches, verify command | `.orchestration/lanes.yml` |
| WHMCS API stubs used by the test suite | `tests/Stub/` |
| Worktree setup script (`composer install` on worktree create) | `orca.yaml` |
| Session-start hook (fetches latest from origin on session start) | `.claude/hooks/session-start-fetch.sh` |

## Fleet context

This file follows the fleet-wide template
(`fleet-command/docs/fleet/AGENT-CONTEXT-TEMPLATE.md`, stamped above). Config drift between
this file and the template is caught automatically by `fleet-doctor.sh`, which runs as
part of fleet-command's daily sweep — see that repo's `PORTFOLIO.md` and `SWEEP.md` for
what gets reported and what (if anything) gets auto-dispatched.
