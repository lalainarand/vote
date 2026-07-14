<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BulletinImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'bureau_vote_id',
        'user_id',
        'path',
        'filename',
        'taken_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function bureauVote()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // Accesseur pratique : $image->url au lieu de Storage::url($image->path)
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
