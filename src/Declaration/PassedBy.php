<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

/**
 * @api
 */
enum PassedBy
{
    case Value;
    case Reference;
    case ValueOrReference;
}
