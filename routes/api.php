<?php

use Illuminate\Support\Facades\Route;
use Luchavez\SimpleFiles\Http\Controllers\FileController;

Route::apiResource('files', FileController::class);
