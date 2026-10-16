<?php

use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;

it('adds context and names the session in one Hook Response', function () {
    $result = answer(
        SessionStartInput::fake(),
        fn (SessionStartInput $input) => $input->addContext("Current branch: feat/auth-refactor\nUncommitted changes: src/auth.ts, src/login.tsx\nActive issue: #4211 Migrate to OAuth2")
            ->and($input->setSessionTitle('auth-refactor')),
    )->assertContextContains('feat/auth-refactor')->assertSessionTitle('auth-refactor');

    expect($result->json)->toBe([
        'hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => "Current branch: feat/auth-refactor\nUncommitted changes: src/auth.ts, src/login.tsx\nActive issue: #4211 Migrate to OAuth2",
            'sessionTitle' => 'auth-refactor',
        ],
    ]);
});

it('supplies the first user message of the session', function () {
    $result = answer(SessionStartInput::fake(), fn (SessionStartInput $input) => $input->setInitialUserMessage('Run the test suite and report.'))
        ->assertInitialUserMessage('test suite')
        ->assertInitialUserMessage();

    expect($result->json)->toBe([
        'hookSpecificOutput' => ['hookEventName' => 'SessionStart', 'initialUserMessage' => 'Run the test suite and report.'],
    ]);
});

it('reports what resuming costs in a system message', function () {
    answer(SessionStartInput::fake(['source' => 'resume', 'estimated_cache_write_usd' => 1.1396]), fn (SessionStartInput $input) => $input->systemMessage('Resuming re-sends 182k tokens.'))
        ->assertSystemMessage('182k');
});

it('reads how the session started', function () {
    $input = seenBy(SessionStartInput::fake([
        'source' => 'resume',
        'model' => 'claude-opus-5',
        'session_title' => 'auth-refactor',
        'seconds_since_last_response' => 5400,
        'context_tokens' => 182340,
        'prompt_cache_likely_expired' => true,
        'estimated_cache_write_usd' => 1.1396,
    ]));

    expect($input)->toBeInstanceOf(SessionStartInput::class)
        ->and($input->source())->toBe('resume')
        ->and($input->model())->toBe('claude-opus-5')
        ->and($input->sessionTitle())->toBe('auth-refactor')
        ->and($input->get('context_tokens'))->toBe(182340);
});

it('fakes a new session', function () {
    $input = seenBy(SessionStartInput::fake());

    expect($input->source())->toBe('startup')
        ->and($input->sessionTitle())->toBeNull();
});
