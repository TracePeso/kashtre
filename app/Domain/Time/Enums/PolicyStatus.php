<?php

namespace App\Domain\Time\Enums;

enum PolicyStatus: string
{
    case DRAFT = "DRAFT";
    case UNDER_REVIEW = "UNDER_REVIEW";
    case APPROVED = "APPROVED";
    case SCHEDULED = "SCHEDULED";
    case ACTIVE = "ACTIVE";
    case SUPERSEDED = "SUPERSEDED";
    case CANCELLED = "CANCELLED";
    case REJECTED = "REJECTED";
}
