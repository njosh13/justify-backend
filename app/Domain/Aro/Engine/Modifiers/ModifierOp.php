<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Modifiers;

enum ModifierOp: string
{
    case Multiply = 'multiply';
    case Add = 'add';
    case Floor = 'floor';
    case Cap = 'cap';
    case FloorRatioOfBase = 'floor_ratio_of_base';
}
