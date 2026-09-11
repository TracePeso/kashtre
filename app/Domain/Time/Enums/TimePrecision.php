<?php

namespace App\Domain\Time\Enums;

enum TimePrecision: string
{
    case DATE = "DATE";
    case MINUTE = "MINUTE";
    case SECOND = "SECOND";
    case MILLISECOND = "MILLISECOND";
    case MICROSECOND = "MICROSECOND";
}
