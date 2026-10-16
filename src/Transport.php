<?php

namespace RobertoGallea\ClaudeHooks;

/** How Claude Code reaches a Hook Command. */
enum Transport: string
{
    /** A fresh Artisan process that inherits Claude Code's environment. */
    case Command = 'command';

    /** A request to an application that is already running, with that application's environment. */
    case Http = 'http';
}
