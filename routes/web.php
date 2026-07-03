<?php

use App\Models\Trove;
use App\Models\Collection;
use App\Livewire\BrowseAll;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::group([
    'middleware' => 'set.locale',
], function () {

    Route::get('/', static function () {
        return redirect('/home');
    });

    Route::get('/home', function () {
        return view('home');
    })->name('home');

    Route::get('/resources/preview/{slug}', function ($slug) {
        
        if (!auth()->check()) {
            return;
        }

        // Show the working version — the shadow draft when one exists, else the live/working row.
        $resource = Trove::withDrafts()->workingVersions()->where('slug', $slug)->firstOrFail();
        return view('trove', ['resource' => $resource, 'hasCollections' => $resource->collections()->where('public', 1)->exists()]);
    });

    Route::get('/resources/{troveKey}', function ($troveKey) {
        $resource = Trove::findBySlugOrRedirect($troveKey);
    
        if (! $resource) {
            abort(404);
        }
    
        // If slug doesn't match, redirect to correct slug
        if ($resource->slug !== $troveKey) {
            return redirect()->route('resources.show', ['troveKey' => $resource->slug], 301);
        }
    
        return view('trove', ['resource' => $resource, 'hasCollections' => $resource->collections()->where('public', 1)->exists()]);
    })->name('resources.show');

    Route::get('/browse-all', BrowseAll::class)->name('browse-all');

    Route::get('/collections/{id}', function ($id) {
        $collection = Collection::where('id', $id)->firstOrFail();
        return view('collection', compact('collection'));
    });
    
    Route::get('/download-all-zip/{slug}', function ($slug) {
        $trove = Trove::where('slug', $slug)->firstOrFail();
        return $trove->downloadAllFilesAsZip();
    })->name('trove.download.zip');

});