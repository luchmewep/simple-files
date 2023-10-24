<?php

namespace Luchavez\SimpleFiles\Observers;

use Luchavez\SimpleFiles\Models\File;

/**
 * Class FileObserver
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileObserver
{
    /**
     * Handle the File "saving" event.
     *
     * @param  File  $file
     * @return void
     */
    public function saving(File $file): void
    {
        simpleFiles()->generateUrl($file);
    }

    /**
     * Handle the File "deleting" event.
     *
     * @param  File  $file
     * @return bool
     */
    public function deleting(File $file): bool
    {
        return simpleFiles()->delete($file->path, $file->is_public);
    }
}
