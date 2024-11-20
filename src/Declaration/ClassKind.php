<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

/**
 * @api
 */
enum ClassKind
{
    case Class_;
    case Interface;
    case Enum;
    case Trait;
}
