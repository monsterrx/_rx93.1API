<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'jock_id',
        'show_id',
        'name',
        'title',
        'description',
        'year',
        'special',
        'location'
    ];

    public function Jock() {
        return $this->belongsTo(Jock::class);
    }

    public function Show() {
        return $this->belongsTo(Show::class);
    }
}
