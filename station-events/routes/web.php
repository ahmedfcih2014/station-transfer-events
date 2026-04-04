<?php

use App\Http\Controllers\TransferEventsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::controller(TransferEventsController::class)
    ->group(function () {
        Route::get('/stations/{stationId}/summary', 'stationSummary')->name('station.summary');
    });
