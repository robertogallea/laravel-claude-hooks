# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/). It stays on 0.x while the Hook Input classes settle.

## [Unreleased]

First release, 0.1.0.

### Added

- `HookCommand`, the base class of a Hook Command: a public `hook()` method typed with a Hook Input, returning a Hook Response or null for no opinion. Responses are always JSON with exit code 0, and a Hook Command that throws fails open ([ADR 0003](docs/adr/0003-fail-open-and-json-only-responses.md)).
- Typed Hook Inputs for `PreToolUse`, `PermissionRequest`, `PostToolUse`, `PostToolUseFailure`, `PostToolBatch`, `UserPromptSubmit`, `Stop`, `SubagentStop`, `SessionStart`, `PreCompact`, `Notification` and `SessionEnd`, with response helpers only where Claude Code honours them, and `GenericInput` for every other Hook Event, including ones added later.
- Union handlers, so one Hook Command can answer several Hook Events.
- `--payload=<file|->` and `--pretty` on every Hook Command, to replay a captured payload by hand; without a payload in a terminal it refuses with a hint.
- The `#[ClaudeHook]` Registration attribute: matcher, `if`, timeout, status message, async, fail-closed, Transport and event, repeatable. Registrations Claude Code would ignore or never run are rejected, and so is `asyncRewake`, since a Hook Command never exits 2 ([ADR 0002](docs/adr/0002-registration-lives-on-the-hook-command.md)).
- `claude-hooks:install`, which merges Managed Entries into `.claude/settings.json` without touching other entries, with `--local`, `--dry-run`, `--prune` and `--http=<base-url>`.
- `claude-hooks:doctor`, which reports missing, outdated and stale Managed Entries, invalid Registrations, missing binaries of Ready-made Hooks and http guards that do not fail closed, and fails on errors so that it can run in CI.
- Testing helpers: `InteractsWithClaudeHooks` with `runHook()` and `assertRegistered()`, `fake()` on every Hook Input, and assertions on the Hook Response in `HookResult`.
- Ready-made Hooks, each disabled until enabled in config: `guard-migrations`, `guard-artisan`, `session-context`, `pint` and `pest`.
- The http Transport: `POST /claude-hooks/{signature}`, local only, behind a bearer token. Hook Commands that are not http-safe stay on the command Transport ([ADR 0005](docs/adr/0005-http-transport-sees-the-servers-environment.md)).
- `make:claude-hook`, which generates a Hook Command and its Pest test, with publishable stubs.
- A Laravel Boost skill, `laravel-claude-hooks-writing-hooks`, for coding agents.
- A scheduled CI job that compares the package's Hook Events with the Claude Code hooks reference.

[Unreleased]: https://github.com/robertogallea/laravel-claude-hooks/commits/main
