<?php

namespace App\Domain\Units\Enums;

enum UnitStatus: string
{
    case DRAFT = 'DRAFT';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'ACTIVE';
    case DEPRECATED = 'DEPRECATED';
    case RETIRED = 'RETIRED';
    case REJECTED = 'REJECTED';
}
