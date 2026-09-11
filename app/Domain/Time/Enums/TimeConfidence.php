<?php

namespace App\Domain\Time\Enums;

enum TimeConfidence: string
{
    case EXACT = "EXACT";
    case HIGH = "HIGH";
    case MEDIUM = "MEDIUM";
    case LOW = "LOW";
    case UNRESOLVED = "UNRESOLVED";
}
