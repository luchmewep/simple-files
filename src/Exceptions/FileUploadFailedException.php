<?php

namespace Luchavez\SimpleFiles\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class FileUploadFailedException
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileUploadFailedException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function render(Request $request): JsonResponse
    {
        return customResponse()
            ->data([])
            ->message('Failed to upload file.')
            ->failed(409)
            ->generate();
    }
}
