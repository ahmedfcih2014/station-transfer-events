<?php

namespace App\Enum;

enum TransferEventStatus: string
{
    /**
     * hint: other statuses will be added later, for now we only process approved
     * others added for demonstration purposes
     */
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}
