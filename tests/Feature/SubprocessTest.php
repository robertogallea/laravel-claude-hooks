<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Smoke tests: real JSON piped into a real Artisan process, as Claude Code runs a Hook Command.
 * Fixture Hook Commands are registered for the Testbench binary through testbench.yaml.
 */
function artisan(array $arguments, ?string $stdin = null): Process
{
    $process = new Process([PHP_BINARY, 'vendor/bin/testbench', ...$arguments], dirname(__DIR__, 2));
    $process->setInput($stdin);
    $process->run();

    return $process;
}

/** A PreToolUse payload shaped like the hooks reference example. */
function editPayload(string $filePath): string
{
    return json_encode([
        'session_id' => 'abc123',
        'transcript_path' => '/home/user/.claude/projects/my-project/00893aaf.jsonl',
        'cwd' => '/home/user/my-project',
        'permission_mode' => 'default',
        'hook_event_name' => 'PreToolUse',
        'tool_name' => 'Edit',
        'tool_input' => ['file_path' => $filePath, 'old_string' => 'a', 'new_string' => 'b'],
        'tool_use_id' => 'toolu_01ABC123',
    ], JSON_THROW_ON_ERROR);
}

it('reads the payload from stdin and answers on stdout with exit code 0', function () {
    $process = artisan(['fixture:guard-env-files'], editPayload('/home/user/my-project/.env'));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":".env holds secrets: ask the user to change it."}}'."\n");
});

it('replays a payload from a file', function () {
    $file = tempnam(sys_get_temp_dir(), 'payload-');
    file_put_contents($file, editPayload('/home/user/my-project/.env'));

    $process = artisan(['fixture:guard-env-files', "--payload={$file}"]);
    unlink($file);

    expect($process->getExitCode())->toBe(0)
        ->and(json_decode($process->getOutput(), true))->toBe([
            'hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
                'permissionDecision' => 'deny',
                'permissionDecisionReason' => '.env holds secrets: ask the user to change it.',
            ],
        ]);
});

it('reads stdin when the payload option is a dash', function () {
    $process = artisan(['fixture:guard-env-files', '--payload=-'], editPayload('/home/user/my-project/.env'));

    expect($process->getExitCode())->toBe(0)
        ->and(json_decode($process->getOutput(), true)['hookSpecificOutput']['permissionDecision'])->toBe('deny');
});

it('indents the response with --pretty', function () {
    $process = artisan(['fixture:guard-env-files', '--pretty'], editPayload('/home/user/my-project/.env'));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe(<<<'JSON'
            {
                "hookSpecificOutput": {
                    "hookEventName": "PreToolUse",
                    "permissionDecision": "deny",
                    "permissionDecisionReason": ".env holds secrets: ask the user to change it."
                }
            }

            JSON);
});

it('writes nothing when the hook has no opinion', function () {
    $process = artisan(['fixture:guard-env-files'], editPayload('/home/user/my-project/app/User.php'));

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('');
});

it('fails fast with a hint when run in a terminal without a payload', function () {
    $script = (new ExecutableFinder)->find('script');

    if ($script === null) {
        $this->markTestSkipped('`script` is needed to give the hook a terminal.');
    }

    $process = new Process([$script, '-qec', escapeshellarg(PHP_BINARY).' vendor/bin/testbench fixture:guard-env-files', '/dev/null'], dirname(__DIR__, 2), timeout: 20);
    $process->run();

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getOutput())->toContain('reads its JSON payload from stdin')
        ->toContain('--payload=');
});

it('lets an exception escape, so Claude Code treats it as a non-blocking error', function () {
    $process = artisan(['fixture:broken-hook'], editPayload('/home/user/my-project/.env'));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('The database is down.');
});

it('gives a payload without an event name to a hook that handles one event', function () {
    $payload = json_decode(editPayload('/home/user/my-project/.env'), true);
    unset($payload['hook_event_name']);

    $process = artisan(['fixture:guard-env-files'], json_encode($payload));

    expect($process->getExitCode())->toBe(0)
        ->and(json_decode($process->getOutput(), true)['hookSpecificOutput']['permissionDecision'])->toBe('deny');
});

it('fails clearly on a payload for an event the hook does not handle', function () {
    $process = artisan(['fixture:guard-env-files'], json_encode([
        'session_id' => 'abc123',
        'transcript_path' => '/home/user/.claude/projects/my-project/00893aaf.jsonl',
        'cwd' => '/home/user/my-project',
        'hook_event_name' => 'Stop',
        'stop_hook_active' => false,
    ]));

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput().$process->getErrorOutput())->toContain("does not handle the 'Stop' event");
});

it('fails clearly on a payload that is not JSON', function () {
    $process = artisan(['fixture:guard-env-files'], '{"hook_event_name": "PreToolUse",');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('The hook payload is not valid JSON');
});
