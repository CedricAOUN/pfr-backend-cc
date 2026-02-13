<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['title', 'description', 'video_url', 'expert_id'];
    public function expert()
    {
        return $this->belongsTo(User::class, 'id');
    }
}
