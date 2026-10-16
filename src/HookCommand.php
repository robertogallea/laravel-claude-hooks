<?php

namespace RobertoGallea\ClaudeHooks;

use Illuminate\Console\Command;
use JsonException;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;
use RobertoGallea\ClaudeHooks\Wire\ClaudeCode;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An Artisan command that answers Claude Code hooks. Subclasses declare a public `hook()` method
 * whose first parameter is the Hook Input it handles, and return a Hook Response or null for no opinion.
 */
abstract class HookCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->addOption('payload', null, InputOption::VALUE_REQUIRED, 'Read the hook payload from this file ("-" for stdin)');
        $this->addOption('pretty', null, InputOption::VALUE_NONE, 'Indent the JSON response');
    }

    final public function handle(ClaudeCode $wire): int
    {
        $payload = $this->payload();

        if ($payload === null) {
            $this->components->error('This command answers a Claude Code hook and reads its JSON payload from stdin.');
            $this->line('  Pipe a payload in, or replay one with --payload=path/to/payload.json');

            return self::FAILURE;
        }

        $response = $this->answer($wire->decode($payload, static::inputClasses()));

        if ($response !== null) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($this->option('pretty') ? JSON_PRETTY_PRINT : 0);
            $this->output->writeln(json_encode($wire->encode($response), $flags), OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }

    /**
     * The Hook Input classes this command's `hook()` method accepts.
     *
     * @return list<class-string<HookInput>>
     */
    public static function inputClasses(): array
    {
        $type = self::hookMethod()->getParameters()[0]?->getType();
        $types = match (true) {
            $type instanceof ReflectionNamedType => [$type],
            $type instanceof ReflectionUnionType => $type->getTypes(),
            default => [],
        };

        $classes = [];
        foreach ($types as $named) {
            if (! $named instanceof ReflectionNamedType || ! is_subclass_of($named->getName(), HookInput::class)) {
                throw new LogicException(static::class.'::hook() must take a Hook Input as its first parameter.');
            }
            $classes[] = $named->getName();
        }

        return $classes ?: throw new LogicException(static::class.'::hook() must take a Hook Input as its first parameter.');
    }

    private static function hookMethod(): ReflectionMethod
    {
        if (! method_exists(static::class, 'hook')) {
            throw new LogicException(static::class.' must declare a public hook() method.');
        }

        return new ReflectionMethod(static::class, 'hook');
    }

    private function answer(HookInput $input): ?HookResponse
    {
        $parameter = self::hookMethod()->getParameters()[0]->getName();

        return $this->laravel->call([$this, 'hook'], [$parameter => $input]);
    }

    /**
     * The decoded payload, or null when there is nothing to read but a terminal.
     *
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    private function payload(): ?array
    {
        $source = $this->option('payload');

        if ($source === null && stream_isatty(STDIN)) {
            return null;
        }

        $json = $source === null || $source === '-'
            ? stream_get_contents(STDIN)
            : file_get_contents($source);

        return json_decode(trim((string) $json) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    }
}
