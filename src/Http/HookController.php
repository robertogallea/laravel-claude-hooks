<?php

namespace RobertoGallea\ClaudeHooks\Http;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RobertoGallea\ClaudeHooks\HookCommand;
use RobertoGallea\ClaudeHooks\Wire\ClaudeCode;

/**
 * The http Transport: answers a hook payload that Claude Code POSTs with the configured bearer token, with the
 * Hook Command of that signature, in the already running application. A Hook Command that is not http-safe is
 * never run here, even from a Settings Entry written by hand (ADR 0005): it answers 404. Exceptions are not caught, so Laravel
 * answers 500, which Claude Code treats as a non-blocking error.
 *
 * @internal
 */
final class HookController
{
    public function __invoke(Request $request, string $signature, Kernel $artisan, ClaudeCode $wire): Response
    {
        $token = config('claude-hooks.http.token');

        if (! is_string($token) || $token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(401, 'The bearer token is missing or wrong.');
        }

        $command = $artisan->all()[$signature] ?? null;

        if (! $command instanceof HookCommand) {
            abort(404, "No Hook Command is named {$signature}.");
        }

        if (! $command::httpSafe()) {
            abort(new JsonResponse([
                'message' => "{$signature} is command-only: its answer depends on Claude Code's environment, so it is not served over http.",
            ], 404));
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(trim($request->getContent()) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $response = $command->respond($payload, $wire);

        return $response === null
            ? new Response('', 200)
            : new Response(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
    }
}
