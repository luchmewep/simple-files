<?php

use Luchavez\SimpleFiles\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::apiResource('files', FileController::class);
Route::post('files/{file}/restore', [FileController::class, 'restore'])->name('files.restore');
