# Registration lives on the Hook Command, and install owns only Managed Entries

A Hook Command declares its Registration (event, matcher, `if`, timeout, failure stance) with a PHP attribute, so the rule and its trigger live in one file; config is used only to enable Ready-made Hooks and override their Registration. `claude-hooks:install` merges into `.claude/settings.json` (or `settings.local.json` with `--local`): it recognises a Managed Entry by its handler (the `php "$CLAUDE_PROJECT_DIR/artisan" <signature>` command, or the package's `http` URL), adds and updates those, never touches any other entry, and removes stale Managed Entries only with `--prune`.

## Considered Options

- **A config file as the source of truth**: rejected, it separates the rule from its trigger.
- **A bundled Claude Code plugin (`hooks/hooks.json`)**: rejected for v1. A plugin is static, so it cannot list the application's own Hook Commands; it needs a marketplace step and is invisible in the repository diff.
