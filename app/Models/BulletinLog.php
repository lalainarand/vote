<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulletinLog extends Model
{
    use HasFactory;

    public $timestamps = false; // on gère created_at manuellement, pas de updated_at

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

    // Renommé bureauVote() -> bureau() pour rester cohérent avec VoteLog::bureau()
    public function bureau()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}