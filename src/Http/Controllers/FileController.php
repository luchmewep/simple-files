<?php

namespace Luchavez\SimpleFiles\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Luchavez\SimpleFiles\Exceptions\FileUploadFailedException;
use Luchavez\SimpleFiles\Http\Requests\File\DeleteFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\IndexFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\ShowFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\StoreFileRequest;
use Luchavez\SimpleFiles\Http\Requests\File\UpdateFileRequest;
use Luchavez\SimpleFiles\Models\File;

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
     * @throws AuthorizationException
     */
    public function index(IndexFileRequest $request): JsonResponse
    {
        if (! ($user = $request->user())) {
            throw new AuthorizationException();
        }

        $data = $user->uploadedFiles();

        if ($request->has('full_data') === true) {
            $data = $data->get();
        } else {
            $data = $data->simplePaginate($request->get('per_page', 15));
        }

        return simpleResponse()
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
     * @throws FileUploadFailedException|AuthenticationException
     */
    public function store(StoreFileRequest $request): JsonResponse
    {
        if (! ($user = $request->user())) {
            throw new AuthenticationException();
        }

        $file = $request->file;

        $is_public = $request->boolean('is_public', true);
        $preserve_name = $request->boolean('preserve_name');

        $model = simpleFiles()->store($file, $user, $is_public, $preserve_name);

        return simpleResponse()
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
     * @throws AuthorizationException
     */
    public function show(ShowFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || $file->owner()->isNot($user)) {
            throw new AuthorizationException();
        }

        return simpleResponse()
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
     * @throws AuthorizationException
     */
    public function update(UpdateFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || $file->owner()->isNot($user)) {
            throw new AuthorizationException();
        }

        $file->touch(); // trigger FileObserver's saving event...

        return simpleResponse()
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
     * @throws AuthorizationException
     */
    public function destroy(DeleteFileRequest $request, File $file): JsonResponse
    {
        if (! ($user = $request->user()) || $file->owner()->isNot($user)) {
            throw new AuthorizationException();
        }

        $file->delete();

        return simpleResponse()
            ->data($file)
            ->message('Successfully archived record.')
            ->success()
            ->generate();
    }
}
