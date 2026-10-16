<?php

namespace RobertoGallea\ClaudeHooks\Wire;

use InvalidArgumentException;
use RobertoGallea\ClaudeHooks\Decision;
use RobertoGallea\ClaudeHooks\Event;
use RobertoGallea\ClaudeHooks\HookResponse;
use RobertoGallea\ClaudeHooks\Inputs\GenericInput;
use RobertoGallea\ClaudeHooks\Inputs\HookInput;
use RobertoGallea\ClaudeHooks\InvalidRegistration;
use RobertoGallea\ClaudeHooks\Registration;
use RobertoGallea\ClaudeHooks\Responses\BareResponse;
use RobertoGallea\ClaudeHooks\Transport;

/**
 * The translation seam: the only place that knows Claude Code's payload, response and hook handler JSON.
 *
 * @internal
 */
final class ClaudeCode
{
    /** The path, under the application's URL, of the route the http Transport posts to. */
    public const HTTP_PATH = 'claude-hooks';

    /** The environment variable from which Claude Code sends the http Transport's bearer token. */
    private const HTTP_TOKEN = 'CLAUDE_HOOKS_TOKEN';

    /**
     * The Hook Input for a payload, among the Hook Input classes a handler accepts.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<class-string<HookInput>>  $accepted
     */
    public function decode(array $payload, array $accepted): HookInput
    {
        $name = $payload['hook_event_name'] ?? null;

        if ($name === null && count($accepted) === 1) {
            return new $accepted[0]($payload);
        }

        $class = Event::tryFrom((string) $name)?->inputClass() ?? GenericInput::class;

        if (! in_array($class, $accepted, true)) {
            $class = in_array(GenericInput::class, $accepted, true) ? GenericInput::class : null;
        }

        if ($class === null) {
            throw new InvalidArgumentException("This hook does not handle the '{$name}' event.");
        }

        return new $class($payload);
    }

    /**
     * The JSON object Claude Code expects for a Hook Response.
     *
     * @return array<string, mixed>
     */
    public function encode(HookResponse $response): array
    {
        $specific = $this->withoutNulls(match ($response->event) {
            Event::PreToolUse => [
                'permissionDecision' => $response->decision?->value,
                'permissionDecisionReason' => $response->reason,
                'updatedInput' => $response->updatedInput,
            ],
            Event::PermissionRequest => [
                'decision' => $response->decision === null ? null : $this->withoutNulls([
                    'behavior' => $response->decision->value,
                    'updatedInput' => $response->updatedInput,
                    'updatedPermissions' => $response->updatedPermissions,
                    'message' => $response->reason,
                    'interrupt' => $response->interrupt ?: null,
                ]),
            ],
            default => [],
        } + [
            'additionalContext' => $response->context,
            'sessionTitle' => $response->sessionTitle,
            'initialUserMessage' => $response->initialUserMessage,
            'updatedToolOutput' => $response->updatedToolOutput,
        ]);

        $universal = $response instanceof BareResponse ? [] : $this->withoutNulls([
            'continue' => $response->stopReason === null ? null : false,
            'stopReason' => $response->stopReason,
            'systemMessage' => $response->systemMessage,
        ]);

        return $universal + ($response->decision === Decision::Block ? $this->withoutNulls([
            'decision' => 'block',
            'reason' => $response->reason,
        ]) : []) + ($specific === [] ? [] : [
            'hookSpecificOutput' => ['hookEventName' => $response->event->value, ...$specific],
        ]);
    }

    /**
     * The hook handler of a Registration's Settings Entry, with only the fields that differ from Claude Code's defaults.
     *
     * @param  string|null  $httpUrl  the application's base URL, which a Registration on the http Transport needs
     * @return array<string, mixed>
     *
     * @throws InvalidRegistration
     */
    public function handler(Registration $registration, ?string $httpUrl = null): array
    {
        $transport = $registration->transport === Transport::Http ? [
            'type' => 'http',
            'url' => rtrim($httpUrl ?? throw new InvalidRegistration("{$registration->command} uses the http Transport, which needs the application's URL: pass --http=<base-url> or set claude-hooks.http.url."), '/')
                .'/'.self::HTTP_PATH.'/'.$registration->signature,
            'headers' => ['Authorization' => 'Bearer $'.self::HTTP_TOKEN],
            'allowedEnvVars' => [self::HTTP_TOKEN],
        ] : [
            'type' => 'command',
            'command' => 'php "$CLAUDE_PROJECT_DIR/artisan" '.$registration->signature,
        ];

        return $this->withoutNulls($transport + [
            'if' => $registration->if,
            'timeout' => $registration->timeout,
            'statusMessage' => $registration->statusMessage,
            'async' => $registration->async ?: null,
            'asyncRewake' => $registration->asyncRewake ?: null,
            'onFailure' => $registration->failClosed ? 'block' : null,
        ]);
    }

    /**
     * The signature of the Hook Command a hook handler runs, when it is the handler of a Managed Entry: the command
     * Transport's Artisan command or the http Transport's package route. Null when the handler belongs to the user.
     *
     * @param  array<array-key, mixed>  $handler
     */
    public function managedSignature(array $handler): ?string
    {
        $command = $handler['command'] ?? null;
        $url = $handler['url'] ?? null;

        if (($handler['type'] ?? null) === 'command' && is_string($command)
            && preg_match('/\Aphp "\$CLAUDE_PROJECT_DIR\/artisan" (\S+)\z/', $command, $matches) === 1) {
            return $matches[1];
        }

        // Whatever the application's URL, a URL whose last segment follows the package route's path.
        if (($handler['type'] ?? null) === 'http' && is_string($url)
            && preg_match('#\Ahttps?://\S*/'.self::HTTP_PATH.'/([^/?\#\s]+)\z#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function withoutNulls(array $fields): array
    {
        return array_filter($fields, fn (mixed $value) => $value !== null);
    }
}
