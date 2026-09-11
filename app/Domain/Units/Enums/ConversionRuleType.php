<?php

namespace App\Domain\Units\Enums;

enum ConversionRuleType: string
{
    case SCALE = 'SCALE';
    case AFFINE = 'AFFINE';
    case NAMED_NONLINEAR = 'NAMED_NONLINEAR';
    case SUBSTANCE_SPECIFIC = 'SUBSTANCE_SPECIFIC';
    case PRODUCT_SPECIFIC = 'PRODUCT_SPECIFIC';
    case PROCEDURE_SPECIFIC = 'PROCEDURE_SPECIFIC';
    case NO_CONVERSION = 'NO_CONVERSION';
}
