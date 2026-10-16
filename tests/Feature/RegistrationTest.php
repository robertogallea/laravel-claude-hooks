<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\ClaudeHooks\Attributes\ClaudeHook;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Inputs\SubagentStopInput;
use RobertoGallea\ClaudeHooks\InvalidRegistration;
use RobertoGallea\ClaudeHooks\Registrations;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\SharedContext;
use RobertoGallea\ClaudeHooks\Transport;

#[ClaudeHook(if: 'Edit(*.php)')]
#[ClaudeHook(if: 'Write(*.php)')]
class FormatsPhpFiles extends HookCommand
{
    protected $signature = 'registration:formats-php-files';

    public function hook(PostToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(matcher: 'startup', event: Event::SessionStart)]
#[ClaudeHook(event: Event::UserPromptSubmit)]
class GreetsEverySession extends SharedContext {}

#[ClaudeHook(event: Event::Stop)]
class StopsOnPostToolUse extends FormatsPhpFiles {}

#[ClaudeHook(if: 'Bash(git *)')]
class FiltersStopWithIf extends HookCommand
{
    protected $signature = 'registration:filters-stop-with-if';

    public function hook(StopInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(matcher: 'Bash')]
class MatchesStop extends FiltersStopWithIf {}

#[ClaudeHook(matcher: 'Bash', if: 'Bash(php artisan *)', timeout: 30, statusMessage: 'Checking the command', failClosed: true, transport: Transport::Http)]
class GuardsArtisanOverHttp extends HookCommand
{
    protected $signature = 'registration:guards-artisan-over-http';

    public function hook(PreToolUseInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(async: true, transport: Transport::Http)]
class RunsAsyncOverHttp extends GuardsArtisanOverHttp {}

#[ClaudeHook(asyncRewake: true, transport: Transport::Http)]
class RewakesOverHttp extends GuardsArtisanOverHttp {}

#[ClaudeHook(transport: Transport::Http)]
class ReadsClaudesDatabaseOverHttp extends GuardsArtisanOverHttp
{
    public static function httpSafe(): bool
    {
        return false;
    }
}

#[ClaudeHook(asyncRewake: true)]
class RunsTestsInTheBackground extends FiltersStopWithIf {}

#[ClaudeHook(failClosed: true)]
class FailsClosedOnStop extends FiltersStopWithIf {}

#[ClaudeHook(failClosed: true)]
class FailsClosedOnSubagentStop extends HookCommand
{
    protected $signature = 'registration:fails-closed-on-subagent-stop';

    public function hook(SubagentStopInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(event: Event::TaskCompleted, failClosed: true)]
class FailsClosedOnTaskCompleted extends HookCommand
{
    protected $signature = 'registration:fails-closed-on-task-completed';

    public function hook(GenericInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook(event: Event::TeammateIdle, failClosed: true)]
class FailsClosedOnTeammateIdle extends FailsClosedOnTaskCompleted {}

#[ClaudeHook(async: true, failClosed: true)]
class FailsClosedInTheBackground extends GuardEnvFiles {}

#[ClaudeHook(asyncRewake: true, failClosed: true)]
class FailsClosedOnRewake extends GuardEnvFiles {}

#[ClaudeHook(matcher: 'opus', event: Event::PreModelSwitch)]
class WatchesModelSwitches extends HookCommand
{
    protected $signature = 'registration:watches-model-switches';

    public function hook(GenericInput $input): ?HookResponse
    {
        return null;
    }
}

#[ClaudeHook]
class WatchesAnything extends WatchesModelSwitches {}

it('registers a Hook Command for the event its handler takes', function () {
    $this->assertRegistered(GuardEnvFiles::class, event: Event::PreToolUse);
});

it('registers a Hook Command once per Registration attribute', function () {
    $this->assertRegistered(FormatsPhpFiles::class, event: Event::PostToolUse, if: 'Edit(*.php)');
    $this->assertRegistered(FormatsPhpFiles::class, event: Event::PostToolUse, if: 'Write(*.php)');
});

it('fails the assertion when no Registration has that matcher and if filter', function () {
    $this->assertRegistered(FormatsPhpFiles::class, event: Event::PostToolUse, matcher: 'Edit', if: 'Edit(*.php)');
})->throws(AssertionFailedError::class, "FormatsPhpFiles is not registered for PostToolUse on 'Edit' if 'Edit(*.php)'");

it('registers a union handler for each event it names', function () {
    $this->assertRegistered(GreetsEverySession::class, event: Event::SessionStart, matcher: 'startup');
    $this->assertRegistered(GreetsEverySession::class, event: Event::UserPromptSubmit);
});

it('rejects a union handler without explicit events', function () {
    app(Registrations::class)->of(SharedContext::class);
})->throws(InvalidRegistration::class, SharedContext::class.'::hook() takes a union of Hook Inputs');

it('rejects an explicit event the handler does not accept', function () {
    app(Registrations::class)->of(StopsOnPostToolUse::class);
})->throws(InvalidRegistration::class, StopsOnPostToolUse::class.' is registered for Stop, but its hook() does not accept the Stop Hook Input');

it('rejects an if filter on an event that ignores it', function () {
    app(Registrations::class)->of(FiltersStopWithIf::class);
})->throws(InvalidRegistration::class, FiltersStopWithIf::class.' sets an `if` filter on Stop, which Claude Code evaluates only on PreToolUse, PostToolUse, PostToolUseFailure, PermissionRequest and PermissionDenied');

it('rejects a matcher on an event without matchers', function () {
    app(Registrations::class)->of(MatchesStop::class);
})->throws(InvalidRegistration::class, MatchesStop::class.' sets a matcher on Stop, which takes none');

it('carries every declared field into the Registration', function () {
    [$registration] = app(Registrations::class)->of(GuardsArtisanOverHttp::class);

    expect($registration)
        ->command->toBe(GuardsArtisanOverHttp::class)
        ->signature->toBe('registration:guards-artisan-over-http')
        ->event->toBe(Event::PreToolUse)
        ->matcher->toBe('Bash')
        ->if->toBe('Bash(php artisan *)')
        ->timeout->toBe(30)
        ->statusMessage->toBe('Checking the command')
        ->async->toBeFalse()
        ->asyncRewake->toBeFalse()
        ->failClosed->toBeTrue()
        ->transport->toBe(Transport::Http)
        ->httpSafe->toBeTrue();
});

it('defaults to the command Transport, failing open, in the foreground', function () {
    [$registration] = app(Registrations::class)->of(GuardEnvFiles::class);

    expect($registration)
        ->transport->toBe(Transport::Command)
        ->failClosed->toBeFalse()
        ->async->toBeFalse()
        ->timeout->toBeNull();
});

it('accepts async-rewake on the command Transport', function () {
    [$registration] = app(Registrations::class)->of(RunsTestsInTheBackground::class);

    expect($registration->asyncRewake)->toBeTrue();
});

it('rejects async on the http Transport', function (string $command, string $field) {
    expect(fn () => app(Registrations::class)->of($command))
        ->toThrow(InvalidRegistration::class, "{$command} sets {$field} on the http Transport, which runs only in the foreground");
})->with([
    'async' => [RunsAsyncOverHttp::class, 'async'],
    'async-rewake' => [RewakesOverHttp::class, 'asyncRewake'],
]);

it('rejects the http Transport for a Hook Command that is not http-safe', function () {
    app(Registrations::class)->of(ReadsClaudesDatabaseOverHttp::class);
})->throws(InvalidRegistration::class, ReadsClaudesDatabaseOverHttp::class.' is not http-safe');

it('registers a generic handler for the event it names', function () {
    $this->assertRegistered(WatchesModelSwitches::class, event: Event::PreModelSwitch, matcher: 'opus');
});

it('rejects a generic handler without an explicit event', function () {
    app(Registrations::class)->of(WatchesAnything::class);
})->throws(InvalidRegistration::class, WatchesAnything::class.'::hook() takes the generic Hook Input, so each Registration must name its event');

it('rejects fail-closed on an event where a failure already sends Claude back to work', function (string $command, string $event) {
    expect(fn () => app(Registrations::class)->of($command))
        ->toThrow(InvalidRegistration::class, "{$command} fails closed on {$event}, where Claude Code ignores onFailure: blocking would send Claude back to work on a hook it cannot repair.");
})->with([
    'Stop' => [FailsClosedOnStop::class, 'Stop'],
    'SubagentStop' => [FailsClosedOnSubagentStop::class, 'SubagentStop'],
    'TaskCompleted' => [FailsClosedOnTaskCompleted::class, 'TaskCompleted'],
    'TeammateIdle' => [FailsClosedOnTeammateIdle::class, 'TeammateIdle'],
]);

it('rejects fail-closed on a background hook', function (string $command, string $field) {
    expect(fn () => app(Registrations::class)->of($command))
        ->toThrow(InvalidRegistration::class, "{$command} fails closed with {$field}, but Claude Code ignores onFailure on background hooks: run it in the foreground or let it fail open.");
})->with([
    'async' => [FailsClosedInTheBackground::class, 'async'],
    'async-rewake' => [FailsClosedOnRewake::class, 'asyncRewake'],
]);
