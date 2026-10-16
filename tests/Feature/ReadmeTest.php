<?php

use App\Console\Commands\ClaudeHooks\GuardTranslations;
use Illuminate\Support\Facades\File;

/*
 * The README is the package's documentation, so its samples are run here rather than copied into fixtures.
 * Every PHP and JSON sample in it sits under a `<!-- readme-test: <name> -->` comment, by which this file
 * finds it. The Hook Command sample is loaded as a class, and the Pest sample is loaded into this file, so
 * its tests run as written. The Boost skill teaches with the same two PHP samples.
 */

/**
 * The README's tested samples, by name.
 *
 * @return array<string, string>
 */
function readmeSamples(): array
{
    preg_match_all('/<!-- readme-test: ([\w-]+) -->\n```\w+\n(.*?)\n```/s', (string) file_get_contents(__DIR__.'/../../README.md'), $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

/** Load a README sample as a PHP file of its own. */
function requireReadmeSample(string $name): void
{
    $sample = readmeSamples()[$name] ?? throw new LogicException("The README has no sample named {$name}.");
    $path = sys_get_temp_dir()."/claude-hooks-readme-{$name}-".md5($sample).'.php';
    file_put_contents($path, $sample);

    require_once $path;
}

/**
 * Run install in a fresh project folder and return the settings file it writes.
 *
 * @param  array<string, mixed>  $options
 */
function installedSettings(array $options = []): string
{
    $project = sys_get_temp_dir().'/claude-hooks-readme-'.bin2hex(random_bytes(4));
    mkdir($project);
    config(['claude-hooks.project_path' => $project]);

    try {
        test()->artisan('claude-hooks:install', $options)->assertSuccessful();

        return trim((string) file_get_contents("{$project}/.claude/settings.json"));
    } finally {
        File::deleteDirectory($project);
    }
}

requireReadmeSample('hook-command');
requireReadmeSample('hook-command-test');

it('tests every PHP and JSON sample in the README', function () {
    preg_match_all('/(?:<!-- readme-test: ([\w-]+) -->\n)?```(php|json)\n/', (string) file_get_contents(__DIR__.'/../../README.md'), $fences, PREG_SET_ORDER);

    foreach ($fences as $fence) {
        expect($fence[1] ?? '')->not->toBe('', "A ```{$fence[2]} sample in the README has no readme-test comment.");
    }

    expect(array_keys(readmeSamples()))->toEqualCanonicalizing([
        'hook-command', 'hook-command-test', 'settings-command', 'settings-http', 'settings-guard-artisan',
    ]);
});

it('teaches the Boost skill with the README\'s tested samples', function () {
    preg_match_all('/```php\n(.*?)\n```/s', (string) file_get_contents(__DIR__.'/../../resources/boost/skills/laravel-claude-hooks-writing-hooks/SKILL.md'), $samples);

    expect($samples[1])->toBe([readmeSamples()['hook-command'], readmeSamples()['hook-command-test']]);
});

it('writes the Settings Entry the README shows for the Hook Command', function () {
    hookCommands(GuardTranslations::class);

    expect(installedSettings())->toBe(readmeSamples()['settings-command']);
});

it('writes the http handler the README shows', function () {
    hookCommands(GuardTranslations::class);

    expect(installedSettings(['--http' => 'http://localhost:8000']))->toBe(readmeSamples()['settings-http']);
});

it('writes the Settings Entry the README shows for guard-artisan', function () {
    enableReadyMadeHook('guard-artisan');

    expect(installedSettings())->toBe(readmeSamples()['settings-guard-artisan']);
});
