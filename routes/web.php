<?php

use App\Livewire\ItemRequestForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/permintaan-barang', ItemRequestForm::class)
    ->name('item-requests.create')
    ->middleware('throttle:10,1');
