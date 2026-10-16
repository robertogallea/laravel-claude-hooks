<?php

namespace RobertoGallea\ClaudeHooks\Testing;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert;

/** The answer a Hook Command gave in a test, with assertions on what Claude Code would see. */
final class HookResult
{
    /** @var array<string, mixed> */
    public readonly array $json;

    public function __construct(public readonly string $output, public readonly int $exitCode)
    {
        Assert::assertSame(0, $exitCode, "The hook exited with {$exitCode}:\n{$output}");

        $this->json = trim($output) === '' ? [] : json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    /** The hook answered nothing, so the normal flow continues. */
    public function assertNoOpinion(): self
    {
        Assert::assertSame([], $this->json, "The hook had an opinion:\n{$this->output}");

        return $this;
    }

    /** The hook made no decision: no permission decision and no block. */
    public function assertNoDecision(): self
    {
        Assert::assertNull($this->get('hookSpecificOutput.permissionDecision'), "The hook made a decision:\n{$this->output}");
        Assert::assertNull($this->get('decision'), "The hook made a decision:\n{$this->output}");
        Assert::assertNull($this->get('hookSpecificOutput.decision'), "The hook made a decision:\n{$this->output}");

        return $this;
    }

    public function assertAllowed(?string $reason = null): self
    {
        return $this->assertPermissionDecision('allow', $reason);
    }

    public function assertAsked(?string $reason = null): self
    {
        return $this->assertPermissionDecision('ask', $reason);
    }

    public function assertDeferred(?string $reason = null): self
    {
        return $this->assertPermissionDecision('defer', $reason);
    }

    /** @param  array<string, mixed>  $input */
    public function assertUpdatedInput(array $input): self
    {
        Assert::assertSame($input, $this->get($this->isPermissionRequest() ? 'hookSpecificOutput.decision.updatedInput' : 'hookSpecificOutput.updatedInput'), "The hook did not update the input as expected:\n{$this->output}");

        return $this;
    }

    /**
     * The hook replaced what Claude sees of the tool's output with exactly this value.
     *
     * @param  array<string, mixed>|string  $output
     */
    public function assertUpdatedOutput(array|string $output): self
    {
        Assert::assertSame($output, $this->get('hookSpecificOutput.updatedToolOutput'), "The hook did not replace the tool output as expected:\n{$this->output}");

        return $this;
    }

    public function assertContextContains(string $text): self
    {
        Assert::assertStringContainsString($text, (string) $this->get('hookSpecificOutput.additionalContext'), "The hook added no such context:\n{$this->output}");

        return $this;
    }

    public function assertDenied(?string $reason = null): self
    {
        return $this->assertPermissionDecision('deny', $reason);
    }

    /** The hook blocks the action (top-level `decision: "block"`), optionally with a reason containing the given text. */
    public function assertBlocked(?string $reason = null): self
    {
        Assert::assertSame('block', $this->get('decision'), "The hook did not block:\n{$this->output}");

        return $reason === null ? $this : $this->assertField('reason', $reason, 'gave no reason');
    }

    /** The hook sets the session title, optionally to one containing the given text. */
    public function assertSessionTitle(?string $title = null): self
    {
        return $this->assertField('hookSpecificOutput.sessionTitle', $title, 'set no session title');
    }

    /** The hook supplies the first user message of the session, optionally containing the given text. */
    public function assertInitialUserMessage(?string $text = null): self
    {
        return $this->assertField('hookSpecificOutput.initialUserMessage', $text, 'supplied no initial user message');
    }

    /** The hook shows the user a warning, optionally containing the given text. */
    public function assertSystemMessage(?string $text = null): self
    {
        return $this->assertField('systemMessage', $text, 'showed no system message');
    }

    /** The hook stops Claude entirely, optionally with a reason containing the given text. */
    public function assertStoppedSession(?string $reason = null): self
    {
        Assert::assertFalse($this->get('continue') ?? true, "The hook did not stop the session:\n{$this->output}");

        return $this->assertField('stopReason', $reason, 'gave no stop reason');
    }

    /**
     * The PermissionRequest hook applies exactly these permission updates.
     *
     * @param  list<array<string, mixed>>  $permissions
     */
    public function assertUpdatedPermissions(array $permissions): self
    {
        Assert::assertSame($permissions, $this->get('hookSpecificOutput.decision.updatedPermissions'), "The hook did not update permissions as expected:\n{$this->output}");

        return $this;
    }

    /** The PermissionRequest hook denies and stops Claude. */
    public function assertInterrupted(): self
    {
        Assert::assertTrue($this->get('hookSpecificOutput.decision.interrupt') ?? false, "The hook did not interrupt Claude:\n{$this->output}");

        return $this;
    }

    /** A string field is present and, when text is given, contains it. */
    private function assertField(string $path, ?string $text, string $failure): self
    {
        $value = $this->get($path);
        Assert::assertIsString($value, "The hook {$failure}:\n{$this->output}");

        if ($text !== null) {
            Assert::assertStringContainsString($text, $value, "The hook {$failure} containing '{$text}':\n{$this->output}");
        }

        return $this;
    }

    /** A permission decision, read where its event puts it: PreToolUse's `permissionDecision`, or PermissionRequest's `decision.behavior`. */
    private function assertPermissionDecision(string $decision, ?string $reason): self
    {
        [$decisionPath, $reasonPath] = $this->isPermissionRequest()
            ? ['hookSpecificOutput.decision.behavior', 'hookSpecificOutput.decision.message']
            : ['hookSpecificOutput.permissionDecision', 'hookSpecificOutput.permissionDecisionReason'];

        Assert::assertSame($decision, $this->get($decisionPath), "The hook did not answer '{$decision}':\n{$this->output}");

        return $reason === null ? $this : $this->assertField($reasonPath, $reason, 'gave no reason');
    }

    private function isPermissionRequest(): bool
    {
        return $this->get('hookSpecificOutput.hookEventName') === 'PermissionRequest';
    }

    /** A field of the JSON response by dot path. */
    public function get(string $path): mixed
    {
        return Arr::get($this->json, $path);
    }
}
