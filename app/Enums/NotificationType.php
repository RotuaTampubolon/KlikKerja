<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewApplication = 'new_application';
    case ApplicationSeen = 'application_seen';
    case ApplicationAccepted = 'application_accepted';
    case ApplicationRejected = 'application_rejected';
    case VerificationResult = 'verification_result';
    case Warning = 'warning';
    case Sanction = 'sanction';
    case ModerationAction = 'moderation_action';
}
