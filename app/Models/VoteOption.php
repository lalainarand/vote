<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteOption extends Model
{
    protected $fillable = ['nom', 'type', 'ordre_affichage'];

    public function voteLogs()    { return $this->hasMany(VoteLog::class); }
    public function bureauResults(){ return $this->hasMany(BureauResult::class); }
}