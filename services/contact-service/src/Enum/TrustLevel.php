<?php

declare(strict_types=1);

namespace App\Enum;

enum TrustLevel: string
{
    case UNRATED = 'unrated';   // default for new contacts
    case TRUSTED = 'trusted';
    case WATCHLIST = 'watchlist'; // not blocked, but needs attention
}
