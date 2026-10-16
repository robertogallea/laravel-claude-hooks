<?php

use Illuminate\Support\Facades\Route;
use RobertoGallea\ClaudeHooks\ClaudeHooksServiceProvider;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\BrokenHook;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\GuardEnvFiles;

/** Boot the package again in this environment with the http Transport configured, as a server would start. */
function serveHttp(bool $enabled = true, string $environment = 'local', ?string $token = 'secret-token'): void
{
    config(['claude-hooks.http.enabled' => $enabled, 'claude-hooks.http.token' => $token]);
    app()->detectEnvironment(fn () => $environment);
    app()->register(ClaudeHooksServiceProvider::class, force: true);
}

/** @return array<string, string> */
function bearer(string $token = 'secret-token'): array
{
    return ['Authorization' => "Bearer {$token}"];
}

beforeEach(function () {
    hookCommands(GuardEnvFiles::class);
});

it('answers a hook over http with the encoded Hook Response', function () {
    serveHttp();

    $this->postJson('/claude-hooks/fixture:guard-env-files', PreToolUseInput::fake()->write('.env')->payload, bearer())
        ->assertOk()
        ->assertExactJson([
            'hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'permissionDecision' => 'deny',
                'permissionDecisionReason' => '.env holds secrets: ask the user to change it.',
            ],
        ]);
});

it('refuses a request without the bearer token, or with a different one', function (array $headers) {
    serveHttp();

    $this->postJson('/claude-hooks/fixture:guard-env-files', PreToolUseInput::fake()->write('.env')->payload, $headers)
        ->assertUnauthorized();
})->with([
    'no token' => [[]],
    'wrong token' => [['Authorization' => 'Bearer guessed']],
    'not a bearer token' => [['Authorization' => 'secret-token']],
]);

it('refuses every request when no token is configured', function () {
    serveHttp(token: null);

    $this->postJson('/claude-hooks/fixture:guard-env-files', PreToolUseInput::fake()->write('.env')->payload, ['Authorization' => 'Bearer '])
        ->assertUnauthorized();
});

it('answers 200 with an empty body when the Hook Command has no opinion', function () {
    serveHttp();

    $response = $this->postJson('/claude-hooks/fixture:guard-env-files', PreToolUseInput::fake()->write('app/User.php')->payload, bearer())
        ->assertOk();

    expect($response->getContent())->toBe('');
});

it('answers 404 for a signature that is not a Hook Command', function (string $signature) {
    serveHttp();

    $this->postJson("/claude-hooks/{$signature}", PreToolUseInput::fake()->bash('ls')->payload, bearer())
        ->assertNotFound();
})->with([
    'unknown' => ['fixture:no-such-hook'],
    'not a Hook Command' => ['claude-hooks:install'],
]);

it('answers 500 when the handler throws', function () {
    serveHttp();
    hookCommands(BrokenHook::class);

    $this->postJson('/claude-hooks/fixture:broken-hook', PreToolUseInput::fake()->bash('ls')->payload, bearer())
        ->assertInternalServerError();
});

it('has no route unless enabled, and none outside the local environment', function (bool $enabled, string $environment) {
    serveHttp($enabled, $environment);

    $this->postJson('/claude-hooks/fixture:guard-env-files', PreToolUseInput::fake()->write('.env')->payload, bearer())
        ->assertNotFound();
    expect(Route::has('claude-hooks.hook'))->toBeFalse();
})->with([
    'disabled' => [false, 'local'],
    'production' => [true, 'production'],
    'testing' => [true, 'testing'],
]);

it('gives the same answer over http as over the command Transport', function (PreToolUseInput $input) {
    serveHttp();

    $overCommand = $this->runHook(GuardEnvFiles::class, $input)->output;
    $overHttp = $this->postJson('/claude-hooks/fixture:guard-env-files', $input->payload, bearer())->assertOk()->getContent();

    expect($overHttp)->toBe(rtrim($overCommand, "\n"));
})->with([
    'a decision' => [fn () => PreToolUseInput::fake()->write('.env')],
    'no opinion' => [fn () => PreToolUseInput::fake()->write('app/User.php')],
]);

it('answers with an enabled Ready-made Hook', function () {
    enableReadyMadeHook('pint', ['binary' => '/nonexistent/pint']);
    serveHttp();

    $this->postJson('/claude-hooks/claude-hooks:pint', PostToolUseInput::fake()->edit('resources/views/home.blade.php')->payload, bearer())
        ->assertOk()
        ->assertContent('');
});
