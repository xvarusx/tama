<?php

declare(strict_types=1);

namespace App\Enum;

enum DeliveryStatus: string
{
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case RETRY_WAITING = 'retry_waiting';
    case FAILED = 'failed';
}
