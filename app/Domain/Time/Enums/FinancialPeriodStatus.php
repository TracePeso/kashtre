<?php

namespace App\Domain\Time\Enums;

enum FinancialPeriodStatus: string
{
    case OPEN = "OPEN";
    case CLOSED = "CLOSED";
    case REOPENED = "REOPENED";
}
