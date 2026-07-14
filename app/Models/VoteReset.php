<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteReset extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'bureau_vote_id',
        'user_id',
        'snapshot',
        'reason',
        'restored_at',
        'created_at',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'created_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function bureau()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
