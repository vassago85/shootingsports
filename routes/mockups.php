<?php

use App\Http\Controllers\AppMockupController;
use Illuminate\Support\Facades\Route;

Route::get('/mockups/apps', AppMockupController::class)->name('mockups.apps');
