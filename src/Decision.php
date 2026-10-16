<?php

namespace RobertoGallea\ClaudeHooks;

/** What a Hook Response decides about the action it was asked about. */
enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Ask = 'ask';
    case Defer = 'defer';
    case Block = 'block';
}
