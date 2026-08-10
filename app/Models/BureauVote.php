<?php

namespace App\Models;

use App\Models\BulletinImage;
use App\Models\VoteReset;
use Illuminate\Database\Eloquent\Model;

class BureauVote extends Model
{
    protected $table = 'bureaux_vote';

    protected $fillable = ['code', 'nom', 'status', 'is_procuration', 'admin_validated_by', 'admin_validated_at'];

    protected $casts = [
        'is_procuration'     => 'boolean',
        'admin_validated_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Admin qui a confirmé (validé de son côté) ce bureau, après validation opérateur.
     * Purement déclaratif : ne modifie aucun résultat, sert uniquement à l'affichage.
     */
    public function adminValidator()
    {
        return $this->belongsTo(User::class, 'admin_validated_by');
    }
    public function voteLogs()
    {
        return $this->hasMany(VoteLog::class);
    }
    public function bureauResults()
    {
        return $this->hasMany(BureauResult::class);
    }
    public function statistics()
    {
        return $this->hasOne(BureauStatistic::class);
    }
    public function bulletinImages()
    {
        return $this->hasMany(BulletinImage::class, 'bureau_vote_id');
    }
    public function voteResets()
    {
        return $this->hasMany(VoteReset::class, 'bureau_vote_id');
    }
}
