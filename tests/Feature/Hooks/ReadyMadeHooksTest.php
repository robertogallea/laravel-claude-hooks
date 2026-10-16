<?php

use Illuminate\Support\Facades\Artisan;
use RobertoGallea\ClaudeHooks\Hooks\GuardMigrations;
use RobertoGallea\ClaudeHooks\Registrations;

it('registers no Ready-made Hook until it is enabled', function () {
    expect(app(Registrations::class)->commands())->toBe([])
        ->and(Artisan::all())->not->toHaveKey('claude-hooks:guard-migrations')
        ->and(Artisan::all())->not->toHaveKey('claude-hooks:guard-artisan');
});

it('registers an enabled Ready-made Hook as an Artisan command', function () {
    enableReadyMadeHook('guard-migrations');

    expect(app(Registrations::class)->commands())->toBe([GuardMigrations::class])
        ->and(Artisan::all())->toHaveKey('claude-hooks:guard-migrations');
});
