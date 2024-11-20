<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

/**
 * @api
 */
enum Visibility
{
    case Public;
    case Protected;
    case Private;
}
