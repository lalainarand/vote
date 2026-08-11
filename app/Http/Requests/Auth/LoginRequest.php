<?php

namespace App\Http\Requests\Auth;

use App\Models\AuthorizedDevice;
use App\Models\DeviceLoginAttempt;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Identifiants corrects, mais le compte a été désactivé par un admin :
        // on refuse la session malgré tout et on l'explique clairement.
        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez un administrateur pour le réactiver.',
            ]);
        }

        // Compte actif : dernier verrou pour les OPÉRATEURS uniquement — l'appareil
        // doit être l'une des tablettes autorisées (26 au total). Les admins ne
        // sont pas soumis à cette liste (ils gèrent le système depuis leur propre
        // poste, et c'est justement depuis l'admin qu'on enregistre les tablettes :
        // les y soumettre aussi créerait un blocage total au premier démarrage).
        $user = Auth::user();

        if ($user->isOperator()) {
            $device = AuthorizedDevice::resolveFromRequest($this);

            if (! $device) {
                DeviceLoginAttempt::logFromRequest($this, $user);
                Auth::logout();

                throw ValidationException::withMessages([
                    'email' => "Cet appareil n'est pas autorisé à accéder au système de comptage. Contactez un administrateur.",
                ]);
            }

            $device->touchUsage($this, $user);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
