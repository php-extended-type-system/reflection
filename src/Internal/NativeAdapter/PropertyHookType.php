<?php

declare(strict_types=1);

if (enum_exists(PropertyHookType::class)) {
    return;
}

enum PropertyHookType: string
{
    case Get = 'get';
    case Set = 'set';
}
