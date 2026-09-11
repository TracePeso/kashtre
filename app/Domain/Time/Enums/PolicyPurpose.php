<?php

namespace App\Domain\Time\Enums;

enum PolicyPurpose: string
{
    case OPERATIONAL = "OPERATIONAL";
    case FINANCIAL = "FINANCIAL";
    case REPORTING = "REPORTING";
    case PRESENTATION = "PRESENTATION";
}
