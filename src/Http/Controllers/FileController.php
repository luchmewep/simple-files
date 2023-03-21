<?php

namespace Luchavez\SimpleFiles\Http\Controllers;

use App\Http\Controllers\Controller;
use Luchavez\SimpleFiles\Events\File\FileArchivedEvent;
use Luchavez\SimpleFiles\Events\File\FileCollectedEvent;
use Luchavez\SimpleFiles\Events\File\FileCreatedEvent;
// Model
use Luchavez\SimpleFiles\Events\File\FileRestoredEvent;
// Requests
use Luchavez\SimpleFiles\Events\File\FileShownEvent;
use Luchavez\SimpleFiles\Events\File\FileUpdatedEvent;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Http\Requests\File\DeleteFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\IndexFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\RestoreFileRequest;
// Events
use Luchavez\SimpleFiles\Http\Requests\File\ShowFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\StoreFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\UpdateFileRequest;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\StarterKit\Exceptions\UnauthorizedException;
use Illuminate\Http\JsonResponse;

/**
 * Class FileController
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileController extends Controller
{
    /**
     * File List
     *
     * @group File Management
     *
     * @param  IndexFileRequest  $request
     * @return JsonResponse
     *
     * @throws UnauthorizedException
     */
    public function index(IndexFileRequest $request): JsonResponse
    {
        if (! ($user = $request->user())) {
            throw new UnauthorizedException();
        }

        $data = $user->uploadedFiles();

        if ($request->has('full_data') === true) {
            $data = $data->get();
        } else {
            $data = $data->simplePaginate($request->get('per_page', 15));
        }

        event(new FileCollectedEvent($data));

        return customResponse()
            ->data($data)
            ->message('Successfully collected record.')
            ->success()
            ->generate();
    }

    /**
     * Store File
     *
     * @group File Management
     *
     * @param  StoreFileRequest  $request
     * @return JsonResponse
     *
     * @throws UnauthorizedException|FileUploadFailedException
     */
    public function store(StoreFileRequest $request): JsonResponse
    {
        if (! ($user = $request->user())) {
            throw new UnauthorizedException();
        }

        $file = $request->file;

        $is_public = $request->boolean('is_public', true);
        $preserve_name = $request->boolean('preserve_name');

        $model = simpleFiles()->store($file, $user, $is_public, $preserve_name);

        event(new FileCreatedEvent($model));

        return customResponse()
            ->data($model)
            ->message('Successfully uploaded file.')
            ->success()
            ->generate();
    }

    /**
     * Show File
     *
     * @group File Management
     *
     * @param  ShowFileRequest  $request
     * @param  File  $file
     * @return JsonResponse
     *
     * @throws UnauthorizedException
     */
    public function show(ShowFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || (($owner = $file->user) && $owner->isNot($user))) {
            throw new UnauthorizedException();
        }

        event(new FileShownEvent($file));

        return customResponse()
            ->data($file)
            ->message('Successfully collected record.')
            ->success()
            ->generate();
    }

    /**
     * Update File
     *
     * @group File Management
     *
     * @param  UpdateFileRequest  $request
     * @param  File  $file
     * @return JsonResponse
     *
     * @throws UnauthorizedException
     */
    public function update(UpdateFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || (($owner = $file->user) && $owner->isNot($user))) {
            throw new UnauthorizedException();
        }

        $file->touch(); // trigger FileObserver's saving event...

        event(new FileUpdatedEvent($file));

        return customResponse()
            ->data($file)
            ->message('Successfully updated record.')
            ->success()
            ->generate();
    }

    /**
     * Archive File
     *
     * @group File Management
     *
     * @param  DeleteFileRequest  $request
     * @param  File  $file
     * @return JsonResponse
     *
     * @throws UnauthorizedException
     */
    public function destroy(DeleteFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || (($owner = $file->user) && $owner->isNot($user))) {
            throw new UnauthorizedException();
        }

        $file->delete();

        event(new FileArchivedEvent($file));

        return customResponse()
            ->data($file)
            ->message('Successfully archived record.')
            ->success()
            ->generate();
    }

    /**
     * Restore File
     *
     * @group File Management
     *
     * @param  RestoreFileRequest  $request
     * @param $file
     * @return JsonResponse
     *
     * @throws UnauthorizedException
     */
    public function restore(RestoreFileRequest $request, $file): JsonResponse
    {
        $file = File::withTrashed()->where('uuid', $file)->firstOrFail();

        if (! ($user = $request->user()) || (($owner = $file->user) && $owner->isNot($user))) {
            throw new UnauthorizedException();
        }

        $file->restore();

        event(new FileRestoredEvent($file));

        return customResponse()
            ->data($file)
            ->message('Successfully restored record.')
            ->success()
            ->generate();
    }
}
