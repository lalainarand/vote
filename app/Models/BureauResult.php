<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BureauResult extends Model
{
    protected $fillable = [
        'bureau_vote_id',
        'vote_option_id',
        'count',
        'source',
        'entered_by',
        'entered_at'
    ];

    public function bureau()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }
    public function voteOption()
    {
        return $this->belongsTo(VoteOption::class);
    }
    public function enteredByUser()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
