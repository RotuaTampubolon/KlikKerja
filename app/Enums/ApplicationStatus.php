<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case SENT = 'sent';
    case SEEN = 'seen';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
