# Hook Inputs, helpers, fakes and assertions

All classes are in `RobertoGallea\ClaudeHooks\Inputs`. Every helper returns a Hook Response; `hook()` returns it, or `null` for no opinion.

## Helpers per Hook Input

| Hook Input | Helpers |
| --- | --- |
| `PreToolUseInput` | `allow(?reason, ?updatedInput)`, `deny(reason)`, `ask(?reason)`, `defer(?reason)`, `updateInput(input)`, `addContext(context)` |
| `PermissionRequestInput` | `allow(?updatedInput, ?updatedPermissions)`, `deny(?message, interrupt: false)` |
| `PostToolUseInput` | `block(?reason)` (adds the reason next to the result; the tool already ran), `addContext(context)`, `replaceOutput(output)` |
| `PostToolUseFailureInput` | `addContext(context)` |
| `PostToolBatchInput` | `block(?reason)`, `addContext(context)` |
| `UserPromptSubmitInput` | `block(?reason)` (the prompt never reaches Claude), `addContext(context)`, `setSessionTitle(title)` |
| `StopInput`, `SubagentStopInput` | `block(reason)` (Claude keeps working; the reason is its next instruction), `addContext(context)` |
| `SessionStartInput` | `addContext(context)`, `setSessionTitle(title)`, `setInitialUserMessage(message)` |
| `PreCompactInput` | `block(?reason)` |
| `NotificationInput`, `SessionEndInput` | none: Claude Code discards their output; use them for side effects. `SessionEnd` hooks share a 1.5 s budget |
| `GenericInput` | none: every other Hook Event; read fields with `get()` |

Every Hook Input with helpers except `PreCompactInput` also has `systemMessage(message)` (a warning for the user) and `stopSession(reason)` (stops Claude entirely). Chain them on a response: `$input->deny($reason)->withSystemMessage('...')`, `->stopSession('...')`. `$response->and($other)` merges two responses of the same event.

`PreToolUseInput::deny()` is the way to stop a tool call before it runs. `ask()` lets the user decide, which suits rules where the user may know better than the hook.

## Reading the payload

- Every Hook Input: `sessionId()`, `cwd()`, `permissionMode()`, `transcriptPath()`, `agentId()`, `agentType()`, `eventName()`, `get('dot.path', $default)`, and the raw `payload` array.
- Tool events (`PreToolUse`, `PermissionRequest`, `PostToolUse`, `PostToolUseFailure`): `toolName()`, `toolInput('dot.path')`, `filePath()` (Edit, Write, Read), `command()` (Bash), `toolUseId()`.
- `PostToolUseInput`: `toolResponse('dot.path')`, `durationMs()`. `PostToolUseFailureInput`: `error()`, `isInterrupt()`, `durationMs()`.
- `PermissionRequestInput`: `permissionSuggestions()`.
- `PostToolBatchInput`: `toolCalls()`.
- `UserPromptSubmitInput`: `prompt()`, `sessionTitle()`.
- `StopInput`, `SubagentStopInput`: `stopHookActive()`, `lastAssistantMessage()`, `backgroundTasks()`, `sessionCrons()`; `SubagentStopInput` adds `agentTranscriptPath()`.
- `SessionStartInput`: `source()` (`startup`, `resume`, `clear`, `compact`, `fork`), `model()`, `sessionTitle()`.
- `PreCompactInput`: `trigger()`, `customInstructions()`. `SessionEndInput`: `reason()`. `NotificationInput`: `message()`, `title()`, `notificationType()`.

A `Bash` matcher sees every shell command, including `sed -i` on a file an `Edit|Write` guard protects. When the rule protects files, decide with the user whether to add a `Bash` Registration that looks for those paths in `command()`.

## Fakes

- `XInput::fake()` builds a Hook Input with realistic common fields; `XInput::fake(['field' => 'value'])` overrides any of them, such as `StopInput::fake(['stop_hook_active' => true])`.
- On tool events: `->edit($path)`, `->write($path, $content)`, `->bash($command)`.
- `->with(['field' => 'value'])` replaces fields on any Hook Input.
- `GenericInput::fakeFor('EventName', [...])` for an event without a typed Hook Input.

## Assertions

`$this->runHook(Command::class, $input)` asserts exit code 0 and returns a result with:

- `assertNoOpinion()`: the hook answered nothing. `assertNoDecision()`: no permission decision and no block, while context or a message is allowed.
- `assertAllowed(?text)`, `assertDenied(?text)`, `assertAsked(?text)`, `assertDeferred(?text)`, `assertBlocked(?text)`: the text is a fragment the reason must contain.
- `assertContextContains(text)`, `assertSystemMessage(?text)`, `assertStoppedSession(?text)`, `assertSessionTitle(?text)`, `assertInitialUserMessage(?text)`.
- `assertUpdatedInput(array)`, `assertUpdatedOutput(array|string)`, `assertUpdatedPermissions(array)`, `assertInterrupted()`.
- `json` and `get('dot.path')` for anything else.

`$this->assertRegistered(Command::class, Event::PreToolUse, matcher: 'Bash', if: 'Bash(php artisan *)')` checks one Registration; omitted arguments mean no matcher or no `if`.

A Hook Command that needs the database in its test uses `RefreshDatabase` as any Laravel test does. Fake external processes with `Process::fake()`.
