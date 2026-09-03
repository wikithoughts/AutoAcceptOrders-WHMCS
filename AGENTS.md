<!-- fleet-template: v1 | reconciled-against: fleet-kit/templates/AGENT-CONTEXT-TEMPLATE.md @ 35354d0 2026-09-02 -->
# AGENTS.md — AutoAcceptOrders-WHMCS

## What this repo is

WHMCS addon module that auto-accepts qualifying orders: it hooks into `InvoicePaid` (paid
orders) and `ShoppingCartCheckoutComplete` (free orders) to call WHMCS's `AcceptOrder` API
automatically, without manual admin review, guarded by an atomic database claim so a
concurrent hook fire can't double-accept the same order.

**This repo is a fork.** `wikithoughts/AutoAcceptOrders-WHMCS` is a GitHub fork of
`VercaaLLC/AutoAcceptOrders-WHMCS` (confirmed via `gh api repos/wikithoughts/AutoAcceptOrders-WHMCS`
— `fork: true`, `parent: VercaaLLC/AutoAcceptOrders-WHMCS`). Two branches on this fork,
`feat/v1.2.0-overhaul` and `fix/review-hardening-v1.1.0`, back open PRs against that upstream
repo (#1, #2) — see Guardrails below for the resulting hands-off rule. Otherwise this fork is
developed and released independently: it's a **public repo**, so CI here runs on unlimited
GitHub Actions minutes, not a shared quota.

## Stack & layout

- **Language:** PHP `^8.1` (`composer.json`; CI matrix covers 8.1/8.2/8.3 — 7.4 and 8.0 were
  dropped in commit `8993a32`, 2026-05-24). Two README.md facts are stale relative to what's
  actually enforced: its "PHP 7.4+" requirement line and its "PHP 7.4, 8.0, 8.1, and 8.2"
  CI-matrix line — trust `composer.json` and `.github/workflows/ci.yml` (8.1/8.2/8.3) instead.
- **Package manager:** composer
- **Frameworks/tooling:** WHMCS addon/hook API, PHPUnit `^9.6`, PHPStan `^1.10` (level 5),
  PHP-CS-Fixer `^3.45`

Layout:
- `modules/addons/auto_accept_orders/` — the module: `auto_accept_orders.php` (config/admin
  UI + the status/reprocess page), `hooks.php` (WHMCS hook registration), `autoload.php`,
  `lib/` (claim/accept logic, PSR-4 autoloaded as `AutoAcceptOrders\`)
- `tests/` — PHPUnit; WHMCS APIs are stubbed under `tests/Stub/` (excluded from PHPStan),
  autoloaded as `AutoAcceptOrders\Tests\`
- `vendor/` — composer output; never edit. `composer.lock` exists on disk but is
  `.gitignore`d — don't expect `git status` to be clean around it.
- `.orchestration/lanes.yml` — this repo's verify command and the two protected
  upstream-PR branches (see Guardrails)
- `.claude/hooks/session-start-fetch.sh` — repo-checked-in `SessionStart` hook, `git fetch
  --prune`s on every session start on any host with this repo cloned, and says so if the
  checkout is behind or has unpushed commits
- `orca.yaml` — worktree setup script (`composer install --no-progress --prefer-dist
  --no-interaction`)

## Commands

Verified by actually running each of these on the VPS copy this session (PHP 8.4.25,
Composer 2.10.3) — all grounded in `composer.json`'s `scripts` block, `phpunit.xml.dist`,
`phpstan.neon.dist`, and `.orchestration/lanes.yml`. No host-specific PATH note is needed
here: `composer`/`php` are plain binaries on PATH on the VPS, with nothing else to invoke
around them — unlike the prior version of this file, don't reintroduce a Mac-only Homebrew
aside unless a Mac session actually needs one.

Setup:
```bash
composer install
```

Test (56 tests, 73 assertions):
```bash
vendor/bin/phpunit
composer test        # = phpunit, per composer.json scripts.test
```

Typecheck (level 5):
```bash
vendor/bin/phpstan analyse
composer stan         # = phpstan analyse --no-progress
```

Lint (dry-run, no changes written):
```bash
composer cs           # = php-cs-fixer fix --dry-run --diff
```

Format (writes changes):
```bash
composer cs-fix       # = php-cs-fixer fix
```

Verify — the exact line `.orchestration/lanes.yml` runs, and what CI runs per PHP version in
its matrix:
```bash
composer test && composer stan
```

## Deployment

Copy `modules/addons/auto_accept_orders/` into a real WHMCS installation's
`modules/addons/`, then activate (or, on an upgrade, click **Upgrade**) from **Setup > Addon
Modules** in the WHMCS admin UI. No build step. Not deployed via CI —
`.orchestration/lanes.yml`'s `deploy_on_merge` is empty — so shipping a change to an actual
WHMCS site is always a manual step, separate from merging the PR.

## Verification before done

1. `composer install` if `vendor/` is stale or missing.
2. `composer test` (or `vendor/bin/phpunit` directly) — expect 56 tests, 73 assertions, all
   passing.
3. `composer stan` (or `vendor/bin/phpstan analyse`) — level 5, expect "No errors".
4. `composer cs` before a PR to check style; `composer cs-fix` to auto-fix. Not required by
   `.orchestration/lanes.yml`'s own `verify:` line, but CI's `ci.yml` runs it as a dry-run
   check on every push/PR, so a style violation fails CI even though it wouldn't fail the
   local `verify` line.
5. Combined, this is the one-line check the harness and CI both effectively run:
   `composer test && composer stan`.

This repo is a backend WHMCS addon module with no dev server or browser-facing UI of its
own — it only runs once installed inside a separate, external WHMCS site — so there is no
page belonging to this repo's own flow to browser-preview or screenshot. Per the fleet-wide
`rules/verification.md`, lint + typecheck + tests above are the full "done" bar here; naming
that plainly (rather than skipping a browser check silently) is what satisfies the rule for
a repo shaped like this one. The one thing genuinely unavailable on the VPS — Xcode /
iOS-Android simulator boots — is moot here anyway (see Host boundaries below).

## Guardrails

**Tier: unguarded** (confirmed absent from `~/.claude/hooks/billed_repos.json` on this
session) — direct push to `main` is fine; the global `git-safety-guard.py` `PreToolUse` hook
does not block it here. Still open a PR for reviewability, per this fleet's convention.

- **Never touch or force-push `feat/v1.2.0-overhaul` or `fix/review-hardening-v1.1.0`.**
  Per `.orchestration/lanes.yml`'s own comment, both back open PRs against the upstream fork
  source, `VercaaLLC/AutoAcceptOrders-WHMCS` (#1 and #2) — they belong to that upstream PR
  flow, not this harness. `.orchestration/lanes.yml`'s repo-wide `force_push: allowed`
  default does **not** override this exception.
- `.orchestration/lanes.yml`'s `hot_files` is empty — no file/line is currently locked
  against the harness — and `deploy_on_merge` is empty: nothing here auto-deploys on merge
  (see Deployment below).
- No Supabase/RLS, payment gateway, or auth-flow surface exists in this repo — it's a
  standalone WHMCS addon with no external service credentials to protect.
- Deployment to an actual WHMCS site is a manual step, not a merge-triggered one — see
  Deployment above.

## Git & PR flow

Unguarded: direct push to `main` is fine — this repo is not in the guarded-repo map, and
its own docs have always said so. `.orchestration/lanes.yml` sets `merge: squash` and
`force_push: allowed` for ordinary branches (the two upstream-tracking branches above are
the one exception — never force-push those). No repo-specific shipper skill exists here —
use plain `git`/`gh`, or the generic `/ship` skill if a guarded-repo-style branch → PR →
squash-merge flow is wanted anyway. This fleet's convention is to open a PR even on
unguarded repos for reviewability, rather than pushing straight to `main` by default.

## Host boundaries — VPS vs Mac

Nothing here is Mac-only. This is a pure PHP/composer project — no `ios/` directory, no
Xcode project, no Expo/EAS native build step — so there is no host-specific restriction to
name. Verified this session on the VPS copy only: `composer install`, `composer test`,
`composer stan`, and `composer cs`/`cs-fix` all ran clean there (PHP 8.4.25, Composer
2.10.3); nothing in the toolchain (plain composer scripts, no OS-specific binary) gives
reason to expect different behavior on the Mac, but that has not itself been re-verified
from a Mac session as part of this rewrite. This repo is registered on both hosts; when both
copies exist, treat the one you were told to work on as authoritative and don't cross-edit
the other from here. The only host limits that remain real fleet-wide — Xcode, iOS/Android
simulators, native mobile builds — don't apply to this repo at all: there is no mobile
target here to trigger them.

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
| Full addon description, install/upgrade steps, config fields, data model | `README.md` |
| Version history | `CHANGELOG.md` |
| Verify command, protected upstream-PR branches, merge/force-push policy | `.orchestration/lanes.yml` |
| WHMCS API stubs used by the test suite | `tests/Stub/` |
| Worktree setup script (`composer install` on worktree create) | `orca.yaml` |
| Session-start hook (fetches latest from origin, flags a stale/unpushed checkout) | `.claude/hooks/session-start-fetch.sh` |
| CI pipeline (lint, PHPUnit, PHPStan, cs-fixer dry-run across PHP 8.1–8.3) | `.github/workflows/ci.yml` |

## Fleet context

This file follows the fleet-wide template
(`fleet-kit/templates/AGENT-CONTEXT-TEMPLATE.md`, stamped above). Config drift between
this file and the template is caught automatically by `fleet-doctor.sh`, which runs as
part of fleet-command's daily sweep — see that repo's `PORTFOLIO.md` and `SWEEP.md` for
what gets reported and what (if anything) gets auto-dispatched.
