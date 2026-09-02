<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::get('/health', function () {
    return 'OK';
});
