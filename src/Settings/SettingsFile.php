<?php

namespace RobertoGallea\ClaudeHooks\Settings;

use JsonException;
use RuntimeException;
use stdClass;

/** Reads and formats a Claude Code settings file: two-space JSON, unescaped slashes, a trailing newline. */
final class SettingsFile
{
    /**
     * The settings in a file, or none when it does not exist.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the file is not a JSON object
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $settings = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("{$path} is not valid JSON: {$e->getMessage()}.", previous: $e);
        }

        if (! $settings instanceof stdClass) {
            throw new RuntimeException("{$path} does not hold a JSON object.");
        }

        /** @var array<string, mixed> */
        return self::toArrays($settings);
    }

    /** @param  array<string, mixed>  $settings */
    public static function encode(array $settings): string
    {
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return preg_replace_callback('/^(?: {4})+/m', fn (array $indent) => str_repeat('  ', intdiv(strlen($indent[0]), 4)), $json)."\n";
    }

    /** Decoded JSON with objects as arrays, except empty objects, which stay objects so they are written back as {}. */
    private static function toArrays(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;

            return $value === [] ? new stdClass : array_map(self::toArrays(...), $value);
        }

        return is_array($value) ? array_map(self::toArrays(...), $value) : $value;
    }
}
