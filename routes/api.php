<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


// Define the callback route for products
Route::post('products/{product}/callback', [ProductController::class, 'handleCallback'])
    ->name('api.products.callback'); 

Route::post('pull-qty','ProductController@pullQty');
    
Route::post('/etims/callback', 'EtimsController@handleCallback');