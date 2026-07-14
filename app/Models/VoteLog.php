<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteLog extends Model
{
    public $timestamps = false; 
    protected $fillable = [
        'bureau_vote_id',
        'vote_option_id',
        'user_id',
        'action',
        'quantity',
        'is_procuration',
        'is_restored',
        'created_at',
    ];
    public function bureau()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }
    public function voteOption()
    {
        return $this->belongsTo(VoteOption::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
