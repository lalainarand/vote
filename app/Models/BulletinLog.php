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
        'is_procuration',
        'is_reset',
        'is_restored',
        'created_at',
    ];
    protected $casts = [
        'created_at'      => 'datetime',
        'is_manuel'       => 'boolean',
        'is_procuration'  => 'boolean',
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
    public static function currentCountForBureau(int $bureauId, ?bool $isProcuration = null): int
    {
        $base = static::where('bureau_vote_id', $bureauId);
        if ($isProcuration !== null) {
            $base->where('is_procuration', $isProcuration);
        }

        $plus = (clone $base)->where('action', '+1')->sum('quantity');
        $minus = (clone $base)->where('action', '-1')->sum('quantity');

        return $plus - $minus;
    }

    /**
     * Compteur système national de bulletins dépouillés, tous bureaux confondus.
     * Optionnellement filtré sur is_procuration (true = procuration, false = individuel).
     */
    public static function currentCountNational(?bool $isProcuration = null, ?iterable $bureauIds = null): int
    {
        $base = static::query();
        if ($bureauIds !== null) {
            $base->whereIn('bureau_vote_id', $bureauIds);
        }
        if ($isProcuration !== null) {
            $base->where('is_procuration', $isProcuration);
        }

        $plus = (clone $base)->where('action', '+1')->sum('quantity');
        $minus = (clone $base)->where('action', '-1')->sum('quantity');

        return $plus - $minus;
    }
}
