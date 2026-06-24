<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BureauVote extends Model
{
    protected $table = 'bureaux_vote';
    
    protected $fillable = ['code', 'nom', 'status'];

    public function users()          { return $this->hasMany(User::class); }
    public function voteLogs()       { return $this->hasMany(VoteLog::class); }
    public function bureauResults()  { return $this->hasMany(BureauResult::class); }
    public function statistics()     { return $this->hasOne(BureauStatistic::class); }
}