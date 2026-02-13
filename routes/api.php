<?php

use Illuminate\Support\Facades\Route;

Route::apiResource('users', App\Http\Controllers\API\UserController::class);
Route::apiResource('courses', App\Http\Controllers\API\CourseController::class);

// Public routes for recipes
Route::apiResource('recipes', App\Http\Controllers\API\RecipeController::class)->only(['index', 'show']);

// Protected routes for recipes
Route::middleware('auth:sanctum')->apiResource('recipes', App\Http\Controllers\API\RecipeController::class)->except(['index', 'show']);
