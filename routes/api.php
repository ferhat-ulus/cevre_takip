<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Eğer burada başka API rotalarınız varsa kalsın. Yoksa boş bırakabilirsiniz.
});