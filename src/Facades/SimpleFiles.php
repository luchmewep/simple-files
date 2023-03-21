<?php

namespace Luchavez\SimpleFiles\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class SimpleFiles
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 *
 * @see \Luchavez\SimpleFiles\Services\SimpleFiles
 */
class SimpleFiles extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'simple-files';
    }
}
