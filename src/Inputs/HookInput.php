<?php

namespace RobertoGallea\ClaudeHooks\Inputs;

use Illuminate\Support\Arr;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookResponse;

/** The typed form of the payload Claude Code sends for one Hook Event. */
abstract class HookInput
{
    /**
     * @param  array<string, mixed>  $payload  the raw payload, as Claude Code sent it
     */
    final public function __construct(public readonly array $payload) {}

    /** The Hook Event this Hook Input is for. */
    abstract public static function event(): Event;

    /**
     * A Hook Input with realistic common fields, for tests.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fake(array $overrides = []): static
    {
        return new static(array_replace_recursive([
            'session_id' => 'fake-session',
            'transcript_path' => sys_get_temp_dir().'/fake-transcript.jsonl',
            'cwd' => base_path(),
            'permission_mode' => 'default',
            'hook_event_name' => static::event()->value,
        ], static::fakeDefaults(), $overrides));
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return [];
    }

    /** A payload field by dot path. */
    public function get(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->payload, $path, $default);
    }

    public function sessionId(): string
    {
        return (string) $this->get('session_id');
    }

    public function transcriptPath(): ?string
    {
        return $this->get('transcript_path');
    }

    public function cwd(): ?string
    {
        return $this->get('cwd');
    }

    public function permissionMode(): ?string
    {
        return $this->get('permission_mode');
    }

    /**
     * A copy with the given payload fields replaced.
     *
     * @param  array<string, mixed>  $fields
     */
    public function with(array $fields): static
    {
        return new static(array_replace($this->payload, $fields));
    }

    protected function respond(): HookResponse
    {
        return HookResponse::for(static::event());
    }
}
