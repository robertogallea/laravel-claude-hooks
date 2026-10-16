<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;

#[ClaudeHook(matcher: 'Bash', if: 'Bash(php artisan *)', timeout: 30, statusMessage: 'Checking the command', failClosed: true)]
class InstallGuardsArtisan extends HookCommand
{
    protected $signature = 'install:guard-artisan';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(if: 'Edit(*.php)', async: true)]
#[ClaudeHook(if: 'Write(*.php)', asyncRewake: true)]
class InstallFormatsPhp extends HookCommand
{
    protected $signature = 'install:format-php';

    public function hook(PostToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(if: 'Bash(git *)')]
class InstallFiltersStopWithIf extends HookCommand
{
    protected $signature = 'install:filters-stop-with-if';

    public function hook(StopInput $input): ?HookResponse
    {
        return null;
    }
}

beforeEach(function () {
    $this->project = sys_get_temp_dir().'/claude-hooks-install-'.bin2hex(random_bytes(4));
    mkdir($this->project);
    config(['claude-hooks.project_path' => $this->project]);
    hookCommands(GuardEnvFiles::class);
});

afterEach(function () {
    File::deleteDirectory($this->project);
});

/** @param  class-string<HookCommand>  ...$commands */
function hookCommands(string ...$commands): void
{
    foreach ($commands as $command) {
        app(Kernel::class)->registerCommand(app($command));
    }
}

function settings(string $file = 'settings.json'): string
{
    return file_get_contents(test()->project."/.claude/{$file}");
}

function givenSettings(string $json, string $file = 'settings.json'): void
{
    File::ensureDirectoryExists(test()->project.'/.claude');
    file_put_contents(test()->project."/.claude/{$file}", $json);
}

it('creates the .claude folder and settings file with a Managed Entry for each Registration', function () {
    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain('+ PreToolUse fixture:guard-env-files')
        ->assertSuccessful();

    expect(file_get_contents("{$this->project}/.claude/settings.json"))->toBe(<<<'JSON'
        {
          "hooks": {
            "PreToolUse": [
              {
                "hooks": [
                  {
                    "type": "command",
                    "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"
                  }
                ]
              }
            ]
          }
        }

        JSON);
});

it('writes every Registration field the Settings Entry handler takes', function () {
    hookCommands(InstallGuardsArtisan::class, InstallFormatsPhp::class);

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'PreToolUse' => [
            ['hooks' => [['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" fixture:guard-env-files']]],
            ['matcher' => 'Bash', 'hooks' => [[
                'type' => 'command',
                'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:guard-artisan',
                'if' => 'Bash(php artisan *)',
                'timeout' => 30,
                'statusMessage' => 'Checking the command',
                'onFailure' => 'block',
            ]]],
        ],
        'PostToolUse' => [
            ['hooks' => [
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:format-php', 'if' => 'Edit(*.php)', 'async' => true],
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:format-php', 'if' => 'Write(*.php)', 'asyncRewake' => true],
            ]],
        ],
    ]);
});

it('keeps user entries, sibling handlers in the same matcher group and other settings in place', function () {
    hookCommands(InstallGuardsArtisan::class);
    givenSettings(<<<'JSON'
        {
          "permissions": {
            "allow": ["Bash(git status)"]
          },
          "hooks": {
            "PreToolUse": [
              {
                "matcher": "Bash",
                "hooks": [
                  {"type": "command", "command": "./audit.sh"},
                  {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" install:guard-artisan"}
                ]
              }
            ],
            "Stop": [
              {"hooks": [{"type": "command", "command": "notify-send done"}]}
            ]
          },
          "model": "opus"
        }
        JSON);

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain("~ PreToolUse install:guard-artisan on 'Bash' if 'Bash(php artisan *)'")
        ->expectsOutputToContain('+ PreToolUse fixture:guard-env-files')
        ->assertSuccessful();

    expect(settings())->toBe(<<<'JSON'
        {
          "permissions": {
            "allow": [
              "Bash(git status)"
            ]
          },
          "hooks": {
            "PreToolUse": [
              {
                "matcher": "Bash",
                "hooks": [
                  {
                    "type": "command",
                    "command": "./audit.sh"
                  },
                  {
                    "type": "command",
                    "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" install:guard-artisan",
                    "if": "Bash(php artisan *)",
                    "timeout": 30,
                    "statusMessage": "Checking the command",
                    "onFailure": "block"
                  }
                ]
              },
              {
                "hooks": [
                  {
                    "type": "command",
                    "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"
                  }
                ]
              }
            ],
            "Stop": [
              {
                "hooks": [
                  {
                    "type": "command",
                    "command": "notify-send done"
                  }
                ]
              }
            ]
          },
          "model": "opus"
        }

        JSON);
});

it('reports nothing to change on a second run and leaves the file byte-identical', function () {
    hookCommands(InstallGuardsArtisan::class, InstallFormatsPhp::class);
    $this->artisan('claude-hooks:install')->assertSuccessful();
    $first = settings();

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain('Nothing to change in .claude/settings.json.')
        ->doesntExpectOutputToContain('+ ')
        ->assertSuccessful();

    expect(settings())->toBe($first);
});

it('leaves a file it has nothing to change in byte-identical, whatever its formatting', function () {
    givenSettings($json = '{"hooks":{"PreToolUse":[{"hooks":[{"type":"command","command":"php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"}]}]}}');

    $this->artisan('claude-hooks:install')->expectsOutputToContain('Nothing to change')->assertSuccessful();

    expect(settings())->toBe($json);
});

it('moves a Managed Entry whose matcher changed to a matcher group of its own', function () {
    hookCommands(InstallGuardsArtisan::class);
    givenSettings(<<<'JSON'
        {"hooks": {"PreToolUse": [
          {"matcher": "Bash|Edit", "hooks": [
            {"type": "command", "command": "./audit.sh"},
            {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" install:guard-artisan", "timeout": 10}
          ]},
          {"hooks": [{"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"}]}
        ]}}
        JSON);

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain("~ PreToolUse install:guard-artisan on 'Bash'")
        ->assertSuccessful();

    expect(json_decode(settings(), true)['hooks']['PreToolUse'])->toBe([
        ['matcher' => 'Bash|Edit', 'hooks' => [['type' => 'command', 'command' => './audit.sh']]],
        ['hooks' => [['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" fixture:guard-env-files']]],
        ['matcher' => 'Bash', 'hooks' => [[
            'type' => 'command',
            'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:guard-artisan',
            'if' => 'Bash(php artisan *)',
            'timeout' => 30,
            'statusMessage' => 'Checking the command',
            'onFailure' => 'block',
        ]]],
    ]);
});

it('updates the Managed Entry whose if filter changed and keeps the one that did not', function () {
    hookCommands(InstallFormatsPhp::class);
    givenSettings(<<<'JSON'
        {"hooks": {"PostToolUse": [{"hooks": [
          {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" install:format-php", "if": "Write(*.php)", "asyncRewake": true},
          {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" install:format-php", "if": "Edit(*.blade.php)"}
        ]}]}}
        JSON);

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain("~ PostToolUse install:format-php if 'Edit(*.php)'")
        ->doesntExpectOutputToContain("install:format-php if 'Write(*.php)'")
        ->assertSuccessful();

    expect(json_decode(settings(), true)['hooks']['PostToolUse'][0]['hooks'])->toBe([
        ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:format-php', 'if' => 'Write(*.php)', 'asyncRewake' => true],
        ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" install:format-php', 'if' => 'Edit(*.php)', 'async' => true],
    ]);
});

it('keeps stale Managed Entries unless asked to prune them', function () {
    givenSettings($json = <<<'JSON'
        {"hooks": {"PreToolUse": [{"hooks": [{"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"}]}],
          "SessionStart": [{"matcher": "startup", "hooks": [{"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" app:removed-hook"}]}]}}
        JSON);

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain("? SessionStart app:removed-hook on 'startup' (stale: --prune removes it)")
        ->expectsOutputToContain('Nothing to change in .claude/settings.json.')
        ->assertSuccessful();

    expect(settings())->toBe($json);
});

it('removes stale Managed Entries with --prune, and the matcher groups and events they leave empty', function () {
    givenSettings(<<<'JSON'
        {"hooks": {
          "PreToolUse": [{"hooks": [{"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" fixture:guard-env-files"}]}],
          "SessionStart": [{"matcher": "startup", "hooks": [{"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" app:removed-hook"}]}],
          "Stop": [{"hooks": [
            {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" app:removed-hook"},
            {"type": "command", "command": "php \"$CLAUDE_PROJECT_DIR/artisan\" app:removed-hook --verbose"}
          ]}]
        }}
        JSON);

    $this->artisan('claude-hooks:install', ['--prune' => true])
        ->expectsOutputToContain("- SessionStart app:removed-hook on 'startup'")
        ->expectsOutputToContain('- Stop app:removed-hook')
        ->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'PreToolUse' => [['hooks' => [['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" fixture:guard-env-files']]]],
        'Stop' => [['hooks' => [['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" app:removed-hook --verbose']]]],
    ]);
});

it('prints what would change with --dry-run and writes nothing', function () {
    hookCommands(InstallGuardsArtisan::class);

    $this->artisan('claude-hooks:install', ['--dry-run' => true])
        ->expectsOutputToContain('+ PreToolUse fixture:guard-env-files')
        ->expectsOutputToContain("+ PreToolUse install:guard-artisan on 'Bash' if 'Bash(php artisan *)'")
        ->expectsOutputToContain('Dry run: .claude/settings.json was not written.')
        ->assertSuccessful();

    expect("{$this->project}/.claude")->not->toBeDirectory();
});

it('writes to settings.local.json with --local', function () {
    givenSettings($shared = '{"permissions": {}}');

    $this->artisan('claude-hooks:install', ['--local' => true])
        ->expectsOutputToContain('Wrote .claude/settings.local.json.')
        ->assertSuccessful();

    expect(settings())->toBe($shared)
        ->and(json_decode(settings('settings.local.json'), true)['hooks'])->toHaveKey('PreToolUse');
});

it('stops with the resolver message when a Registration is invalid', function () {
    hookCommands(InstallFiltersStopWithIf::class);

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain(InstallFiltersStopWithIf::class.' sets an `if` filter on Stop')
        ->assertFailed();

    expect("{$this->project}/.claude")->not->toBeDirectory();
});

it('refuses a settings file that is not valid JSON and leaves it alone', function () {
    givenSettings('{"hooks": ');

    $this->artisan('claude-hooks:install')
        ->expectsOutputToContain('settings.json is not valid JSON')
        ->assertFailed();

    expect(settings())->toBe('{"hooks": ');
});

it('writes two-space JSON with unescaped slashes and unicode, empty objects kept, and a trailing newline', function () {
    givenSettings('{"env": {}, "statusLine": {"type": "command", "command": "~/bin/status.sh — ✓"}}');

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(settings())->toStartWith(<<<'JSON'
        {
          "env": {},
          "statusLine": {
            "type": "command",
            "command": "~/bin/status.sh — ✓"
          },
          "hooks": {
        JSON)->toEndWith("}\n}\n");
});

it('writes an enabled Ready-made Hook with the matcher and timeout its config overrides', function () {
    enableReadyMadeHook('guard-migrations', ['matchers' => ['Edit|Write' => 'Edit|Write|NotebookEdit'], 'timeout' => 10]);

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'PreToolUse' => [
            ['matcher' => 'Edit|Write|NotebookEdit', 'hooks' => [
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:guard-migrations', 'timeout' => 10],
            ]],
            ['matcher' => 'Bash', 'hooks' => [
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:guard-migrations', 'timeout' => 10],
            ]],
        ],
    ]);
});

it('writes the Ready-made guard-artisan so that Claude Code blocks the command when the hook fails', function () {
    enableReadyMadeHook('guard-artisan');

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'PreToolUse' => [
            ['matcher' => 'Bash', 'hooks' => [[
                'type' => 'command',
                'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:guard-artisan',
                'if' => 'Bash(php artisan *)',
                'onFailure' => 'block',
            ]]],
        ],
    ]);
});

it('writes the Ready-made session-context for SessionStart without a matcher', function () {
    enableReadyMadeHook('session-context');

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'SessionStart' => [
            ['hooks' => [['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:session-context']]],
        ],
    ]);
});

it('writes the Ready-made pint after Edit and Write, with one handler for each PHP filter', function () {
    enableReadyMadeHook('pint');

    $this->artisan('claude-hooks:install')->assertSuccessful();

    expect(json_decode(settings(), true)['hooks'])->toBe([
        'PostToolUse' => [
            ['matcher' => 'Edit|Write', 'hooks' => [
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:pint', 'if' => 'Edit(*.php)'],
                ['type' => 'command', 'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" claude-hooks:pint', 'if' => 'Write(*.php)'],
            ]],
        ],
    ]);
});
