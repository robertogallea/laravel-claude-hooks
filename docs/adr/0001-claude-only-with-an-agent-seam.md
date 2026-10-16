# Claude Code only, with one seam for other agents

Codex CLI, Gemini CLI and Cursor have hook systems with similar events but different names, payloads and response shapes, and no library maps between them. v1 supports Claude Code only: Hook Inputs and Hook Responses use Claude's vocabulary, and reading the payload and writing the response pass through a single internal translation seam, so an adapter for another agent can be added later without changing Hook Commands.

## Considered Options

- **A neutral, agent-agnostic model from day one**: rejected because it forces lowest-common-denominator names onto every user to serve agents nobody has asked for yet. Cursor already runs Claude-format hooks, which covers part of the demand.
