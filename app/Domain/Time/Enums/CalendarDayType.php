<?php

namespace App\Domain\Time\Enums;

enum CalendarDayType: string
{
    case BUSINESS = "BUSINESS";
    case WEEKEND = "WEEKEND";
    case HOLIDAY = "HOLIDAY";
    case CLOSED = "CLOSED";
}
