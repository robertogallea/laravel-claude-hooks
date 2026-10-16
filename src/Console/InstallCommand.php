<?php

namespace RobertoGallea\ClaudeHooks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RobertoGallea\ClaudeHooks\InvalidRegistration;
use RobertoGallea\ClaudeHooks\ManagedEntry;
use RobertoGallea\ClaudeHooks\Registrations;
use RobertoGallea\ClaudeHooks\Settings\SettingsFile;
use RobertoGallea\ClaudeHooks\Settings\SettingsMerge;
use RuntimeException;

/** Writes a Managed Entry for every Registration into the project's Claude Code settings, keeping everything else. */
class InstallCommand extends Command
{
    protected $signature = 'claude-hooks:install
        {--local : Write to .claude/settings.local.json, which stays out of the repository}
        {--dry-run : Show what would change and write nothing}
        {--prune : Remove Managed Entries that no Registration matches any more}';

    protected $description = 'Write the Settings Entries of every Hook Command into .claude/settings.json';

    public function handle(Registrations $registrations, SettingsMerge $merge): int
    {
        $relative = '.claude/'.($this->option('local') ? 'settings.local.json' : 'settings.json');
        $path = rtrim((string) (config('claude-hooks.project_path') ?? base_path()), '/').'/'.$relative;

        try {
            $merged = $merge->merge(SettingsFile::read($path), $registrations->all(), (bool) $this->option('prune'));
        } catch (InvalidRegistration|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->list('+', $merged->added);
        $this->list('~', $merged->updated);
        $this->list('-', $merged->pruned ? $merged->stale : []);
        $this->list('?', $merged->pruned ? [] : $merged->stale, ' (stale: --prune removes it)');

        if (! $merged->changed()) {
            $this->components->info("Nothing to change in {$relative}.");
        } elseif ($this->option('dry-run')) {
            $this->components->info("Dry run: {$relative} was not written.");
        } else {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, SettingsFile::encode($merged->settings));
            $this->components->info("Wrote {$relative}.");
        }

        return self::SUCCESS;
    }

    /** @param  list<ManagedEntry>  $entries */
    private function list(string $mark, array $entries, string $note = ''): void
    {
        foreach ($entries as $entry) {
            $this->line("  {$mark} {$entry->describe()}{$note}");
        }
    }
}
