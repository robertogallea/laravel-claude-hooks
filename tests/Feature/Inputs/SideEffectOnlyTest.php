<?php

use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\NotificationInput;
use RobertoGallea\ClaudeHooks\Inputs\SessionEndInput;

/** The public methods of a Hook Input class that build a Hook Response. */
function responseHelpers(string $class): array
{
    $helpers = [];
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $type = $method->getReturnType();
        $names = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($names as $named) {
            if ($named instanceof ReflectionNamedType && is_a($named->getName(), HookResponse::class, true)) {
                $helpers[] = $method->getName();
            }
        }
    }

    return $helpers;
}

it('gives Notification, SessionEnd and generic Hook Inputs no response helpers', function (string $class) {
    expect(responseHelpers($class))->toBe([]);
})->with([NotificationInput::class, SessionEndInput::class, GenericInput::class]);

it('reads a notification', function () {
    $input = seenBy(NotificationInput::fake(['message' => 'Claude needs your permission', 'title' => 'Permission needed', 'notification_type' => 'permission_prompt']));

    expect($input)->toBeInstanceOf(NotificationInput::class)
        ->and($input->message())->toBe('Claude needs your permission')
        ->and($input->title())->toBe('Permission needed')
        ->and($input->notificationType())->toBe('permission_prompt');
});

it('reads why the session ended', function () {
    $input = seenBy(SessionEndInput::fake(['reason' => 'logout']));

    expect($input)->toBeInstanceOf(SessionEndInput::class)
        ->and($input->reason())->toBe('logout')
        ->and(SessionEndInput::fake()->reason())->toBe('other');
});
