<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HuespedController;

Route::get('/', function () {
    return view('welcome');
});

