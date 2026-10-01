<?php

use App\Http\Controllers\Admin\MeController;
use Illuminate\Support\Facades\Route;

/*
| API back-office — /api/admin/v1. Personnel uniquement, MFA obligatoire ;
| chaque route est en plus protégée par une permission (spatie/laravel-permission).
*/

Route::get('me', MeController::class);
