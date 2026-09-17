<?php

use App\Http\Controllers\Admin\ProjectFileManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/file-manager')
    ->name('admin.file-manager.')
    ->middleware(['auth', 'admin', 'super-admin', 'deployment.guard'])
    ->controller(ProjectFileManagerController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('file', 'update')->name('file.update');
        Route::post('file', 'storeFile')->name('file.store');
        Route::post('directory', 'storeDirectory')->name('directory.store');
        Route::post('upload', 'upload')->name('upload');
        Route::post('upload/chunk', 'uploadChunk')->name('upload.chunk');
        Route::post('upload/complete', 'completeChunkedUpload')->name('upload.complete');
        Route::patch('rename', 'rename')->name('rename');
        Route::delete('entry', 'destroy')->name('destroy');
        Route::get('download', 'download')->name('download');
    });
