<?php

namespace App\Domain\Time\Enums;

enum TimeSource: string
{
    case SERVER = "SERVER";
    case USER = "USER";
    case DEVICE = "DEVICE";
    case EXTERNAL = "EXTERNAL";
    case IMPORT = "IMPORT";
    case ESTIMATED = "ESTIMATED";
}
