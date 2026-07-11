<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'nick',
        'email',
        'password',
        'phone',
        'avatar',
        'profession',
        'user_document_cpf_cnpj',
        'user_rg',
        'user_postal_code',
        'user_address',
        'user_number',
        'user_complement',
        'user_district',
        'user_city',
        'user_state',
        'birth_date',
        'instagram',
        'facebook',
        'hashtags',
        'has_notifications',
        'has_calendar',
        'has_commission',
        'observations',
        'role',
        'is_active',
        'can_view_customer_phone',
        'can_view_customer_email',
        'can_see_other_professionals_agenda',
        'photo_path', // Adicionado aqui
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'has_notifications' => 'boolean',
        'has_calendar' => 'boolean',
        'has_commission' => 'boolean',
        'can_view_customer_phone' => 'boolean',
        'can_view_customer_email' => 'boolean',
        'can_see_other_professionals_agenda' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class, 'user_id');
    }

    public function anamneses(): HasMany
    {
        return $this->hasMany(CustomerAnamnese::class, 'user_id');
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'professional_services')->withPivot('commission_type', 'commission_value')->withTimestamps();
    }

    public function professionalPayments(): HasMany
    {
        return $this->hasMany(ProfessionalPayment::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'customer_id');
    }

    // Helpers de Roles
    public function isSuperAdmin(): bool { return $this->role === 'superadmin'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isProfessional(): bool { return $this->role === 'professional'; }
    public function isCustomer(): bool { return $this->role === 'customer'; }
}
