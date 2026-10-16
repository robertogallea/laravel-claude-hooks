<?php

namespace RobertoGallea\ClaudeHooks;

use LogicException;

/** A Registration Claude Code would ignore or contradict, so the hook would silently never run as declared. */
final class InvalidRegistration extends LogicException {}
