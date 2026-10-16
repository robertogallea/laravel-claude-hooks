<?php

use Illuminate\Contracts\Console\Kernel;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Registration;
use RobertoGallea\ClaudeHooks\Registrations;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\SharedContext;

beforeEach(function () {
    $this->app->make(Kernel::class)->registerCommand($this->app->make(GuardEnvFiles::class));
});

it('finds every registered Artisan command that is a Hook Command', function () {
    $commands = app(Registrations::class)->commands();

    expect($commands)->toBe([GuardEnvFiles::class])
        ->not->toContain(SharedContext::class);
});

it('derives the Registrations of every discovered Hook Command', function () {
    $registrations = array_map(
        fn (Registration $registration) => [$registration->signature, $registration->event],
        app(Registrations::class)->all(),
    );

    expect($registrations)->toBe([['fixture:guard-env-files', Event::PreToolUse]]);
});
