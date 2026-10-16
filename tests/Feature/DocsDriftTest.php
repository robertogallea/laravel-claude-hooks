<?php

use Symfony\Component\Process\Process;

/**
 * The docs-drift check: the Hook Events documented in the hooks reference, compared with the Hook Event enum.
 * The fixture is a trimmed copy of https://code.claude.com/docs/en/hooks.md, read on 2026-10-16.
 */
function checkHookEvents(string $source): Process
{
    $process = new Process([PHP_BINARY, 'bin/check-hook-events', $source], dirname(__DIR__, 2));
    $process->run();

    return $process;
}

it('finds every documented Hook Event and reports a match with the enum', function () {
    $process = checkHookEvents('tests/Fixtures/docs/hooks.md');

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toBe("The hooks reference documents the same 33 Hook Events as the enum.\n");
});

it('reports the added and removed Hook Events and exits 1 when the reference has drifted', function () {
    $page = str_replace('### Setup', '### SessionPause', file_get_contents(__DIR__.'/../Fixtures/docs/hooks.md'));
    $file = tempnam(sys_get_temp_dir(), 'hooks-reference-');
    file_put_contents($file, $page);

    $process = checkHookEvents($file);
    unlink($file);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toBe(<<<'TEXT'
            The hooks reference and the Hook Event enum differ.
            Added in the reference: SessionPause
            Removed from the reference: Setup

            TEXT);
});
