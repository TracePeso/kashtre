<?php

namespace App\Domain\Time\Enums;

enum ClockHealthStatus: string
{
    case HEALTHY = "HEALTHY";
    case WARNING = "WARNING";
    case DEGRADED = "DEGRADED";
    case UNHEALTHY = "UNHEALTHY";
    case UNKNOWN = "UNKNOWN";
}
