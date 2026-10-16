---
name: laravel-claude-hooks-writing-hooks
description: Write, test and install a Claude Code hook as a Laravel Hook Command with robertogallea/laravel-claude-hooks. Use when the user wants a rule enforced on Claude Code every time (allow, deny or ask about a tool call, protect files or databases, format or test after edits, add context at session start), asks for a hook or an agent guardrail, or asks where a rule for the agent should live.
---

# Writing a Hook Command

A Hook is a command Claude Code runs at a fixed point of a session, whatever the model decides. A Hook Command is an Artisan command that answers one: it receives a typed Hook Input, can use the container, config and database, and answers with a helper of that input. `claude-hooks:install` writes it into `.claude/settings.json`.

## 1. Choose where the rule lives

A hook is a guarantee with a cost: a PHP process per call. Sort each rule the user describes:

| The rule | Where it goes |
| --- | --- |
| a fixed list of paths or commands ("never touch `.env`", "never run `rm -rf`") | a permission rule in `.claude/settings.json`: `{"permissions": {"deny": ["Edit(./.env)", "Bash(rm -rf *)"]}}` |
| a preference or convention ("prefer Pest", "use Form Requests") | a line in `CLAUDE.md` |
| a check every change must pass, whoever made it | a git hook or CI |
| a rule that must look at the application: its database, config, environment, files or test results | a Hook Command |

A rule can earn two places: a Pint hook during the session plus `pint --test` in CI is a good pair.

Before writing a new Hook Command, check the Ready-made Hooks. Each is enabled by setting `enabled` to `true` under `hooks.<name>` in `config/claude-hooks.php` (publish it with `php artisan vendor:publish --tag=claude-hooks-config`), then going to step 5:

- `guard-migrations`: denies edits to migrations that already ran;
- `guard-artisan`: denies `migrate:fresh`, `db:wipe` and the like unless the database is local;
- `session-context`: tells Claude the versions, environment, connection and pending migrations at session start;
- `pint`: formats each edited PHP file;
- `pest`: sends Claude back once when the test suite fails as it stops.

Step 1 is complete when every rule has one place. Write the rules that are not Hook Commands where they belong, and stop if none is left.

## 2. Generate the class

Pick the Hook Event from what the hook must do: `PreToolUse` to allow, deny or ask about a tool call before it runs; `PostToolUse` to react after it succeeded; `Stop` to send Claude back to work; `SessionStart` or `UserPromptSubmit` to add context. Then:

```bash
php artisan make:claude-hook GuardTranslations --event=PreToolUse
```

This writes `app/Console/Commands/ClaudeHooks/GuardTranslations.php`, with the signature `claude-hooks:guard-translations`, and `tests/Feature/ClaudeHooks/GuardTranslationsTest.php`.

## 3. Write `hook()`

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

- The type of `hook()`'s first parameter is the Hook Input, and it decides the Hook Event. Other parameters are resolved from the container.
- Return `null` for no opinion: the normal flow continues. Return early with `null` for every case the rule does not cover.
- Build the answer with the Hook Input's helpers, such as `$input->deny($reason)`. Each Hook Input has only the helpers its event honours; [references/hook-inputs.md](references/hook-inputs.md) lists them, with the accessors that read the payload.
- Write every reason as an instruction for Claude: what to do instead. Claude acts on it.
- On a `Stop` or `SubagentStop` hook, return `null` or a `systemMessage()` when `$input->stopHookActive()` is true, so that Claude is sent back at most once.

### The Registration

`#[ClaudeHook]` on the class tells Claude Code when to call the hook:

- `matcher`: the tool name, or a regex of names, on tool events: `'Edit|Write'`, `'Bash'`. Events such as `Stop` and `UserPromptSubmit` take none.
- `if`: one permission rule, such as `'Bash(php artisan *)'`, on tool events only. Claude Code evaluates it before starting PHP, so add one whenever the rule concerns a subset of calls. The attribute is repeatable: two filters are two attributes, since `if` has no `||`.
- `timeout`, `statusMessage`, `async`, `asyncRewake`, `transport`, and `event` when `hook()` takes a union such as `SessionStartInput|UserPromptSubmitInput`.

### Fail-open or fail-closed

A Hook Command that throws fails open: Claude Code treats the error as non-blocking and the action proceeds. Keep that default for formatting, context and feedback hooks, where a broken database should never freeze every edit. Add `failClosed: true` to a guard the user relies on for safety, such as one that protects a database or secrets: a failure then blocks the action. Claude Code ignores fail-closed on `Stop` and `SubagentStop` and on async hooks, and install rejects those combinations.

### The http Transport

Leave `transport` at its default. When a Hook Command's answer depends on the environment (the database `.env` points at, `app()->environment()`), override `public static function httpSafe(): bool` to return `false`, so it never runs on a server with another environment.

## 4. Test it with `runHook`

Write one Pest test per branch of `hook()`: each answer it gives, and each case where it has no opinion. Add an `assertRegistered()` for each Registration.

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

The fakes and every assertion are in [references/hook-inputs.md](references/hook-inputs.md). Step 4 is complete when every branch has a test and the file passes.

## 5. Install and check

```bash
php artisan claude-hooks:install
php artisan claude-hooks:doctor
```

Install writes a Managed Entry for every Registration into `.claude/settings.json` and leaves every other entry alone. Use `--local` for `settings.local.json` (personal hooks, such as notifications), `--dry-run` to preview, and `--prune` after deleting or renaming a Hook Command. Step 5 is complete when doctor reports OK. Tell the user to commit `.claude/settings.json`; `/hooks` inside Claude Code lists the hooks it loaded.
