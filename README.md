# Laravel Claude Hooks

Claude Code hooks as tested Artisan commands in your Laravel app.

A line in `CLAUDE.md` such as "never edit a migration that already ran" is advice: Claude follows it most of the time, and forgets it after a long session or a compaction. A hook is a command that Claude Code runs at a fixed point of the session, whatever the model decides. Hooks are usually Bash scripts with `jq`, though, and a Bash script cannot see which migrations ran, which database `.env` points to or what the test suite says.

This package lets the application answer hooks itself. A Hook Command is an Artisan command with the container, the config and the database available: it receives a typed Hook Input and answers with a helper of that input. The package reads the payload, writes the JSON Claude Code expects, and keeps `.claude/settings.json` in sync with your Hook Commands.

<!-- readme-test: hook-command -->
```php
<?php

namespace App\Console\Commands\ClaudeHooks;

use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;

#[ClaudeHook(matcher: 'Edit|Write')]
class GuardTranslations extends HookCommand
{
    protected $signature = 'claude-hooks:guard-translations';

    protected $description = 'PreToolUse hook: keep Claude out of the synced translation files';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        $path = (string) $input->filePath();
        $fallback = config('app.fallback_locale');

        if (! str_starts_with($path, lang_path()) || str_starts_with($path, lang_path($fallback))) {
            return null;
        }

        return $input->deny("Only lang/{$fallback} is edited by hand: the other locales are synced from the translation service. Change the {$fallback} strings instead.");
    }
}
```

## Features

- **[Typed Hook Inputs](#writing-a-hook-command)** for twelve Hook Events, with response helpers only where Claude Code honours them: `deny()` exists on `PreToolUseInput`, not on `NotificationInput`.
- **[Registration as an attribute](#registration)**: matcher, `if` filter, timeout, Transport and failure stance live on the Hook Command, and invalid combinations are rejected before they reach Claude Code.
- **[Install and doctor](#install-and-doctor)**: `claude-hooks:install` merges Managed Entries into `.claude/settings.json` without touching yours; `claude-hooks:doctor` reports drift and runs in CI.
- **[Testing helpers](#testing)**: run a Hook Command in-process with a fake Hook Input, and assert on what Claude Code would see.
- **[Five Ready-made Hooks](#ready-made-hooks)**: guard migrations that already ran, guard destructive Artisan commands, session context, Pint on edit, Pest on stop.
- **[An http Transport](#the-http-transport)** for frequent hooks, answered by the application you already have running.
- **[`make:claude-hook`](#generating-a-hook-command)** and a [Laravel Boost skill](#coding-agents-laravel-boost) that teaches your coding agent to write its own Hook Commands.

## Installation

```bash
composer require robertogallea/laravel-claude-hooks
```

Require it as a normal dependency, not with `--dev`: Laravel discovers the commands in `app/Console/Commands` in production too, and a Hook Command whose parent class is missing breaks Artisan there.

Publish the config to enable the Ready-made Hooks or the http Transport:

```bash
php artisan vendor:publish --tag=claude-hooks-config
```

## Writing a Hook Command

A Hook Command extends `HookCommand` and declares a public `hook()` method. The type of its first parameter is the Hook Input, and decides the Hook Event: `GuardTranslations` above takes a `PreToolUseInput`, so it answers `PreToolUse`. The method can ask the container for anything else it needs, as `handle()` would.

`hook()` returns a Hook Response built by a helper of its Hook Input, or `null` for no opinion: the normal flow continues as if the hook were not there. The response is always JSON on stdout with exit code 0; the package never exits with 2 ([ADR 0003](docs/adr/0003-fail-open-and-json-only-responses.md)).

When Claude denies an edit, it reads the reason and acts on it, so write the reason as an instruction: what to do instead.

### Response helpers

| Hook Input | Helpers |
|---|---|
| `PreToolUseInput` | `allow(?reason, ?updatedInput)`, `deny(reason)`, `ask(?reason)`, `defer(?reason)`, `updateInput(input)`, `addContext(context)` |
| `PermissionRequestInput` | `allow(?updatedInput, ?updatedPermissions)`, `deny(?message, interrupt: false)` |
| `PostToolUseInput` | `block(?reason)`, `addContext(context)`, `replaceOutput(output)` |
| `PostToolUseFailureInput` | `addContext(context)` |
| `PostToolBatchInput` | `block(?reason)`, `addContext(context)` |
| `UserPromptSubmitInput` | `block(?reason)`, `addContext(context)`, `setSessionTitle(title)` |
| `StopInput`, `SubagentStopInput` | `block(reason)`, `addContext(context)` |
| `SessionStartInput` | `addContext(context)`, `setSessionTitle(title)`, `setInitialUserMessage(message)` |
| `PreCompactInput` | `block(?reason)` |
| `NotificationInput`, `SessionEndInput` | none: Claude Code discards their output, so use them for side effects |
| `GenericInput` | none: every other Hook Event, including ones added by later Claude Code releases |

Every Hook Input in the table that has helpers, except `PreCompactInput`, also has `systemMessage(message)`, a warning shown to the user, and `stopSession(reason)`, which stops Claude entirely. A response chains them too: `$input->deny($reason)->withSystemMessage('Blocked an edit to lang/it.')`. `$response->and($other)` combines two responses for the same Hook Event.

On a `Stop` hook, check `$input->stopHookActive()` before blocking: it is true when Claude is already continuing because a stop hook blocked it, and a hook that blocks every time keeps Claude in a loop. `SessionEnd` hooks share a default budget of 1.5 seconds, which a Hook Command fits, but leave the slow work elsewhere.

### Reading the input

Every Hook Input has `sessionId()`, `cwd()`, `permissionMode()`, `transcriptPath()`, `agentId()`, `agentType()`, `eventName()`, the raw `payload` array, and `get('dot.path')` for any field. The tool events add `toolName()`, `toolInput('dot.path')`, `filePath()`, `command()` and `toolUseId()`. The rest are on their own class: `prompt()` on `UserPromptSubmitInput`, `source()` on `SessionStartInput`, `toolResponse()` on `PostToolUseInput`, `lastAssistantMessage()` on `StopInput`, and so on.

A handler can answer more than one Hook Event with a union type, such as `SessionStartInput|UserPromptSubmitInput`. Each Registration then names its event: `#[ClaudeHook(event: Event::SessionStart)]`.

### Running a Hook Command by hand

Run without a payload in a terminal, a Hook Command refuses with a hint rather than waiting on stdin. To debug one, replay a captured payload:

```bash
php artisan claude-hooks:guard-translations --payload=storage/payload.json --pretty
```

`--payload=-` reads stdin, and `--pretty` indents the JSON.

## Registration

`#[ClaudeHook]` declares how Claude Code calls the Hook Command. A Hook Command without it gets one Registration for its event, with no filters.

| Parameter | Meaning |
|---|---|
| `matcher` | what the event filters on, such as the tool name: `'Edit\|Write'`, `'Bash'`; a regular expression, passed through unchanged |
| `if` | one permission rule, such as `'Bash(php artisan *)'`; only the tool events evaluate it |
| `timeout` | seconds before Claude Code cancels the hook |
| `statusMessage` | the spinner message while the hook runs |
| `async` | run in the background, without blocking |
| `asyncRewake` | run in the background, and wake Claude up when it fails |
| `failClosed` | block the action when the hook fails, instead of letting it proceed |
| `transport` | `Transport::Command` (default) or `Transport::Http` |
| `event` | the Hook Event, needed only when `hook()` takes a union |

The attribute is repeatable. Claude Code has no `||` in an `if` filter, so two filters are two Registrations: the Ready-made `pint` hook declares `#[ClaudeHook(matcher: 'Edit|Write', if: 'Edit(*.php)')]` and the same with `Write(*.php)`.

Install and doctor reject a Registration that Claude Code would silently ignore or never run: an `if` on an event that is not a tool event, a matcher on an event that takes none (`Stop`, `UserPromptSubmit`, `PostToolBatch` and a few others), `failClosed` on `Stop` or `SubagentStop`, `failClosed` together with `async`, and `async` over http.

### Fail-open, unless you ask

A Hook Command that throws makes Artisan exit with 1, which Claude Code treats as a non-blocking error: the action proceeds. That is the right default for most hooks, since a database that is down should not freeze every edit. A guard you rely on should fail closed instead: `#[ClaudeHook(matcher: 'Bash', failClosed: true)]` writes `"onFailure": "block"`, and a broken hook then blocks the action.

## Install and doctor

`claude-hooks:install` finds every Artisan command that extends `HookCommand` (Laravel already discovers the ones in `app/Console/Commands`), plus the enabled Ready-made Hooks, and writes a Managed Entry for each Registration:

```bash
php artisan claude-hooks:install
```

For `GuardTranslations`, `.claude/settings.json` gets:

<!-- readme-test: settings-command -->
```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" claude-hooks:guard-translations"
          }
        ]
      }
    ]
  }
}
```

A Managed Entry is recognised by its handler, the `php "$CLAUDE_PROJECT_DIR/artisan" <signature>` command or the package's http route. Install adds and updates those; every other entry and settings key stays as it was, and running it twice changes nothing. Commit the file, so everybody who opens the project with Claude Code gets the same hooks.

- `--local` writes `.claude/settings.local.json`, which stays out of the repository.
- `--dry-run` prints what would change and writes nothing.
- `--prune` removes Managed Entries that no Registration matches any more, such as the entry of a Hook Command you deleted. Without it, install only lists them as stale.
- `--http=<base-url>` switches every http-safe Registration to the [http Transport](#the-http-transport).

`claude-hooks:doctor` checks that the settings file calls every Hook Command as declared. It reports missing, outdated and stale Managed Entries, invalid Registrations, enabled Ready-made Hooks whose binaries are missing, and http guards that do not fail closed. Errors make it exit with 1, so it can run in CI next to the tests:

```bash
php artisan claude-hooks:doctor
```

It takes `--local` and `--http=<base-url>` like install. `/hooks` inside Claude Code lists the hooks it actually loaded, which is the next place to look when one does not fire.

## Testing

`runHook()` runs a Hook Command in-process, through the Artisan kernel, with a fake Hook Input, and returns what Claude Code would see. Add `InteractsWithClaudeHooks` with Pest's `uses()`:

<!-- readme-test: hook-command-test -->
```php
<?php

use App\Console\Commands\ClaudeHooks\GuardTranslations;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Testing\InteractsWithClaudeHooks;

uses(InteractsWithClaudeHooks::class);

it('keeps Claude out of the synced locales', function () {
    $this->runHook(GuardTranslations::class, PreToolUseInput::fake()->edit(lang_path('it/validation.php')))
        ->assertDenied('synced from the translation service');
});

it('lets Claude edit the fallback locale', function () {
    $this->runHook(GuardTranslations::class, PreToolUseInput::fake()->write(lang_path('en/validation.php')))
        ->assertNoOpinion();
});

it('has no opinion on Bash commands', function () {
    $this->runHook(GuardTranslations::class, PreToolUseInput::fake()->bash('ls lang'))
        ->assertNoOpinion();
});

it('answers edits and writes', function () {
    $this->assertRegistered(GuardTranslations::class, Event::PreToolUse, matcher: 'Edit|Write');
});
```

Every Hook Input has `fake()`, with realistic common fields and overrides as an array: `StopInput::fake(['stop_hook_active' => true])`. The tool events add `edit($path)`, `write($path, $content)` and `bash($command)`, `with([...])` replaces any field, and `GenericInput::fakeFor('PreModelSwitch')` fakes an event the package does not model.

The result asserts the exit code is 0, and offers:

- `assertNoOpinion()`, `assertNoDecision()`;
- `assertAllowed()`, `assertDenied()`, `assertAsked()`, `assertDeferred()`, `assertBlocked()`, each with an optional text the reason must contain;
- `assertContextContains()`, `assertSystemMessage()`, `assertStoppedSession()`, `assertSessionTitle()`, `assertInitialUserMessage()`;
- `assertUpdatedInput()`, `assertUpdatedOutput()`, `assertUpdatedPermissions()`, `assertInterrupted()`;
- `json` and `get('dot.path')` for anything else.

`assertRegistered($command, $event, matcher: ..., if: ...)` checks a Registration, with null meaning no matcher or no `if`.

## Ready-made Hooks

The package ships the five hooks of the [blog post](https://robertogallea.com/posts/development/claude-code-hooks-in-a-laravel-app) as Hook Commands. Each one is off until you set `enabled` to `true` in `config/claude-hooks.php` and run `claude-hooks:install`.

| Hook | Event | What it does | Options |
|---|---|---|---|
| `guard-migrations` | `PreToolUse` on `Edit\|Write` and on `Bash` | denies writes to a migration that already ran on the database Claude Code's environment points at, and tells Claude to create a new one | `mode` (`deny` or `ask`), `paths` (default `database_path('migrations')`) |
| `guard-artisan` | `PreToolUse` on `Bash`, `if: Bash(php artisan *)`, fails closed | denies `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` and `db:wipe`, also inside compound commands, unless the connection is local: SQLite always is, another database only when the app is local and its host is in `local_hosts` | `mode`, `destructive` (a regex alternation), `local_hosts` (default `127.0.0.1`, `localhost`, `mysql`, `pgsql`) |
| `session-context` | `SessionStart` | tells Claude the Laravel and PHP versions, the environment, the default connection and the pending migrations, also on resume, clear and compaction | `lines` (turn any of the four off), `paths` |
| `pint` | `PostToolUse` on `Edit\|Write`, `if: Edit(*.php)` and `Write(*.php)` | runs Pint quietly on the edited PHP file, Blade templates excepted; when Pint fails, Claude is told why | `binary` (default `vendor/bin/pint`) |
| `pest` | `Stop`, timeout 300 s | when git reports changed PHP files, runs the suite, without the variables of `.env` as `artisan test` does; on failure sends Claude back once with the tail of the output, and if it still fails at the next stop, lets Claude stop and warns you | `command` (default `vendor/bin/pest --compact`), `tail` (default 40 lines) |

Every hook also takes `timeout`, and the ones with a matcher take `matchers`, which maps a declared matcher to the one to use instead. With `guard-artisan` enabled, install writes:

<!-- readme-test: settings-guard-artisan -->
```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" claude-hooks:guard-artisan",
            "if": "Bash(php artisan *)",
            "onFailure": "block"
          }
        ]
      }
    ]
  }
}
```

To change a rule beyond its options, extend the class (`GuardMigrations::hasRun()`, `GuardArtisan::isLocal()`, `Pint::binary()`, `Pest::testCommand()`), register your subclass as a command, and leave the original disabled. The subclass keeps the Registrations of its parent.

## The http Transport

Each Hook Command boots Laravel. For a hook that fires on every edit, the http Transport sends the payload to the application you already have running (Sail, Herd, `artisan serve`) instead. Enable it in `config/claude-hooks.php` and set the same token on both sides:

```dotenv
CLAUDE_HOOKS_TOKEN=a-long-random-string
```

The route `POST /claude-hooks/{signature}` exists only when `claude-hooks.http.enabled` is true and the environment is `local`, and it answers only requests that carry the token. Claude Code sends `CLAUDE_HOOKS_TOKEN` from its own environment, so export it in the shell you start Claude Code from. Then:

```bash
php artisan claude-hooks:install --http=http://localhost:8000
```

<!-- readme-test: settings-http -->
```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "http",
            "url": "http://localhost:8000/claude-hooks/claude-hooks:guard-translations",
            "headers": {
              "Authorization": "Bearer $CLAUDE_HOOKS_TOKEN"
            },
            "allowedEnvVars": [
              "CLAUDE_HOOKS_TOKEN"
            ]
          }
        ]
      }
    ]
  }
}
```

`transport: Transport::Http` on a Registration puts a single hook on http, with the URL from `claude-hooks.http.url`.

There is a catch ([ADR 0005](docs/adr/0005-http-transport-sees-the-servers-environment.md)). Over the command Transport a Hook Command inherits Claude Code's environment, so it sees the database the agent's own `php artisan` calls would use. Over http the request is answered by the server, with the server's `.env`. A guard could approve a `migrate:fresh` that would run against a different database. So a Hook Command whose answer depends on the environment overrides `httpSafe()` to return `false`: install keeps it on the command Transport, and the route refuses it. `guard-migrations`, `guard-artisan` and `session-context` are command-only; `pint` and `pest` are http-safe.

When the server is not running, Claude Code treats the refused connection as a non-blocking error and the action proceeds, so doctor warns about every http guard (`PreToolUse`, `PermissionRequest`, `UserPromptSubmit`) that does not fail closed.

## Generating a Hook Command

```bash
php artisan make:claude-hook GuardTranslations --event=PreToolUse
```

This writes `app/Console/Commands/ClaudeHooks/GuardTranslations.php`, with `hook()` typed for the event's Hook Input, a `#[ClaudeHook]` attribute (with a matcher placeholder on events that take one) and the signature `claude-hooks:guard-translations`, plus a Pest test in `tests/Feature/ClaudeHooks`. `--event` defaults to `PreToolUse` and takes any event with a typed Hook Input; `--no-test` skips the test, `--force` overwrites. Publish the stubs with `php artisan vendor:publish --tag=claude-hooks-stubs` to change what it writes.

## Performance

A Hook Command boots the framework on every call. Measured on a Laravel 13.37 app on PHP 8.5.9 ([ADR 0004](docs/adr/0004-no-lean-bootstrap.md)):

| Setup | Time per call |
|---|---|
| default | about 130 ms |
| the Composer autoloader alone | about 40 ms |
| after `php artisan optimize` | no change |
| `opcache.enable_cli=1` alone | about 290 ms |
| `opcache.enable_cli=1` with `opcache.file_cache` | about 70 ms |

On a `PreToolUse` hook for edits, 130 ms is invisible next to the time the model takes to write the edit. It adds up on a hook that fires dozens of times per turn, and there are three things to do about it, before reaching for a leaner bootstrap that would give up the container and the database, which are the reason to write the hook in Laravel.

**Cache OPcache on disk.** OPcache on the CLI only helps with a file cache, since every call is a new process. In the `php.ini` of the PHP that runs Claude Code's hooks:

```ini
opcache.enable_cli=1
opcache.file_cache=/tmp/php-opcache
```

Create the folder first (`mkdir -p /tmp/php-opcache`).

**Filter with `if`.** An `if` filter is evaluated by Claude Code, so a call it filters out never starts PHP. `guard-artisan` boots Laravel only for `php artisan` commands, and `pint` only for PHP files.

**Use the http Transport** for frequent http-safe hooks: the application is already booted.

## Where should this rule live?

A hook is not always the right place. The [blog post](https://robertogallea.com/posts/development/claude-code-hooks-in-a-laravel-app) goes through each of the Ready-made Hooks and the other places its rule could live; in short:

- **a fixed list of paths or commands** is a permission rule in `.claude/settings.json` (`"deny": ["Edit(./.env)"]`): no process, no boot time;
- **a rule that needs to look at the application**, its database, config or tests, is a hook, and in a Laravel app a Hook Command;
- **preferences and conventions** stay in `CLAUDE.md`, as advice;
- **a check for every change, whoever made it**, is a git hook or CI. Keep CI as the last check, since it is the only one that also runs for humans and other agents.

## Coding agents (Laravel Boost)

With [Laravel Boost](https://github.com/laravel/boost) installed, `php artisan boost:install` (or `boost:update`) offers this package's skill, `laravel-claude-hooks-writing-hooks`. It teaches your coding agent when a hook is the right place for a rule, how to write, register and test a Hook Command, and how to install it.

## Requirements

- PHP ^8.3
- Laravel 12 or 13
- Claude Code with the hooks the [hooks reference](https://code.claude.com/docs/en/hooks) describes; the package follows the reference as read on 2026-10-16.

Design decisions are recorded in [`docs/adr`](docs/adr), and changes between releases in the [changelog](CHANGELOG.md).

## Package development

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (composer format to fix)
```

## License

MIT. See [LICENSE](LICENSE).
