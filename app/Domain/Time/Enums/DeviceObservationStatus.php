<?php

namespace App\Domain\Time\Enums;

enum DeviceObservationStatus: string
{
    case PENDING = "PENDING";
    case ACCEPTED = "ACCEPTED";
    case QUARANTINED = "QUARANTINED";
    case CORRECTED = "CORRECTED";
}
