<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'individual' => 'Individual',
        'business' => 'Business',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'onboarding' => 'Onboarding',
        'inactive' => 'Inactive',
    ];

    public const BILLING_CYCLES = [
        'none' => 'None (Ad-hoc)',
        'monthly' => 'Monthly Retainer',
        'quarterly' => 'Quarterly Retainer',
    ];

    protected $fillable = [
        'client_code',
        'user_id',
        'assigned_ca_id',
        'category',
        'name',
        'email',
        'mobile',
        'address',
        'city',
        'state',
        'pincode',
        'pan',
        'gstin',
        'tan',
        'cin',
        'business_type',
        'industry',
        'registration_date',
        'status',
        'is_gst_registered',
        'is_itr_applicable',
        'is_tds_applicable',
        'is_recurring_retainer',
        'retainer_amount',
        'billing_cycle',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'is_gst_registered' => 'boolean',
            'is_itr_applicable' => 'boolean',
            'is_tds_applicable' => 'boolean',
            'is_recurring_retainer' => 'boolean',
            'retainer_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedCa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_ca_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_staff')
            ->withPivot('role_label')
            ->withTimestamps();
    }

    public function gstReturns(): HasMany
    {
        return $this->hasMany(GstReturn::class);
    }

    public function itrFilings(): HasMany
    {
        return $this->hasMany(ItrFiling::class);
    }

    public function tdsReturns(): HasMany
    {
        return $this->hasMany(TdsReturn::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(ComplianceDeadline::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function outstandingBalance(): float
    {
        return (float) $this->invoices()
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->sum('balance_amount');
    }
}
