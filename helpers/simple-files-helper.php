<?php

/**
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */

use Luchavez\SimpleFiles\Services\SimpleFiles;

if (! function_exists('simpleFiles')) {
    /**
     * @return SimpleFiles
     */
    function simpleFiles(): SimpleFiles
    {
        return resolve('simple-files');
    }
}

if (! function_exists('simple_files')) {
    /**
     * @return SimpleFiles
     */
    function simple_files(): SimpleFiles
    {
        return simpleFiles();
    }
}
