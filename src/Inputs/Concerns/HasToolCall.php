<?php

namespace RobertoGallea\ClaudeHooks\Inputs\Concerns;

/** Accessors and fake builders for events about one tool call. */
trait HasToolCall
{
    public function toolName(): string
    {
        return (string) $this->get('tool_name');
    }

    /** The tool input, or one of its fields by dot path. */
    public function toolInput(?string $path = null, mixed $default = null): mixed
    {
        return $path === null ? $this->get('tool_input', []) : $this->get("tool_input.{$path}", $default);
    }

    /** The file an Edit, Write or Read is about. */
    public function filePath(): ?string
    {
        return $this->toolInput('file_path');
    }

    /** The command a Bash call runs. */
    public function command(): ?string
    {
        return $this->toolInput('command');
    }

    public function toolUseId(): ?string
    {
        return $this->get('tool_use_id');
    }

    /** @return array<string, mixed> */
    protected static function fakeDefaults(): array
    {
        return ['tool_name' => 'Bash', 'tool_input' => ['command' => 'true'], 'tool_use_id' => 'toolu_fake'];
    }

    /** A fake Edit of a file. */
    public function edit(string $path, string $oldString = 'old', string $newString = 'new'): static
    {
        return $this->with(['tool_name' => 'Edit', 'tool_input' => ['file_path' => $path, 'old_string' => $oldString, 'new_string' => $newString]]);
    }

    /** A fake Write of a file. */
    public function write(string $path, string $content = ''): static
    {
        return $this->with(['tool_name' => 'Write', 'tool_input' => ['file_path' => $path, 'content' => $content]]);
    }

    /** A fake Bash call. */
    public function bash(string $command): static
    {
        return $this->with(['tool_name' => 'Bash', 'tool_input' => ['command' => $command]]);
    }
}
