<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

/**
 * @api
 */
enum TypeKind
{
    case Native;
    case Tentative;
    case Annotated;
    case Resolved;
}
