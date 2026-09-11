<?php

namespace App\Domain\Units\Enums;

enum ComponentOperator: string
{
    case NUMERATOR = 'NUMERATOR';
    case DENOMINATOR = 'DENOMINATOR';
    case MULTIPLY = 'MULTIPLY';
    case SCALAR = 'SCALAR';
    case ANNOTATE = 'ANNOTATE';
}
