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
        'is_restored',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_manuel'  => 'boolean',
    ];

    public function bureau()
    {
        return $this->belongsTo(BureauVote::class, 'bureau_vote_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Compteur système courant de bulletins dépouillés pour un bureau.
     * Réutilisable partout (CountingController, PvController, etc.)
     * au lieu de dupliquer le calcul +1/-1 dans chaque contrôleur.
     */
    public static function currentCountForBureau(int $bureauId): int
    {
        $plus = static::where('bureau_vote_id', $bureauId)
            ->where('action', '+1')
            ->sum('quantity');
        $minus = static::where('bureau_vote_id', $bureauId)
            ->where('action', '-1')
            ->sum('quantity');

        return $plus - $minus;
    }
}