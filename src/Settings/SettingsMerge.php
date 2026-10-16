<?php

namespace RobertoGallea\ClaudeHooks\Settings;

use Closure;
use RobertoGallea\ClaudeHooks\InvalidRegistration;
use RobertoGallea\ClaudeHooks\ManagedEntry;
use RobertoGallea\ClaudeHooks\Registration;
use RobertoGallea\ClaudeHooks\Wire\ClaudeCode;

/**
 * Merges Registrations into Claude Code settings, touching only Managed Entries: user entries, sibling handlers in
 * the same matcher group and every other key stay where they are.
 */
final class SettingsMerge
{
    public function __construct(private readonly ClaudeCode $wire) {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<Registration>  $registrations
     * @param  bool  $prune  remove the Managed Entries no Registration matches
     *
     * @throws InvalidRegistration
     */
    public function merge(array $settings, array $registrations, bool $prune = false): MergedSettings
    {
        /** @var array<string, mixed> $hooks */
        $hooks = is_array($settings['hooks'] ?? null) ? $settings['hooks'] : [];
        $existing = $this->managedHandlers($hooks);
        $wanted = array_map(fn (Registration $registration) => [
            'entry' => ManagedEntry::for($registration),
            'handler' => $this->wire->handler($registration),
        ], $registrations);
        $pairs = $this->pair(array_column($wanted, 'entry'), array_column($existing, 'entry'));

        $added = $updated = $unchanged = $stale = $removed = $appended = [];

        foreach ($wanted as $w => ['entry' => $entry, 'handler' => $handler]) {
            $have = isset($pairs[$w]) ? $existing[$pairs[$w]] : null;
            $inPlace = $have !== null && $have['entry']->matcher === $entry->matcher;

            if ($inPlace && $have['handler'] === $handler) {
                $unchanged[] = $entry;

                continue;
            }

            if ($inPlace) {
                $hooks[$have['event']][$have['group']]['hooks'][$have['index']] = $handler;
            } elseif ($have !== null) {
                $removed[] = $have;
            }

            $have === null ? $added[] = $entry : $updated[] = $entry;

            if (! $inPlace) {
                $appended[$entry->event][$entry->matcher ?? "\0"][] = $handler;
            }
        }

        foreach (array_diff_key($existing, array_flip($pairs)) as $have) {
            $stale[] = $have['entry'];

            if ($prune) {
                $removed[] = $have;
            }
        }

        $hooks = $this->append($this->remove($hooks, $removed), $appended);

        if ($hooks === []) {
            unset($settings['hooks']);
        } else {
            $settings['hooks'] = $hooks;
        }

        return new MergedSettings($settings, $added, $updated, $unchanged, $stale, $prune);
    }

    /**
     * Every Managed Entry's handler in the settings' hooks, with where it sits.
     *
     * @param  array<string, mixed>  $hooks
     * @return list<array{event: string, group: array-key, index: array-key, entry: ManagedEntry, handler: array<array-key, mixed>}>
     */
    private function managedHandlers(array $hooks): array
    {
        $found = [];

        foreach ($hooks as $event => $groups) {
            foreach (is_array($groups) ? $groups : [] as $group => $matcherGroup) {
                $handlers = is_array($matcherGroup) && is_array($matcherGroup['hooks'] ?? null) ? $matcherGroup['hooks'] : [];
                $matcher = is_array($matcherGroup) && is_string($matcherGroup['matcher'] ?? null) ? $matcherGroup['matcher'] : null;

                foreach ($handlers as $index => $handler) {
                    $signature = is_array($handler) ? $this->wire->managedSignature($handler) : null;

                    if ($signature !== null) {
                        $if = is_string($handler['if'] ?? null) ? $handler['if'] : null;
                        $entry = new ManagedEntry((string) $event, $matcher, $signature, $if);
                        $found[] = ['event' => (string) $event, 'group' => $group, 'index' => $index, 'entry' => $entry, 'handler' => $handler];
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Pairs each wanted entry with the existing Managed Entry for the same Hook Command and event that resembles it
     * most: same matcher and `if` first, then same matcher, then any. A Registration whose matcher or `if` changed
     * thus updates its Managed Entry instead of leaving a stale one behind.
     *
     * @param  list<ManagedEntry>  $wanted
     * @param  list<ManagedEntry>  $existing
     * @return array<int, int> existing index by wanted index
     */
    private function pair(array $wanted, array $existing): array
    {
        $passes = [
            fn (ManagedEntry $want, ManagedEntry $have) => $want->matcher === $have->matcher && $want->if === $have->if,
            fn (ManagedEntry $want, ManagedEntry $have) => $want->matcher === $have->matcher,
            fn () => true,
        ];
        $pairs = [];

        foreach ($passes as $resembles) {
            foreach ($wanted as $w => $want) {
                $pairs[$w] ??= $this->firstUnpaired($want, $existing, $pairs, $resembles);
            }
        }

        return array_filter($pairs, fn (?int $e) => $e !== null);
    }

    /**
     * @param  list<ManagedEntry>  $existing
     * @param  array<int, int|null>  $pairs
     * @param  Closure(ManagedEntry, ManagedEntry): bool  $resembles
     */
    private function firstUnpaired(ManagedEntry $want, array $existing, array $pairs, Closure $resembles): ?int
    {
        foreach ($existing as $e => $have) {
            if ($want->sameHook($have) && $resembles($want, $have) && ! in_array($e, $pairs, true)) {
                return $e;
            }
        }

        return null;
    }

    /**
     * Removes handlers, then the matcher groups and events they leave empty.
     *
     * @param  array<string, mixed>  $hooks
     * @param  list<array{event: string, group: array-key, index: array-key, entry: ManagedEntry, handler: array<array-key, mixed>}>  $removed
     * @return array<string, mixed>
     */
    private function remove(array $hooks, array $removed): array
    {
        foreach ($removed as ['event' => $event, 'group' => $group, 'index' => $index]) {
            unset($hooks[$event][$group]['hooks'][$index]);
        }

        foreach ($removed as ['event' => $event, 'group' => $group]) {
            if (! isset($hooks[$event][$group])) {
                continue;
            }

            $hooks[$event][$group]['hooks'] = array_values($hooks[$event][$group]['hooks']);

            if ($hooks[$event][$group]['hooks'] === []) {
                unset($hooks[$event][$group]);
            }
        }

        foreach (array_unique(array_column($removed, 'event')) as $event) {
            $hooks[$event] = array_values($hooks[$event]);

            if ($hooks[$event] === []) {
                unset($hooks[$event]);
            }
        }

        return $hooks;
    }

    /**
     * Appends one new matcher group per event and matcher; the group has no `matcher` when the Registration has none.
     *
     * @param  array<string, mixed>  $hooks
     * @param  array<string, array<string, list<array<string, mixed>>>>  $appended  handlers by event and matcher ("\0" for none)
     * @return array<string, mixed>
     */
    private function append(array $hooks, array $appended): array
    {
        foreach ($appended as $event => $groups) {
            foreach ($groups as $matcher => $handlers) {
                $hooks[$event] = [
                    ...(is_array($hooks[$event] ?? null) ? $hooks[$event] : []),
                    ($matcher === "\0" ? [] : ['matcher' => (string) $matcher]) + ['hooks' => $handlers],
                ];
            }
        }

        return $hooks;
    }
}
