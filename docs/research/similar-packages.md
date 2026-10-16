# Similar packages

Researched 2026-10-16. Star counts and release dates are as of that day.

## Summary

There is no direct competitor. No Laravel package offers an Artisan-based hook base class with typed inputs, response helpers, an install command that merges `settings.json`, Pest helpers, ready-made hooks and an HTTP hook route.

- The closest PHP options are two plain-PHP SDKs:
  - [beyondcode/claude-hooks-sdk](https://github.com/beyondcode/claude-hooks-sdk) is stale and covers only 5 events.
  - [gerard/claude-code-hooks](https://github.com/gerard-labs/claude-code-hooks) is thorough but alpha and has no users.
- [laravel/boost](https://github.com/laravel/boost) ships no hooks.

## Comparison

| Package | Ecosystem | Typed inputs | Response helpers | Installer / settings merge | Test helpers | Multi-agent | Ready-made hooks | Activity (2026-10-16) |
|---|---|---|---|---|---|---|---|---|
| beyondcode/claude-hooks-sdk | PHP | 5 event classes, array tool input | Yes, uses the deprecated PreToolUse `decision` | No | No | No | No | 68 stars, v0.1.0 (2025-07-03) |
| gerard/claude-code-hooks | PHP 8.3 | 29+ event DTOs, 12 tool inputs | 13+ immutable builders | No (has a linter and resolver) | Unverified | No | No | 1 star, v0.0.1 (2026-05-13), 0 installs |
| laravel/boost | Laravel | n/a | n/a | Writes MCP config, guidelines and skills only | n/a | Yes (14 agents) | No | 3.6k stars, v2.10.3 (2026-10-07) |
| cc-hooks-ts | TypeScript | Yes, from Agent SDK tool types | Yes | No | Type tests only | No | No | 39 stars, v2.1.281 (pushed 2026-10-16) |
| @hasna/hooks | Node | Unverified | Unverified | Yes (CLI) | Unverified | Claude, Codex, Gemini (partial) | 57 (README) | v0.12.21, stars unverified |
| cchooks | Python | Yes, per README | Yes, per README | No | Unverified | No | No | 132 stars, v0.1.5 (2025-11-12) |
| claude-hooks (johnlindquist) | TS/Bun | Yes, 4 events | Utilities | Generates `.claude/settings.json` | No | No | No | 396 stars, last push 2025-08-08 |

## Packages

### beyondcode/claude-hooks-sdk

- **Entry point:** `ClaudeHook::create()` reads stdin and maps `hook_event_name` to one of only five classes: PreToolUse, PostToolUse, Notification, Stop and SubagentStop ([ClaudeHook.php](https://github.com/beyondcode/claude-hooks-sdk/blob/main/src/ClaudeHook.php)).
- **Unknown events:** any other event, such as SessionStart or UserPromptSubmit, throws `InvalidArgumentException` (same file).
- **PreToolUse responses:** `block()` and `approve()` write a top-level `decision` field ([Response.php](https://github.com/beyondcode/claude-hooks-sdk/blob/main/src/Hooks/Response.php)). The [hooks docs](https://code.claude.com/docs/en/hooks) say that field is deprecated for PreToolUse in favour of `hookSpecificOutput.permissionDecision`.
- **Exit code bug:** `stop()` exits with code 1 ([Response.php](https://github.com/beyondcode/claude-hooks-sdk/blob/main/src/Hooks/Response.php)), which the docs treat as a *non-blocking* error. Blocking requires exit 2 ([docs](https://code.claude.com/docs/en/hooks)).
- **Dependencies and tooling:** requires only `php ^8.3`, with no Laravel integration. Pest is used only for its own tests ([composer.json](https://github.com/beyondcode/claude-hooks-sdk/blob/main/composer.json)).
- **Activity:** last release [0.1.0, 2025-07-03](https://packagist.org/packages/beyondcode/claude-hooks-sdk).

### gerard/claude-code-hooks

- **Events:** `HookEventFactory::fromPayload()` returns typed event DTOs, and unknown events become `UnknownHookEvent` ([HookEventFactory.php](https://github.com/gerard-labs/claude-code-hooks/blob/main/src/Event/HookEventFactory.php)).
- **Tool inputs:** typed classes under [src/Event/Tool/Input](https://github.com/gerard-labs/claude-code-hooks/tree/main/src/Event/Tool/Input), including `RawToolInput`.
- **Responses:** `PreToolUseResponse::allow/deny/ask/defer()` build `hookSpecificOutput.permissionDecision`, and `withUpdatedInput()` returns a modified copy ([PreToolUseResponse.php](https://github.com/gerard-labs/claude-code-hooks/blob/main/src/Response/Tool/PreToolUseResponse.php)). This matches the [docs](https://code.claude.com/docs/en/hooks).
- **Extras:**
  - a settings precedence resolver ([src/Resolver](https://github.com/gerard-labs/claude-code-hooks/tree/main/src/Resolver));
  - a linter with rules such as `Cch002UnknownEventName` ([src/Linter](https://github.com/gerard-labs/claude-code-hooks/tree/main/src/Linter));
  - a transcript reader, scanners and a cost calculator;
  - a docs drift-check script ([bin/check-doc-drift](https://github.com/gerard-labs/claude-code-hooks/blob/main/bin/check-doc-drift)).
- **Dependencies:** pulls in `symfony/http-client` and `symfony/yaml` ([composer.json](https://github.com/gerard-labs/claude-code-hooks/blob/main/composer.json)).
- **Unverified:** I did not inspect it for an installer or test helpers.
- **Activity:** [v0.0.1, 0 installs](https://packagist.org/packages/gerard/claude-code-hooks).

### laravel/boost

- **No hooks in the repo:** no path in the repo tree mentions hooks, apart from a Livewire JS reference file ([tree](https://github.com/laravel/boost/tree/main)).
- **What its Claude Code integration does:** the agent class handles only MCP config (`.mcp.json`), guidelines (`CLAUDE.md`) and skills ([ClaudeCode.php](https://github.com/laravel/boost/blob/main/src/Install/Agents/ClaudeCode.php)).
- **Other agents:** it supports 14 agents, including Codex and Cursor ([src/Install/Agents](https://github.com/laravel/boost/tree/main/src/Install/Agents)). This makes it a likely partner for a hooks package, not a competitor.

### cc-hooks-ts

- **API:** you write `defineHook({ trigger, run })` and run it with `runHook()` ([index.ts](https://github.com/sushichan044/cc-hooks-ts/blob/main/src/index.ts)).
- **Input validation:** `runHook` reads stdin and checks it against valibot schemas ([run.ts](https://github.com/sushichan044/cc-hooks-ts/blob/main/src/run.ts)).
- **Responses:** the context offers `success`, `blockingError` (exit 2), `nonBlockingError`, `json` and async `defer` ([context.ts](https://github.com/sushichan044/cc-hooks-ts/blob/main/src/context.ts), [run.ts](https://github.com/sushichan044/cc-hooks-ts/blob/main/src/run.ts)).
- **Tool types:** taken from `@anthropic-ai/claude-agent-sdk/sdk-tools`, and you can add your own via declaration merging ([index.ts](https://github.com/sushichan044/cc-hooks-ts/blob/main/src/index.ts)).
- **Versioning:** its version numbers follow Claude Code's whenever hooks change ([README](https://github.com/sushichan044/cc-hooks-ts#readme)).

### @hasna/hooks

- **What it is:** an installer CLI with `hooks`, `hooks-mcp` and `hooks-serve` binaries ([npm package.json](https://www.npmjs.com/package/@hasna/hooks)). It is not an SDK.
- **Agent targets:** the bundled code references `.claude/settings.json`, `.codex/hooks.json`, `.codex/config.toml` and `.gemini/settings.json`. I checked this in the npm tarball for v0.12.21.
- **Gemini caveat:** the [README](https://www.npmjs.com/package/@hasna/hooks) says its shell guards don't yet recognise Gemini's `run_shell_command` tool.
- **Hook count:** the README claims 57 hooks, and the tarball's `hooks/` directory has 65 entries.
- **Unverified:** star count. The repo URL in its metadata (`hasna/apps`) returns 404.

### cchooks (Python)

- `create_context()` parses stdin and detects the hook type, and it supports both exit-code mode and JSON control ([PyPI](https://pypi.org/project/cchooks/), [GitHub](https://github.com/GowayLee/cchooks)).
- **Unverified:** I didn't read its source.

### claude-hooks (johnlindquist)

- `npx claude-hooks` generates `.claude/settings.json` and typed handlers in `.claude/hooks/` ([README](https://github.com/johnlindquist/claude-hooks)). It requires Bun.
- **Unverified:** whether it merges an existing `settings.json` rather than overwriting it.

### Laravel setup kits (not hook frameworks)

- [mohamed-ashraf-elsaed/claude-kit](https://github.com/mohamed-ashraf-elsaed/claude-kit) deep-merges hook wiring into `.claude/settings.json` and ships a Stop-hook quality gate that requires N clean runs in a row (README).
- [jorgecortesdev/laravel-claudify](https://github.com/jorgecortesdev/laravel-claudify) installs Pint/Prettier format hooks ([Packagist](https://packagist.org/packages/jorgecortesdev/laravel-claudify)).
- [imageplus/claude-guardrails](https://packagist.org/packages/imageplus/claude-guardrails) ships PreToolUse guards that install themselves into `.claude/settings.json`.
- [heyosseus/sloppy](https://github.com/Heyosseus/sloppy) ships hooks for Claude Code, Gemini CLI and Codex (README).
- **Unverified:** I checked all four against READMEs only, not source.

## Ideas worth borrowing

1. **Forward-compatible decoding:** turn unknown events into an `UnknownHookEvent`-style object instead of throwing (gerard). beyondcode's throwing breaks on new events.
2. **Up-to-date responses:** use `hookSpecificOutput.permissionDecision` with allow/deny/ask/defer and `updatedInput` (gerard). Make the blocking response exit 2, not 1 (cc-hooks-ts does this right; beyondcode doesn't).
3. **Cheap types for tool inputs:** a raw fallback for `mcp__*` tools, and generated types where possible (cc-hooks-ts).
4. **Docs drift CI:** a scheduled job that diffs [the hooks docs](https://code.claude.com/docs/en/hooks) (gerard). Optionally tie versions to the spec (cc-hooks-ts).
5. **Config linting:** a `doctor`/`lint` command for unknown events, missing matchers and HTTP hooks without a timeout (gerard).
6. **Safe installs:** deep-merge into an existing `settings.json` and make install idempotent (claude-kit).
7. **Stop gate:** check `stop_hook_active` and respect the 8-continuation cap ([docs](https://code.claude.com/docs/en/hooks)). An N-clean-runs quality gate is a strong ready-made hook (claude-kit).
8. **Agent adapters:** one handler that maps event and tool names for Codex and Gemini config paths (@hasna/hooks). Boost's agent registry is a model for detecting which agents a project uses.
9. **HTTP hooks:** the docs define `type: "http"`, where the event JSON is POSTed and a JSON decision comes back in a 2xx response; non-2xx is non-blocking ([docs](https://code.claude.com/docs/en/hooks)). The optional route should follow this exactly, including `allowedEnvVars` for auth headers.

## Sources

- https://code.claude.com/docs/en/hooks
- https://github.com/beyondcode/claude-hooks-sdk · https://packagist.org/packages/beyondcode/claude-hooks-sdk
- https://github.com/gerard-labs/claude-code-hooks · https://packagist.org/packages/gerard/claude-code-hooks
- https://github.com/laravel/boost
- https://github.com/sushichan044/cc-hooks-ts · https://www.npmjs.com/package/cc-hooks-ts
- https://www.npmjs.com/package/@hasna/hooks
- https://github.com/GowayLee/cchooks · https://pypi.org/project/cchooks/
- https://github.com/johnlindquist/claude-hooks
- https://github.com/mohamed-ashraf-elsaed/claude-kit · https://github.com/jorgecortesdev/laravel-claudify · https://packagist.org/packages/imageplus/claude-guardrails · https://github.com/Heyosseus/sloppy
