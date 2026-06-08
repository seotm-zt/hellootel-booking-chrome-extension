<?php

use App\Http\Controllers\Api\ExtensionController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/extension')->group(function () {
    Route::post('login',        [ExtensionController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(ApiTokenAuth::class)->group(function () {
        Route::post('logout',                       [ExtensionController::class, 'logout']);
        Route::post('logout-all',                   [ExtensionController::class, 'logoutAll']);

        // Parser configs are no longer public — they expose target domains/selectors.
        Route::get('parsers',                       [ExtensionController::class, 'parsersList']);
        Route::get('parser-rules',                  [ExtensionController::class, 'parserRules']);

        Route::post('page-report',                  [ExtensionController::class, 'pageReport']);

        Route::get('currencies',                    [ExtensionController::class, 'currencies']);

        Route::get('bookings',                      [ExtensionController::class, 'index']);
        Route::post('bookings',                     [ExtensionController::class, 'store']);
        Route::post('processed-bookings/direct',    [ExtensionController::class, 'storeProcessedDirect']);
        Route::get('processed-bookings',            [ExtensionController::class, 'processedList']);
        Route::patch('processed-bookings/{id}',     [ExtensionController::class, 'updateProcessed']);
        Route::delete('processed-bookings/{id}',    [ExtensionController::class, 'destroyProcessed']);
        Route::patch('bookings/{id}/confirm',       [ExtensionController::class, 'confirm']);
        Route::delete('bookings/{id}',              [ExtensionController::class, 'destroy']);

        Route::get('operators',                     [ExtensionController::class, 'operators']);

        Route::get('hotels',                        [ExtensionController::class, 'hotels']);
        Route::get('hotels/{id}/room-types',        [ExtensionController::class, 'hotelRoomTypes']);
        Route::get('hotels/{id}/vote',              [ExtensionController::class, 'hotelVote']);

        Route::get('countries',                     [ExtensionController::class, 'countries']);
        Route::get('cities',                        [ExtensionController::class, 'cities']);
    });
});
