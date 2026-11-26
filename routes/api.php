<?php

use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ResponseHelper::respond(
    code: 200,
    data: ['status' => 'ok'],
    message: 'API is available.',
));
