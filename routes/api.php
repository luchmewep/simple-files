<?php

use Illuminate\Support\Facades\Route;
use Luchavez\SimpleFiles\Http\Controllers\FileController;

Route::apiResource('files', FileController::class);
Route::post('files/{file}/restore', [FileController::class, 'restore'])->name('files.restore');
