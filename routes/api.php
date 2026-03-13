<?php

use Illuminate\Support\Facades\Route;

Route::apiResource('courses', App\Http\Controllers\API\CourseController::class);

Route::prefix('users')->group(function () {
  Route::get('/', [App\Http\Controllers\API\UserController::class, 'index'])->name('users.index');
  Route::get('{user}', [App\Http\Controllers\API\UserController::class, 'show'])->name('users.show');
  Route::put('edit/{user}', [App\Http\Controllers\API\UserController::class, 'updateInfo'])->middleware('auth:sanctum')->name('users.updateInfo');
  Route::delete('delete/{user}', [App\Http\Controllers\API\UserController::class, 'destroy'])->middleware('auth:sanctum')->name('users.destroy');
  Route::post('login', [App\Http\Controllers\API\UserController::class, 'login'])->name('login');
  Route::post('register', [App\Http\Controllers\API\UserController::class, 'register'])->name('register');
});

Route::prefix('recipes')->group(function () {
  Route::get('/', [App\Http\Controllers\API\RecipeController::class, 'index'])->name('recipes.index');
  Route::get('{recipe}', [App\Http\Controllers\API\RecipeController::class, 'show'])->name('recipes.show');
  Route::post('create', [App\Http\Controllers\API\RecipeController::class, 'store'])->middleware('auth:sanctum')->name('recipes.store');
  Route::put('edit/{recipe}', [App\Http\Controllers\API\RecipeController::class, 'update'])->middleware('auth:sanctum')->name('recipes.update');
  Route::delete('delete/{recipe}', [App\Http\Controllers\API\RecipeController::class, 'destroy'])->middleware('auth:sanctum')->name('recipes.destroy');
  Route::post('{recipe}/like', [App\Http\Controllers\API\LikeController::class, 'toggleLike'])->middleware('auth:sanctum')->name('recipes.toggleLike');
});

Route::prefix('courses')->group(function () {
  Route::post('create', [App\Http\Controllers\API\CourseController::class, 'store'])->middleware('auth:sanctum')->name('courses.store');
  Route::put('edit/{course}', [App\Http\Controllers\API\CourseController::class, 'update'])->middleware('auth:sanctum')->name('courses.update');
  Route::delete('delete/{course}', [App\Http\Controllers\API\CourseController::class, 'destroy'])->middleware('auth:sanctum')->name('courses.destroy');
  Route::get('{course}/video', [App\Http\Controllers\API\CourseController::class, 'streamVideo'])->middleware('auth:sanctum')->name('courses.video');
});

// Stripe: session creation requires auth; webhook is public but signature-verified
Route::post('stripe/checkout', [App\Http\Controllers\API\StripeController::class, 'createCheckoutSession'])->middleware('auth:sanctum')->name('stripe.checkout');
Route::post('stripe/webhook', [App\Http\Controllers\API\StripeController::class, 'handleWebhook'])->name('stripe.webhook');
