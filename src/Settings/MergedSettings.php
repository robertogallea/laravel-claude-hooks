<?php

namespace RobertoGallea\ClaudeHooks\Settings;

use RobertoGallea\ClaudeHooks\ManagedEntry;

/** A settings file after merging the Registrations into it, and what the merge changed. */
final readonly class MergedSettings
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<ManagedEntry>  $added  Registrations that had no Managed Entry
     * @param  list<ManagedEntry>  $updated  Managed Entries rewritten because their Registration changed
     * @param  list<ManagedEntry>  $unchanged  Managed Entries that already match their Registration
     * @param  list<ManagedEntry>  $stale  Managed Entries no Registration matches
     * @param  bool  $pruned  whether the stale Managed Entries were removed
     */
    public function __construct(
        public array $settings,
        public array $added,
        public array $updated,
        public array $unchanged,
        public array $stale,
        public bool $pruned,
    ) {}

    /** Whether the settings file has to be written. */
    public function changed(): bool
    {
        return $this->added !== [] || $this->updated !== [] || ($this->pruned && $this->stale !== []);
    }
}
