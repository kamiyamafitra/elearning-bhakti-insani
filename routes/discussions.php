<?php

use App\Http\Controllers\DiscussionController;
use Illuminate\Support\Facades\Route;

Route::get('/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
Route::get('/discussions/create', [DiscussionController::class, 'create'])->name('discussions.create');
Route::post('/discussions', [DiscussionController::class, 'store'])->middleware('throttle:10,1')->name('discussions.store');
Route::get('/discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy'])->name('discussions.destroy');
Route::get('/discussions/{discussion}/image', [DiscussionController::class, 'image'])->name('discussions.image');
Route::patch('/discussions/{discussion}/pin', [DiscussionController::class, 'togglePin'])->name('discussions.pin');
Route::patch('/discussions/{discussion}/close', [DiscussionController::class, 'toggleClosed'])->name('discussions.close');
Route::post('/discussions/{discussion}/replies', [DiscussionController::class, 'storeReply'])->middleware('throttle:30,1')->name('discussions.replies.store');
Route::get('/discussions/{discussion}/replies/{reply}/image', [DiscussionController::class, 'replyImage'])->name('discussions.replies.image');
Route::patch('/discussions/{discussion}/replies/{reply}/best', [DiscussionController::class, 'toggleBestReply'])->name('discussions.replies.best');
Route::delete('/discussions/{discussion}/replies/{reply}', [DiscussionController::class, 'destroyReply'])->name('discussions.replies.destroy');
