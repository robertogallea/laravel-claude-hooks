> ## Documentation Index
> Fetch the complete documentation index at: https://code.claude.com/docs/llms.txt
> Use this file to discover all available pages before exploring further.

# Hooks reference

## Hook events

Each event corresponds to a point in Claude Code's lifecycle where hooks can run. The sections below are ordered to match the lifecycle: from session setup through the agentic loop to session end. Each section describes when the event fires, what matchers it supports, the JSON input it receives, and how to control behavior through output.

### SessionStart

Runs when Claude Code starts a new session or resumes an existing session. Useful for loading development context like existing issues or recent changes to your codebase, or setting up environment variables. For static context that doesn't require a script, use [CLAUDE.md](/docs/en/memory) instead.

#### SessionStart input

### Setup

Fires only when you launch Claude Code with `--init-only`, or with `--init` or `--maintenance` in [non-interactive mode](/docs/en/headless) with the `-p` flag. It doesn't fire on normal startup. Use it for one-time dependency installation or scheduled cleanup that you trigger explicitly from CI or scripts, separate from normal session startup. For per-session initialization, use [SessionStart](#sessionstart) instead.

#### Setup input

### InstructionsLoaded

Fires when a `CLAUDE.md` or `.claude/rules/*.md` file is loaded into context. This event fires at session start for eagerly loaded files and again later when files are lazily loaded, for example when Claude accesses a subdirectory that contains a nested `CLAUDE.md` or when conditional rules with `paths:` frontmatter match. The hook doesn't support blocking or decision control. It runs asynchronously for observability purposes.

#### InstructionsLoaded input

### UserPromptSubmit

Runs when a prompt is submitted, before Claude processes it. This allows you

#### UserPromptSubmit input

### UserPromptExpansion

Runs when a user-typed command expands into a prompt before reaching Claude. Use this to block specific commands from direct invocation, inject context for a particular skill, or log which commands users invoke. For example, a hook matching `deploy` can block `/deploy` unless an approval file is present, or a hook matching a review skill can append the team's review checklist as `additionalContext`.

#### UserPromptExpansion input

### MessageDisplay

Runs while an assistant message streams to the screen. Claude Code displays the message in increments: each time a batch of newly completed lines is ready to render, the hook runs once with those lines and Claude Code renders the hook's replacement text in their place. A long message produces several calls; a short message may produce only one.

#### MessageDisplay input

### PreToolUse

Runs after Claude creates tool parameters and before processing the tool call. Matches on any tool name except `EndConversation`: built-in tools such as `Bash`, `PowerShell`, `Edit`, `Write`, `Read`, `Glob`, `Grep`, `Agent`, `Workflow`, `WebFetch`, `WebSearch`, `AskUserQuestion`, and `ExitPlanMode`, and any [MCP tool names](#match-mcp-tools).

#### PreToolUse input

### PermissionRequest

Runs when Claude Code is about to ask you for permission to use a tool. In sessions that can't show a prompt, such as background subagents in [non-interactive mode](/docs/en/headless), Claude Code still runs these hooks, and if no hook returns a decision, it denies the tool call. For a call that reaches a `--permission-prompt-tool` or the Agent SDK's [`canUseTool` callback](/docs/en/agent-sdk/permissions), the hooks run alongside your host, and whichever decides first applies.

#### PermissionRequest input

### PostToolUse

Runs immediately after a tool completes successfully.

#### PostToolUse input

### PostToolUseFailure

Runs when a tool that started executing fails: the tool threw an error, or an MCP tool returned an error result. Use this to log failures, send alerts, or provide corrective feedback to Claude.

#### PostToolUseFailure input

### PostToolBatch

Runs once after every tool call in a batch has resolved, before Claude Code sends the next request to the model. `PostToolUse` fires once per tool, which means it fires concurrently when Claude makes parallel tool calls. `PostToolBatch` fires exactly once with the full batch, so it is the right place to inject context that depends on the set of tools that ran rather than on any single tool. There is no matcher for this event.

#### PostToolBatch input

### PermissionDenied

Runs when [auto mode](/docs/en/permission-modes#eliminate-prompts-with-auto-mode) denies a tool call, including when it denies without a classifier verdict because [a safety check separate from auto mode refused the classifier's own request](/docs/en/errors#auto-mode-cannot-determine-the-safety-of-an-action) or its response didn't parse. This hook only fires in auto mode: it doesn't run when you manually deny a permission dialog, when a `PreToolUse` hook blocks a call, or when a `deny` rule matches. Use it to log denials, adjust configuration, or tell the model it may retry the tool call.

#### PermissionDenied input

### Notification

Runs when Claude Code sends notifications. Matches on notification type. Omit the matcher to run hooks for all notification types.

#### Notification input

### SubagentStart

Runs when Claude spawns a subagent with the Agent tool, when Claude [resumes a subagent](/docs/en/sub-agents#resume-subagents), and each time an in-process [agent team](/docs/en/agent-teams) teammate handles a new message. Supports matchers to filter by agent type name. For built-in agents, this is the agent name like `general-purpose`, `Explore`, or `Plan`. For [custom subagents](/docs/en/sub-agents), this is the `name` field from the agent's frontmatter, not the filename.

#### SubagentStart input

### SubagentStop

Runs when a Claude Code subagent has finished responding. Matches on agent type, same values as SubagentStart.

#### SubagentStop input

### TaskCreated

Runs when a task is being created via the `TaskCreate` tool. Use this to enforce naming conventions, require task descriptions, or prevent certain tasks from being created. In a [session without the Task tools](/docs/en/tools-reference#task-tool-availability), this event doesn't fire.

#### TaskCreated input

### TaskCompleted

Runs when a task is being marked as completed. This fires in two situations: when any agent explicitly marks a task as completed through the TaskUpdate tool, or when an [agent team](/docs/en/agent-teams) teammate finishes its turn with in-progress tasks. Use this to enforce completion criteria like passing tests or lint checks before a task can close.

#### TaskCompleted input

### Stop

Runs when the main Claude Code agent has finished responding. Does not run if

#### Stop input

### StopFailure

Runs instead of [Stop](#stop) when the turn ends due to an API error. Claude Code ignores the hook's output and exit code, apart from [`terminalSequence`](#emit-terminal-notifications). Use this to log failures, send alerts, or take recovery actions when Claude can't complete a response due to rate limits, authentication problems, or other API errors.

#### StopFailure input

### TeammateIdle

Runs when an [agent team](/docs/en/agent-teams) teammate is about to go idle after finishing its turn. Use this to enforce quality gates before a teammate stops working, such as requiring passing lint checks or verifying that output files exist.

#### TeammateIdle input

### ConfigChange

Runs when a configuration file changes during a session. Use this to audit settings changes, enforce security policies, or block unauthorized modifications to configuration files.

#### ConfigChange input

### CwdChanged

Runs when a shell command in the main conversation changes the working directory, for example when Claude executes a `cd` command. Use this to react to directory changes: reload environment variables, activate project-specific toolchains, or run setup scripts automatically. Pairs with [FileChanged](#filechanged) for tools like [direnv](https://direnv.net/) that manage per-directory environment.

#### CwdChanged input

### DirectoryAdded

Runs after you add a working directory mid-session with the `/add-dir` command, or after an SDK client adds one with the `register_repo_root` control request. Use this to prepare a newly added repository, for example by installing its dependencies.

#### DirectoryAdded input

### FileChanged

Runs when a watched file changes on disk. Claude Code detects changes with a filesystem watcher, not by inspecting tool calls, so it runs the hook no matter what changed the file: an `Edit` or `Write` tool call, a script Claude runs with `Bash`, or a process outside Claude Code entirely. A common use is reloading environment variables when project configuration files change.

#### FileChanged input

### WorktreeCreate

Runs when Claude Code creates a worktree in cases such as these:

#### WorktreeCreate input

### WorktreeRemove

Runs when Claude Code cleans up a worktree that your [`WorktreeCreate`](#worktreecreate) hook created. The event fires when:

#### WorktreeRemove input

### PreCompact

Runs before Claude Code is about to run a compact operation.

#### PreCompact input

### PostCompact

Runs after Claude Code completes a compact operation. Use this event to react to the new compacted state, for example to log the generated summary or update external state. Claude Code discards a PostCompact hook's `systemMessage` and `continue` fields.

#### PostCompact input

### PreModelSwitch

Runs before Claude Code applies a model switch that you or a client requested. Use it to block a switch, require confirmation, or show what the switch will cost before it happens.

#### PreModelSwitch input

### PostModelSwitch

Runs after the session's model changes. Use it to give Claude model-specific guidance without editing every CLAUDE.md, for example an organization-wide instruction that applies on certain models.

#### PostModelSwitch input

### SessionEnd

Runs when a Claude Code session ends. Useful for cleanup tasks, logging session

#### SessionEnd input

### Elicitation

Runs when an MCP server requests user input mid-task. By default, Claude Code shows an interactive dialog for the user to respond. Hooks can intercept this request and respond programmatically, skipping the dialog entirely.

#### Elicitation input

### ElicitationResult

Runs after a user responds to an MCP elicitation. Hooks can observe, modify, or block the response before it is sent back to the MCP server.

#### ElicitationResult input
## Prompt-based hooks
