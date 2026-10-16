<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Hooks\Pest;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;

beforeEach(function () {
    enableReadyMadeHook('pest');
});

it('is registered for Stop, with no matcher', function () {
    $this->assertRegistered(Pest::class, Event::Stop);
});

it('has no opinion and does not run the tests when git reports no changed PHP files', function () {
    Process::fake([
        'git status*' => Process::result(output: ''),
        '*' => Process::result(output: 'FAILED', exitCode: 1),
    ]);

    $this->runHook(Pest::class, StopInput::fake())->assertNoOpinion();

    Process::assertRan(fn (PendingProcess $process) => $process->command === "git status --porcelain -- '*.php'" && $process->path === base_path());
    Process::assertRanTimes(fn () => true, 1);
});

it('runs the test suite from the project path when PHP files changed, with no opinion when it passes', function () {
    Process::fake([
        'git status*' => Process::result(output: " M app/Money.php\n"),
        '*' => Process::result(output: 'Tests:    12 passed (30 assertions)'),
    ]);

    $this->runHook(Pest::class, StopInput::fake())->assertNoOpinion();

    Process::assertRan(fn (PendingProcess $process) => $process->command === 'vendor/bin/pest --compact' && $process->path === base_path());
    Process::assertRanTimes(fn () => true, 2);
});

it('runs the configured test command', function () {
    enableReadyMadeHook('pest', ['command' => 'php artisan test --parallel']);
    Process::fake(['git status*' => Process::result(output: " M app/Money.php\n"), '*' => Process::result()]);

    $this->runHook(Pest::class, StopInput::fake())->assertNoOpinion();

    Process::assertRan(fn (PendingProcess $process) => $process->command === 'php artisan test --parallel');
});

it('sends Claude back once with the last 40 lines of the output when the suite fails', function () {
    $output = implode("\n", array_map(fn (int $line) => "line {$line}", range(1, 50)))."\n";
    Process::fake(['git status*' => Process::result(output: " M app/Money.php\n"), '*' => Process::result(output: $output, exitCode: 1)]);

    expect($this->runHook(Pest::class, StopInput::fake(['stop_hook_active' => false]))->json)->toBe([
        'decision' => 'block',
        'reason' => "The test suite is failing. Fix it before you finish:\n"
            ."line 11\nline 12\nline 13\nline 14\nline 15\nline 16\nline 17\nline 18\nline 19\nline 20\n"
            ."line 21\nline 22\nline 23\nline 24\nline 25\nline 26\nline 27\nline 28\nline 29\nline 30\n"
            ."line 31\nline 32\nline 33\nline 34\nline 35\nline 36\nline 37\nline 38\nline 39\nline 40\n"
            ."line 41\nline 42\nline 43\nline 44\nline 45\nline 46\nline 47\nline 48\nline 49\nline 50",
    ]);
});

it('sends back as many lines of the output as configured', function () {
    enableReadyMadeHook('pest', ['tail' => 2]);
    Process::fake(['git status*' => Process::result(output: " M app/Money.php\n"), '*' => Process::result(output: "PASS  Tests\\Unit\\TaxTest\nFAILED  Tests\\Unit\\MoneyTest > it formats euros\nTests:    1 failed, 11 passed (30 assertions)\n", exitCode: 1)]);

    expect($this->runHook(Pest::class, StopInput::fake())->json)->toBe([
        'decision' => 'block',
        'reason' => "The test suite is failing. Fix it before you finish:\nFAILED  Tests\\Unit\\MoneyTest > it formats euros\nTests:    1 failed, 11 passed (30 assertions)",
    ]);
});

it('lets Claude stop and warns the user when the suite still fails after Claude was sent back', function () {
    Process::fake(['git status*' => Process::result(output: " M app/Money.php\n"), '*' => Process::result(output: 'Tests:    1 failed', exitCode: 1)]);

    expect($this->runHook(Pest::class, StopInput::fake(['stop_hook_active' => true]))->json)->toBe([
        'systemMessage' => 'The test suite is still failing.',
    ]);
});

it('runs the tests anyway when git cannot tell what changed', function (int $exitCode, string $error) {
    Process::fake([
        'git status*' => Process::result(errorOutput: $error, exitCode: $exitCode),
        '*' => Process::result(output: 'Tests:    1 failed', exitCode: 1),
    ]);

    $this->runHook(Pest::class, StopInput::fake())->assertBlocked();

    Process::assertRan(fn (PendingProcess $process) => $process->command === 'vendor/bin/pest --compact');
})->with([
    'not a repository' => [128, 'fatal: not a git repository (or any of the parent directories): .git'],
    'git not installed' => [127, 'sh: 1: git: not found'],
]);

it('is http-safe, since it runs the suite on disk whichever environment runs it', function () {
    expect(Pest::httpSafe())->toBeTrue();
});

it('is registered as an Artisan command once enabled', function () {
    expect(Artisan::all())->toHaveKey('claude-hooks:pest');
});
