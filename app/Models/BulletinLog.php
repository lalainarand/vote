<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulletinLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'bureau_vote_id',
        'user_id',
        'action',
        'quantity',
        'is_manuel',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_manuel'  => 'boolean',
    ];

    public function bureauVote()
    {
        return $this->belongsTo(BureauVote::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}