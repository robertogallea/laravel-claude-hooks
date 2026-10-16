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

    /** The hook made no permission decision. */
    public function assertNoDecision(): self
    {
        Assert::assertNull($this->get('hookSpecificOutput.permissionDecision'), "The hook made a decision:\n{$this->output}");

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
        Assert::assertSame($input, $this->get('hookSpecificOutput.updatedInput'), "The hook did not update the input as expected:\n{$this->output}");

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

    private function assertPermissionDecision(string $decision, ?string $reason): self
    {
        Assert::assertSame($decision, $this->get('hookSpecificOutput.permissionDecision'), "The hook did not answer '{$decision}':\n{$this->output}");

        if ($reason !== null) {
            Assert::assertStringContainsString($reason, (string) $this->get('hookSpecificOutput.permissionDecisionReason'));
        }

        return $this;
    }

    /** A field of the JSON response by dot path. */
    public function get(string $path): mixed
    {
        return Arr::get($this->json, $path);
    }
}
