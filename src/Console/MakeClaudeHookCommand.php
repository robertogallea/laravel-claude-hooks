<?php

namespace RobertoGallea\ClaudeHooks\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use LogicException;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Hook Command for one Hook Event, with its handler typed for that event's Hook Input and a
 * Registration attribute.
 */
#[AsCommand(name: 'make:claude-hook')]
class MakeClaudeHookCommand extends GeneratorCommand
{
    protected $name = 'make:claude-hook';

    protected $description = 'Create a new Hook Command';

    protected $type = 'Hook Command';

    public function handle(): bool|int|null
    {
        if ($this->event() === null) {
            $typed = array_map(fn (Event $event) => $event->value, self::typedEvents());
            $this->components->error("{$this->eventName()} is not a typed Hook Event. Choose one of: ".implode(', ', $typed).'.');

            return self::FAILURE;
        }

        $handled = parent::handle();

        if ($handled !== false && ! $this->option('no-test')) {
            $this->writeTest($this->qualifyClass($this->getNameInput()));
        }

        return $handled;
    }

    /**
     * A Pest test that runs the Hook Command with a fake Hook Input for its event and expects no opinion. An
     * existing test is kept unless forced.
     */
    private function writeTest(string $class): void
    {
        $commands = $this->rootNamespace().'Console\\Commands\\';
        $relative = str_starts_with($class, $commands) ? Str::after($class, $commands) : 'ClaudeHooks\\'.class_basename($class);
        $path = $this->laravel->basePath('tests/Feature/'.str_replace('\\', '/', $relative).'Test.php');

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn(sprintf('Test [%s] already exists, so it was kept.', $path));

            return;
        }

        $this->makeDirectory($path);
        $this->files->put($path, str_replace(
            ['{{ namespacedClass }}', '{{ class }}', '{{ input }}'],
            [$class, class_basename($class), $this->inputName()],
            $this->files->get($this->resolveStubPath('claude-hook.test.stub')),
        ));

        $this->components->info(sprintf('Test [%s] created successfully.', $path));
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath('claude-hook.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Console\Commands\ClaudeHooks';
    }

    protected function buildClass($name): string
    {
        $event = $this->event() ?? throw new LogicException('handle() rejects an untyped event before building.');

        return str_replace(
            ['{{ event }}', '{{ input }}', '{{ signature }}', '{{ registration }}'],
            [$event->value, $this->inputName(), 'claude-hooks:'.Str::kebab(class_basename($name)), $this->registration($event)],
            parent::buildClass($name),
        );
    }

    /** The Registration attribute, with a matcher placeholder only on events that Claude Code filters by matcher. */
    private function registration(Event $event): string
    {
        return $event->takesMatcher()
            ? "// Narrow the matcher to what {$event->value} filters on, as the Claude Code hooks reference lists: '*' matches all.\n#[ClaudeHook(matcher: '*')]"
            : '#[ClaudeHook]';
    }

    /** The chosen Hook Event, or null when it is unknown or has no typed Hook Input. */
    private function event(): ?Event
    {
        $event = Event::tryFrom($this->eventName());

        return $event === null || $event->inputClass() === GenericInput::class ? null : $event;
    }

    /** The short class name of the chosen event's Hook Input. */
    private function inputName(): string
    {
        return class_basename($this->event()?->inputClass() ?? GenericInput::class);
    }

    private function eventName(): string
    {
        $event = $this->option('event');

        return is_string($event) ? $event : '';
    }

    /**
     * The Hook Events with a typed Hook Input, the only ones a handler can be typed for.
     *
     * @return list<Event>
     */
    private static function typedEvents(): array
    {
        return array_values(array_filter(Event::cases(), fn (Event $event) => $event->inputClass() !== GenericInput::class));
    }

    /** A stub published to the application's `stubs` folder, or the package's own. */
    private function resolveStubPath(string $stub): string
    {
        return file_exists($custom = $this->laravel->basePath("stubs/{$stub}")) ? $custom : __DIR__."/../../stubs/{$stub}";
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['event', 'e', InputOption::VALUE_REQUIRED, 'The Hook Event the Hook Command answers', Event::PreToolUse->value],
            ['no-test', null, InputOption::VALUE_NONE, 'Do not create a Pest test for the Hook Command'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the Hook Command already exists'],
        ];
    }
}
