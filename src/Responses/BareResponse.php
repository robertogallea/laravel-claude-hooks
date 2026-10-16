<?php

namespace RobertoGallea\ClaudeHooks\Responses;

use RobertoGallea\ClaudeHooks\HookResponse;

/** A Hook Response for an event whose system message and `continue` Claude Code discards, so it offers neither. */
final readonly class BareResponse extends HookResponse {}
