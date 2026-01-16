<?php

use App\Http\Controllers\SlackController;
use Illuminate\Support\Facades\Route;

Route::post('/slack/events', [SlackController::class, 'events'])->middleware('slack.signature');
Route::post('/slack/interactivity', [SlackController::class, 'interactivity'])->middleware('slack.signature');
