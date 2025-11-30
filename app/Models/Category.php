<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'dark_mode_icon'
    ];

    public function Article() {
        return $this->hasMany(Article::class);
    }
}
