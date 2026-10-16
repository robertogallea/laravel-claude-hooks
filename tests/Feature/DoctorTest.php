<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;
use RobertoGallea\ClaudeHooks\Transport;

#[ClaudeHook(matcher: 'Bash', timeout: 30)]
class DoctorGuardsBash extends HookCommand
{
    protected $signature = 'doctor:guard-bash';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(if: 'Bash(git *)')]
#[ClaudeHook(matcher: 'Bash')]
#[ClaudeHook]
class DoctorFiltersStopTwice extends HookCommand
{
    protected $signature = 'doctor:filters-stop-twice';

    public function hook(StopInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(failClosed: true)]
class DoctorFailsClosedOnStop extends HookCommand
{
    protected $signature = 'doctor:fails-closed-on-stop';

    public function hook(StopInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(matcher: 'Bash', transport: Transport::Http)]
class DoctorGuardsOverHttp extends HookCommand
{
    protected $signature = 'doctor:guards-over-http';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(matcher: 'Bash', failClosed: true, transport: Transport::Http)]
class DoctorGuardsOverHttpFailingClosed extends DoctorGuardsOverHttp
{
    protected $signature = 'doctor:guards-over-http-failing-closed';
}

#[ClaudeHook(matcher: 'Edit', transport: Transport::Http)]
class DoctorFormatsOverHttp extends HookCommand
{
    protected $signature = 'doctor:formats-over-http';

    public function hook(PostToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

beforeEach(function () {
    $this->project = sys_get_temp_dir().'/claude-hooks-doctor-'.bin2hex(random_bytes(4));
    mkdir($this->project);
    config(['claude-hooks.project_path' => $this->project]);
    hookCommands(GuardEnvFiles::class);
});

afterEach(function () {
    File::deleteDirectory($this->project);
});

it('prints a short OK line and succeeds when Claude Code calls every Hook Command as declared', function () {
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('OK: .claude/settings.json calls every Hook Command as declared.')
        ->assertSuccessful();
});

it('reports a Hook Command missing from settings and fails', function () {
    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('Missing from .claude/settings.json: PreToolUse fixture:guard-env-files')
        ->expectsOutputToContain('Run php artisan claude-hooks:install')
        ->doesntExpectOutputToContain('OK:')
        ->assertFailed();
});

it('reports a Managed Entry its Registration has changed since install and fails', function () {
    hookCommands(DoctorGuardsBash::class);
    $this->artisan('claude-hooks:install')->assertSuccessful();
    givenSettings(str_replace('"timeout": 30', '"timeout": 10', settings()));

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain("Outdated in .claude/settings.json: PreToolUse doctor:guard-bash on 'Bash'")
        ->assertFailed();
});

it('reports a stale Managed Entry and fails', function () {
    $this->artisan('claude-hooks:install')->assertSuccessful();
    givenSettings(str_replace('fixture:guard-env-files', 'fixture:removed-hook', settings()));

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('Missing from .claude/settings.json: PreToolUse fixture:guard-env-files')
        ->expectsOutputToContain('Stale in .claude/settings.json: PreToolUse fixture:removed-hook')
        ->expectsOutputToContain('Run php artisan claude-hooks:install --prune')
        ->assertFailed();
});

it('checks settings.local.json with --local, as install writes it', function () {
    $this->artisan('claude-hooks:install', ['--local' => true])->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('Missing from .claude/settings.json: PreToolUse fixture:guard-env-files')
        ->assertFailed();
    $this->artisan('claude-hooks:doctor', ['--local' => true])
        ->expectsOutputToContain('OK: .claude/settings.local.json calls every Hook Command as declared.')
        ->assertSuccessful();
});

it('suggests install with --local when settings.local.json needs it', function () {
    $this->artisan('claude-hooks:doctor', ['--local' => true])
        ->expectsOutputToContain('Run php artisan claude-hooks:install --local')
        ->assertFailed();
});

it('reports a settings file that is not valid JSON and fails', function () {
    givenSettings('{"hooks": ');

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('settings.json is not valid JSON')
        ->assertFailed();
});

it('reports every invalid Registration, not just the first, and still checks the valid ones', function () {
    hookCommands(DoctorFiltersStopTwice::class, DoctorFailsClosedOnStop::class);

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('DoctorFiltersStopTwice sets an `if` filter on Stop')
        ->expectsOutputToContain('DoctorFiltersStopTwice sets a matcher on Stop, which takes none')
        ->expectsOutputToContain('DoctorFailsClosedOnStop fails closed on Stop')
        ->expectsOutputToContain('Missing from .claude/settings.json: PreToolUse fixture:guard-env-files')
        ->expectsOutputToContain('Missing from .claude/settings.json: Stop doctor:filters-stop-twice')
        ->assertFailed();
});

it('warns about an http guard that fails open, and still succeeds', function () {
    $this->artisan('claude-hooks:install')->assertSuccessful();
    hookCommands(DoctorGuardsOverHttp::class);

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain("PreToolUse doctor:guards-over-http on 'Bash' uses the http Transport without failClosed")
        ->doesntExpectOutputToContain('OK:')
        ->assertSuccessful();
});

it('does not warn about an http guard that fails closed, nor about an http hook that guards nothing', function () {
    $this->artisan('claude-hooks:install')->assertSuccessful();
    hookCommands(DoctorGuardsOverHttpFailingClosed::class, DoctorFormatsOverHttp::class);

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('OK: .claude/settings.json calls every Hook Command as declared.')
        ->assertSuccessful();
});

/** An executable file under the project, as a Composer binary would be. */
function givenBinary(string $relative): void
{
    File::ensureDirectoryExists(dirname(test()->project."/{$relative}"));
    file_put_contents(test()->project."/{$relative}", "#!/bin/sh\n");
    chmod(test()->project."/{$relative}", 0755);
}

it('reports an enabled Ready-made Hook whose binary is missing and fails', function (string $hook, string $binary) {
    enableReadyMadeHook($hook);
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain("claude-hooks:{$hook} needs {$this->project}/{$binary}, which is missing or not executable.")
        ->assertFailed();
})->with([
    'pint' => ['pint', 'vendor/bin/pint'],
    'pest' => ['pest', 'vendor/bin/pest'],
]);

it('finds the binaries of enabled Ready-made Hooks under the project path', function () {
    enableReadyMadeHook('pint');
    enableReadyMadeHook('pest');
    givenBinary('vendor/bin/pint');
    givenBinary('vendor/bin/pest');
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('OK: .claude/settings.json calls every Hook Command as declared.')
        ->assertSuccessful();
});

it('checks the configured Pint binary', function () {
    enableReadyMadeHook('pint', ['binary' => '/nonexistent/pint']);
    givenBinary('vendor/bin/pint');
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('claude-hooks:pint needs /nonexistent/pint, which is missing or not executable.')
        ->assertFailed();
});

it('checks the first word of the Pest command only when it is a path', function () {
    enableReadyMadeHook('pest', ['command' => 'php artisan test --compact']);
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->expectsOutputToContain('OK: .claude/settings.json calls every Hook Command as declared.')
        ->assertSuccessful();
});

it('does not check the binaries of Ready-made Hooks that are not enabled', function () {
    $this->artisan('claude-hooks:install')->assertSuccessful();

    $this->artisan('claude-hooks:doctor')
        ->doesntExpectOutputToContain('vendor/bin/pint')
        ->assertSuccessful();
});
