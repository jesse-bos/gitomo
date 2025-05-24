<?php

namespace Gitomo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gitomo\Gitomo
 */
class Gitomo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gitomo\Gitomo::class;
    }
}
