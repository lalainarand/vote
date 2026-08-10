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
        'password_plain',
        'bureau_vote_id',
        'email_verified_at',
        'is_active',
        'is_approved',
    ];

    // Ni le hash ni la copie en clair du mot de passe ne doivent jamais partir dans
    // une sérialisation implicite (props Inertia d'une relation chargée en entier,
    // toArray()/toJson()...). password_plain n'est exposé qu'explicitement, à la
    // main, dans les contrôleurs admin qui en ont légitimement besoin.
    protected $hidden = [
        'password',
        'password_plain',
        'remember_token',
    ];

    protected $casts = [
        // Chiffrement réversible (APP_KEY), pas un hash : permet de relire le mot
        // de passe en clair pour l'afficher/l'exporter côté admin.
        'password_plain' => 'encrypted',
        'is_active'      => 'boolean',
        'is_approved'    => 'boolean',
    ];

    /**
     * Génère un mot de passe aléatoire ne contenant que des chiffres, des lettres
     * (majuscules/minuscules) et les symboles # * . " @ - (aucun autre caractère).
     */
    public static function generatePassword(int $length = 12): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789#*."@-';
        $max = strlen($chars) - 1;

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

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