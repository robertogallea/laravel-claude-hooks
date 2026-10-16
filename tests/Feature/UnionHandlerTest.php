<?php

use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\PostToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\PreToolUseInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionStartInput;
use RobertoGallea\ClaudeHooks\Inputs\StopInput;
use RobertoGallea\ClaudeHooks\Inputs\UserPromptSubmitInput;
use RobertoGallea\ClaudeHooks\Tests\Fixtures\SharedContext;

it('gives a union-typed handler the Hook Input of the event that fired', function () {
    $this->runHook(SharedContext::class, SessionStartInput::fake())
        ->assertContextContains(SessionStartInput::class.' for SessionStart');

    $this->runHook(SharedContext::class, UserPromptSubmitInput::fake())
        ->assertContextContains(UserPromptSubmitInput::class.' for UserPromptSubmit');
});

it('refuses an event outside the union', function () {
    $this->runHook(SharedContext::class, StopInput::fake());
})->throws(InvalidArgumentException::class, "does not handle the 'Stop' event");

it('decodes each typed event to its own Hook Input', function () {
    expect(seenBy(PreToolUseInput::fake()))->toBeInstanceOf(PreToolUseInput::class)
        ->and(seenBy(PostToolUseInput::fake()))->toBeInstanceOf(PostToolUseInput::class)
        ->and(seenBy(GenericInput::fakeFor('PreModelSwitch')))->toBeInstanceOf(GenericInput::class);
});

it('refuses to combine Hook Responses of different events', function () {
    answer(StopInput::fake(), fn (StopInput $input) => $input->block('Run the tests.')->and(PostToolUseInput::fake()->addContext('x')));
})->throws(LogicException::class, 'cannot be combined');
