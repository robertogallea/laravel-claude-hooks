# Laravel Claude Hooks

Laravel Claude Hooks lets a Laravel application answer Claude Code hooks with its own code, so that rules which need to look at the application (its database, configuration or tests) are enforced every time instead of being left to the model.

## Language

### Claude Code's side

**Hook**:
Claude Code's mechanism for running a handler at a fixed point of a session, whatever the model decides.
_Avoid_: Agent hook, callback

**Hook Event**:
The point of a session at which a Hook fires, such as `PreToolUse` or `Stop`, named exactly as Claude Code names it.
_Avoid_: Trigger, lifecycle event

**Settings Entry**:
A Hook as written in a Claude Code settings file: an event, an optional matcher, and a handler.
_Avoid_: Hook config, binding

### The application's side

**Hook Command**:
An Artisan command in the application that answers exactly the Hooks it is registered for.
_Avoid_: Hook script, handler, listener

**Registration**:
The declaration, on a Hook Command, of the Hook Event and filters it answers, from which its Settings Entry is derived.
_Avoid_: Binding, subscription

**Ready-made Hook**:
A Hook Command shipped by this package, inactive until the application enables it.
_Avoid_: Built-in hook, preset, default hook

**Managed Entry**:
A Settings Entry that this package wrote and recognises as its own; every other Settings Entry belongs to the user and is never touched.
_Avoid_: Our entry, generated entry

**Hook Input**:
The typed form of the payload Claude Code sends for one Hook Event.
_Avoid_: Payload (reserved for the raw JSON), request

**Hook Response**:
What a Hook Command answers to Claude Code; answering nothing means "no opinion" and the normal flow continues.
_Avoid_: Output, result, return value

**Fail-open**:
The default stance that a Hook Command which fails lets the action proceed, unless its Registration asks to fail closed.

**Transport**:
How Claude Code reaches a Hook Command: `command` (a fresh Artisan process that inherits Claude Code's environment) or `http` (a request to an application that is already running, with that application's environment).
_Avoid_: Driver, mode, channel
