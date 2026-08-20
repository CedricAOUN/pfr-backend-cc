<?php

use App\Http\Controllers\API\CheckoutController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\FavoriteController;
use App\Http\Controllers\API\LikeController;
use App\Http\Controllers\API\RecipeController;
use App\Http\Controllers\API\StripeWebhookController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public auth routes
|--------------------------------------------------------------------------
*/

Route::post('users/login', [UserController::class, 'login'])->name('login');
Route::post('users/register', [UserController::class, 'register'])->name('register');
// New accounts should be auto-assigned "regular_user" on registration

/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/
Route::prefix('users')->group(function () {
    // Public: list only, no user details exposed
    Route::get('/', [UserController::class, 'index'])->name('users.index');

    Route::get('me', [UserController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('users.me');

    Route::get('chefs', [UserController::class, 'listChefs'])
        ->name('users.chefs');

    Route::get('{user}', [UserController::class, 'show'])
        ->middleware(['auth:sanctum', 'permission:users.view'])
        ->name('users.show');

    Route::put('edit/{user}', [UserController::class, 'updateInfo'])
        ->middleware('auth:sanctum') // ownership check via UserPolicy
        ->name('users.updateInfo');

    Route::delete('delete/{user}', [UserController::class, 'destroy'])
        ->middleware('auth:sanctum') // ownership/admin check via UserPolicy
        ->name('users.destroy');

    Route::post('logout', [UserController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Recipes
|--------------------------------------------------------------------------
*/
Route::prefix('recipes')->group(function () {
    // Public: list only — should probably return limited fields (title, thumbnail),
    // no full detail, since anonymous visitors have no .view permission at all
    Route::get('/', [RecipeController::class, 'index'])->name('recipes.index');

    Route::get('{recipe}', [RecipeController::class, 'show'])
        ->name('recipes.show');

    Route::post('create', [RecipeController::class, 'store'])
        ->middleware(['auth:sanctum', 'permission:recipes.create'])
        ->name('recipes.store');

    Route::put('edit/{recipe}', [RecipeController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('recipes.update');

    Route::delete('delete/{recipe}', [RecipeController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('recipes.destroy');

    Route::post('{recipe}/like', [LikeController::class, 'toggleLike'])
        ->middleware(['auth:sanctum', 'permission:likes.update'])
        ->name('recipes.toggleLike');

    Route::post('{recipe}/favorite', [FavoriteController::class, 'toggleFavorite'])
        ->middleware(['auth:sanctum', 'permission:favorites.update'])
        ->name('recipes.toggleFavorite');
});

/*
|--------------------------------------------------------------------------
| Comments
|--------------------------------------------------------------------------
*/
Route::prefix('comments')->group(function () {
    Route::post('create', [CommentController::class, 'store'])
        ->middleware(['auth:sanctum', 'permission:comments.create'])
        ->name('comments.store');

    Route::put('edit/{comment}', [CommentController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('comments.update');

    Route::delete('delete/{comment}', [CommentController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('comments.destroy');
});

/*
|--------------------------------------------------------------------------
| Courses
|--------------------------------------------------------------------------
| Still no Route::apiResource() — it duplicated these under different URLs
| with no auth, and clashed with these route names.
*/
Route::prefix('courses')->group(function () {
    // Public: list only, same reasoning as recipes
    Route::get('/', [CourseController::class, 'index'])->name('courses.index');

    Route::get('{course}', [CourseController::class, 'show'])
        ->middleware(['auth:sanctum', 'permission:courses.view'])
        ->name('courses.show');

    Route::post('create', [CourseController::class, 'store'])
        ->middleware(['auth:sanctum', 'permission:courses.create'])
        ->name('courses.store');

    Route::put('edit/{course}', [CourseController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('courses.update');

    Route::delete('delete/{course}', [CourseController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('courses.destroy');

    Route::get('{course}/video', [CourseController::class, 'streamVideo'])
        ->middleware(['auth:sanctum', 'permission:courses.view'])
        // if streaming requires a paid purchase specifically, that's a policy
        // check beyond "can view courses" — flag for follow-up
        ->name('courses.video');
});

/*
|--------------------------------------------------------------------------
| Stripe
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'create'])
        ->name('stripe.createCheckoutSession');

    Route::get('/stripe/order-details/{sessionId}', [CheckoutController::class, 'orderDetails'])
        ->name('stripe.orderDetails');
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

Route::post('/stripe/plan-details', [CheckoutController::class, 'planDetails'])
    ->name('stripe.planDetails');
