<?php

use App\Http\Controllers\Admin\hireController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

////Admin:
//Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
//
//      Route::post('/hire-waiter', [hireController::class, 'hireWaiter']);
//});



