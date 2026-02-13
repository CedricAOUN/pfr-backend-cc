<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['content', 'creator_id', 'recipe_id'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }
}
