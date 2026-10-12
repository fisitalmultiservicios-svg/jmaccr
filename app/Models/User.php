<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Notifications\Outbox;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'auth_version' => 'integer',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        Outbox::direct('auth.reset', $this->id, $this->email, ['url' => $url, 'name' => $this->name]);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if (request()->attributes->get('rbac.user_id') === $this->id) {
            return in_array($permission, request()->attributes->get('rbac.permissions', []), true);
        }

        return $this->roles()->whereHas('permissions', fn ($q) => $q->where('name', $permission))->exists();
    }

    public function requiresMfa(): bool
    {
        return $this->roles()->whereHas('permissions', fn ($q) => $q->whereNotIn('name', ['pages.view', 'posts.view', 'products.view', 'categories.view', 'brands.view', 'menus.view', 'theme.view', 'media.view', 'seo.view']))->exists() || $this->mfa_confirmed_at !== null;
    }
}
