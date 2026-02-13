<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'title',
        'description',
        'ingredients',
        'instructions',
        'is_premium',
        'creator_id',
        'image_url',
    ];
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    public function comments()
    {
        return $this->hasMany(Comment::class, 'recipe_id');
    }
    public function likes()
    {
        return $this->hasMany(Like::class, 'recipe_id');
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'recipe_id');
    }
}
