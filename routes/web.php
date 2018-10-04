<?php

Route::group(['middleware' => 'web', 'prefix' => 'queue', 'namespace' => 'Jano\Modules\Queue\Http\Controllers'], function()
{
    Route::get('/', 'QueueController@index');
});
