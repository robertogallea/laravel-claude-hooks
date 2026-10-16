<?php

namespace RobertoGallea\ClaudeHooks\Console;

use Illuminate\Console\Command;
use RobertoGallea\ClaudeHooks\Hooks\ReadyMadeHook;
use RobertoGallea\ClaudeHooks\ManagedEntry;
use RobertoGallea\ClaudeHooks\Registration;
use RobertoGallea\ClaudeHooks\Registrations;
use RobertoGallea\ClaudeHooks\Settings\SettingsFile;
use RobertoGallea\ClaudeHooks\Settings\SettingsMerge;
use RobertoGallea\ClaudeHooks\Transport;
use RuntimeException;

/**
 * Tells whether Claude Code will call every Hook Command as its Registrations declare. Error findings make it
 * fail, so that it can run in CI; warnings alone do not.
 */
class DoctorCommand extends Command
{
    protected $signature = 'claude-hooks:doctor
        {--local : Check .claude/settings.local.json, which stays out of the repository}';

    protected $description = 'Check that .claude/settings.json calls every Hook Command as declared';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $hints = [];

    public function handle(Registrations $registrations, SettingsMerge $merge): int
    {
        $this->errors = $this->warnings = $this->hints = [];
        $relative = '.claude/'.($this->option('local') ? 'settings.local.json' : 'settings.json');
        $path = rtrim((string) (config('claude-hooks.project_path') ?? base_path()), '/').'/'.$relative;

        ['valid' => $valid, 'invalid' => $invalid] = $registrations->checked();

        foreach ($invalid as $e) {
            $this->errors[] = $e->getMessage();
        }

        foreach ($valid as $registration) {
            if ($registration->transport === Transport::Http && $registration->event->guardsAnAction() && ! $registration->failClosed) {
                $this->warnings[] = ManagedEntry::for($registration)->describe().' uses the http Transport without failClosed:'
                    .' when the server is not running, Claude Code lets the action through unchecked.';
            }
        }

        foreach ($registrations->commands() as $command) {
            if (is_subclass_of($command, ReadyMadeHook::class)) {
                $this->checkBinaries($this->laravel->make($command));
            }
        }

        // Install cannot write http handlers yet (ticket 07), so only command Registrations are checked against settings.
        $this->checkSettings($relative, $path, array_values(array_filter($valid, fn (Registration $registration) => $registration->transport === Transport::Command)), $merge);

        foreach ($this->errors as $error) {
            $this->components->error($error);
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach (array_unique($this->hints) as $hint) {
            $this->line("  {$hint}");
        }

        if ($this->errors === [] && $this->warnings === []) {
            $this->components->info("OK: {$relative} calls every Hook Command as declared.");
        }

        return $this->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /** The binaries an enabled Ready-made Hook runs, which would make it fail open on every call if missing. */
    private function checkBinaries(ReadyMadeHook $hook): void
    {
        foreach ($hook->requiredBinaries() as $binary) {
            if (! is_file($binary) || ! is_executable($binary)) {
                $this->errors[] = "{$hook->getName()} needs {$binary}, which is missing or not executable.";
            }
        }
    }

    /**
     * The Managed Entries install would add, update or prune: reusing its merge, without writing anything.
     *
     * @param  list<Registration>  $registrations
     */
    private function checkSettings(string $relative, string $path, array $registrations, SettingsMerge $merge): void
    {
        try {
            $merged = $merge->merge(SettingsFile::read($path), $registrations);
        } catch (RuntimeException $e) {
            $this->errors[] = $e->getMessage();

            return;
        }

        foreach ($merged->added as $entry) {
            $this->errors[] = "Missing from {$relative}: {$entry->describe()}.";
        }

        foreach ($merged->updated as $entry) {
            $this->errors[] = "Outdated in {$relative}: {$entry->describe()}.";
        }

        foreach ($merged->stale as $entry) {
            $this->errors[] = "Stale in {$relative}: {$entry->describe()}.";
        }

        $local = $this->option('local') ? ' --local' : '';

        if ($merged->added !== [] || $merged->updated !== []) {
            $this->hints[] = "Run php artisan claude-hooks:install{$local} to write them.";
        }

        if ($merged->stale !== []) {
            $this->hints[] = "Run php artisan claude-hooks:install{$local} --prune to remove the stale ones.";
        }
    }
}
