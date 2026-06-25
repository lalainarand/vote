<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles; 
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'bureau_vote_id',
        'email_verified_at',
    ];

    // Relations
    public function bureauVote()
    {
        return $this->belongsTo(BureauVote::class);
    }

    public function voteLogs()
    {
        return $this->hasMany(VoteLog::class);
    }

    // Helpers
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isOperator()
    {
        return $this->hasRole('operator');
    }
}