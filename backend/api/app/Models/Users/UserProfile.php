<?php

namespace App\Models\Users;

use App\Models\BlotterReport\BlotterReport;
use App\Models\Certificates\CertificateRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class UserProfile extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'street_address',
        'address_line',
        'mobile_number',
        'mobile_verified_at',
        'date_of_birth',
        'is_active'
    ];

    /**
     * Guard the primary key so it can be read but not mass-assigned.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes enables hasOne relationship.
     *
     * @var list<string>
     */
    public function user()
    {
        return $this->hasOne(User::class, 'user_profile_id');
    }

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class, 'user_profile_id');
    }
    public function blotterReport()
    {
        return $this->hasMany(BlotterReport::class, 'user_profile_id');
    }

    public function governmentIdentity(): HasOne
    {
        return $this->hasOne(GovernmentIdentity::class, 'user_profile_id');
    }

    public function routeNotificationForSms(): ?string
    {
        if (!$this->mobile_number) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->mobile_number);
        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '63')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+63' . ltrim($digits, '0');
        }

        return '+' . $digits;
    }

    public function routeNotificationForTwilio(): ?string
    {
        return $this->routeNotificationForSms();
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'date_of_birth' => 'date'
    ];
}
