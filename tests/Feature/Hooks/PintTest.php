<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Hooks\Pint;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;

beforeEach(function () {
    enableReadyMadeHook('pint');
});

it('is registered after every Edit and every Write of a PHP file', function () {
    $this->assertRegistered(Pint::class, Event::PostToolUse, matcher: 'Edit|Write', if: 'Edit(*.php)');
    $this->assertRegistered(Pint::class, Event::PostToolUse, matcher: 'Edit|Write', if: 'Write(*.php)');
});

it('runs Pint quietly on the file Claude edited, with no opinion when it succeeds', function () {
    Process::fake();

    $this->runHook(Pint::class, PostToolUseInput::fake()->edit('/app/src/Invoice.php'))->assertNoOpinion();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [base_path('vendor/bin/pint'), '--quiet', '/app/src/Invoice.php']);
    Process::assertRanTimes(fn () => true, 1);
});

it('runs the configured Pint binary', function () {
    enableReadyMadeHook('pint', ['binary' => '/usr/local/bin/pint']);
    Process::fake();

    $this->runHook(Pint::class, PostToolUseInput::fake()->write('/app/src/Invoice.php'))->assertNoOpinion();

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['/usr/local/bin/pint', '--quiet', '/app/src/Invoice.php']);
});

it('tells Claude, without blocking, when Pint fails', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Parse error: syntax error, unexpected end of file', exitCode: 1)]);

    expect($this->runHook(Pint::class, PostToolUseInput::fake()->edit('/app/src/Invoice.php'))->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolUse',
            'additionalContext' => "Pint could not format /app/src/Invoice.php (exit code 1):\nParse error: syntax error, unexpected end of file",
        ],
    ]);
});

it('says only that Pint failed when Pint printed nothing', function () {
    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->runHook(Pint::class, PostToolUseInput::fake()->edit('/app/src/Invoice.php'))
        ->assertContextContains('Pint could not format /app/src/Invoice.php (exit code 1).');
});

it('has no opinion on a file that is not PHP, Blade templates included, and does not run Pint', function (string $path) {
    Process::fake();

    $this->runHook(Pint::class, PostToolUseInput::fake()->edit($path))->assertNoOpinion();

    Process::assertNothingRan();
})->with(['/app/README.md', '/app/resources/views/welcome.blade.php', '/app/src/Invoice.php.bak', '/app/composer.json']);

it('is http-safe, since it formats a file on disk whichever environment runs it', function () {
    expect(Pint::httpSafe())->toBeTrue();
});

it('is registered as an Artisan command once enabled', function () {
    expect(Artisan::all())->toHaveKey('claude-hooks:pint');
});
