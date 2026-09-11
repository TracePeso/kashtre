<?php

namespace App\Domain\Time\Enums;

enum PolicyScopeType: string
{
    case PLATFORM = "PLATFORM";
    case TENANT = "TENANT";
    case BRANCH = "BRANCH";
    case FACILITY = "FACILITY";
    case CLIENT_SPACE = "CLIENT_SPACE";
    case USER = "USER";
    case DEVICE = "DEVICE";
}
