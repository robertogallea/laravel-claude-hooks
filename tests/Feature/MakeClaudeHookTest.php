<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\Inputs\NotificationInput;
use RobertoGallea\ClaudeHooks\Inputs\PermissionRequestInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolBatchInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseFailureInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreCompactInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionEndInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Inputs\SubagentStopInput;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;
use RobertoGallea\ClaudeHooks\Registrations;

afterEach(function () {
    File::deleteDirectory(app_path('Console/Commands/ClaudeHooks'));
    File::deleteDirectory(base_path('tests/Feature/ClaudeHooks'));
    File::deleteDirectory(base_path('stubs'));
});

it('generates a PreToolUse Hook Command that has no opinion', function () {
    $name = 'GuardInvoices'.Str::random(8);

    $this->artisan('make:claude-hook', ['name' => $name, '--event' => 'PreToolUse'])->assertSuccessful();

    $command = generatedClass(app_path("Console/Commands/ClaudeHooks/{$name}.php"), "App\\Console\\Commands\\ClaudeHooks\\{$name}")->name;
    $registrations = app(Registrations::class)->of($command);

    expect(is_subclass_of($command, HookCommand::class))->toBeTrue()
        ->and($registrations)->toHaveCount(1)
        ->and($registrations[0]->event)->toBe(Event::PreToolUse)
        ->and($registrations[0]->signature)->toBe('claude-hooks:'.Str::kebab($name));
    $this->runHook($command, PreToolUseInput::fake()->bash('ls'))->assertNoOpinion();
});

it('generates a Hook Command for each typed event, with a matcher placeholder only where the event takes one', function (string $event, string $input, ?string $matcher) {
    $name = 'Answers'.$event.Str::random(8);

    $this->artisan('make:claude-hook', ['name' => $name, '--event' => $event])->assertSuccessful();

    $command = generatedClass(app_path("Console/Commands/ClaudeHooks/{$name}.php"), "App\\Console\\Commands\\ClaudeHooks\\{$name}")->name;
    $registrations = app(Registrations::class)->of($command);

    expect($registrations)->toHaveCount(1)
        ->and($registrations[0]->event->value)->toBe($event)
        ->and($registrations[0]->matcher)->toBe($matcher);
    $this->runHook($command, $input::fake())->assertNoOpinion();
})->with([
    ['SessionStart', SessionStartInput::class, '*'],
    ['UserPromptSubmit', UserPromptSubmitInput::class, null],
    ['PreToolUse', PreToolUseInput::class, '*'],
    ['PermissionRequest', PermissionRequestInput::class, '*'],
    ['PostToolUse', PostToolUseInput::class, '*'],
    ['PostToolUseFailure', PostToolUseFailureInput::class, '*'],
    ['PostToolBatch', PostToolBatchInput::class, null],
    ['Notification', NotificationInput::class, '*'],
    ['SubagentStop', SubagentStopInput::class, '*'],
    ['Stop', StopInput::class, null],
    ['PreCompact', PreCompactInput::class, '*'],
    ['SessionEnd', SessionEndInput::class, '*'],
]);

it('rejects an unknown or untyped event with the list of typed events, and writes nothing', function (string $event) {
    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices', '--event' => $event])
        ->expectsOutputToContain("{$event} is not a typed Hook Event. Choose one of: SessionStart, UserPromptSubmit, PreToolUse, PermissionRequest, PostToolUse, PostToolUseFailure, PostToolBatch, Notification, SubagentStop, Stop, PreCompact, SessionEnd.")
        ->assertFailed();

    expect(app_path('Console/Commands/ClaudeHooks/GuardInvoices.php'))->not->toBeFile()
        ->and(base_path('tests/Feature/ClaudeHooks/GuardInvoicesTest.php'))->not->toBeFile();
})->with([
    'unknown' => ['PreToolUsed'],
    'untyped' => ['TaskCreated'],
]);

it('generates a Pest test that runs the Hook Command with a fake for its event and expects no opinion', function () {
    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices', '--event' => 'PostToolUse'])->assertSuccessful();

    $test = base_path('tests/Feature/ClaudeHooks/GuardInvoicesTest.php');
    lints($test);

    expect(file_get_contents($test))
        ->toContain('use App\Console\Commands\ClaudeHooks\GuardInvoices;')
        ->toContain('use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;')
        ->toContain('$this->runHook(GuardInvoices::class, PostToolUseInput::fake())->assertNoOpinion();');
});

it('generates no test with --no-test', function () {
    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices', '--no-test' => true])->assertSuccessful();

    expect(app_path('Console/Commands/ClaudeHooks/GuardInvoices.php'))->toBeFile()
        ->and(base_path('tests/Feature/ClaudeHooks/GuardInvoicesTest.php'))->not->toBeFile();
});

it('publishes its stubs, and generates from the published ones', function () {
    $this->artisan('vendor:publish', ['--tag' => 'claude-hooks-stubs'])->assertSuccessful();

    expect(base_path('stubs/claude-hook.stub'))->toBeFile()
        ->and(base_path('stubs/claude-hook.test.stub'))->toBeFile();

    file_put_contents(base_path('stubs/claude-hook.stub'), str_replace('return null;', '// Customised.'."\n".'        return null;', file_get_contents(base_path('stubs/claude-hook.stub'))));
    file_put_contents(base_path('stubs/claude-hook.test.stub'), file_get_contents(base_path('stubs/claude-hook.test.stub'))."// Customised.\n");

    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices'])->assertSuccessful();

    expect(file_get_contents(app_path('Console/Commands/ClaudeHooks/GuardInvoices.php')))->toContain('// Customised.')
        ->and(file_get_contents(base_path('tests/Feature/ClaudeHooks/GuardInvoicesTest.php')))->toContain('// Customised.');
});

it('keeps an existing test unless forced', function () {
    File::ensureDirectoryExists(base_path('tests/Feature/ClaudeHooks'));
    file_put_contents($test = base_path('tests/Feature/ClaudeHooks/GuardInvoicesTest.php'), "<?php\n// Mine.\n");

    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices'])->assertSuccessful();
    expect(file_get_contents($test))->toBe("<?php\n// Mine.\n");

    $this->artisan('make:claude-hook', ['name' => 'GuardInvoices', '--force' => true])->assertSuccessful();
    expect(file_get_contents($test))->toContain('assertNoOpinion()');
});
