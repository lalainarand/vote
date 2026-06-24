<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BureauStatistic extends Model
{
    protected $fillable = [
        'bureau_vote_id', 'registered_voters', 'voters',
        'ballots_found', 'pv_source', 'pv_note'
    ];

    public function bureau() { return $this->belongsTo(BureauVote::class, 'bureau_vote_id'); }
}