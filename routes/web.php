<?php

use App\Http\Controllers\ItemRequestPdfController;
use App\Livewire\ItemRequestForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/permintaan-barang', ItemRequestForm::class)
    ->name('item-requests.create')
    ->middleware('throttle:10,1');

Route::get('/permintaan-barang/{itemRequest}/pdf', ItemRequestPdfController::class)
    ->middleware('signed')
    ->name('item-requests.pdf');
