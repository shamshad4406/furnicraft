<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enquiry extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const TYPES = [
        'general' => 'General question',
        'product' => 'About a product',
        'custom' => 'Custom furniture',
        'bulk' => 'Bulk or trade order',
    ];

    public const STATUSES = [
        'new' => 'New',
        'in_progress' => 'Being handled',
        'quoted' => 'Quoted',
        'converted' => 'Became an order',
        'closed' => 'Closed',
        'spam' => 'Spam',
    ];

    public const SOURCES = [
        'web' => 'Website',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'walk_in' => 'Walk-in',
        'email' => 'Email',
    ];

    /** Nothing further is expected from these. */
    public const CLOSED = ['converted', 'closed', 'spam'];

    protected $fillable = [
        'reference', 'public_token', 'type',
        'customer_id', 'product_id',
        'name', 'email', 'phone', 'city', 'pincode',
        'subject', 'message', 'quantity', 'budget', 'required_by',
        'status', 'source', 'assigned_to',
        'admin_note', 'close_reason', 'ip_address',
        'responded_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'budget' => 'decimal:2',
            'required_by' => 'date',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'status', 'assigned_to'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('enquiries');
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    protected static function booted(): void
    {
        static::creating(function (self $enquiry) {
            $enquiry->public_token ??= Str::lower(Str::random(32));
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->oldest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EnquiryAttachment::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED);
    }

    /**
     * Nobody has answered yet. The figure that matters on a Monday morning.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereNull('responded_at')->whereNotIn('status', self::CLOSED);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED, true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'new' => 'warning',
            'in_progress' => 'info',
            'quoted' => 'primary',
            'converted' => 'success',
            'spam' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * How long it has gone unanswered. Null once somebody has replied.
     */
    public function getWaitingHoursAttribute(): ?int
    {
        if ($this->responded_at || ! $this->isOpen()) {
            return null;
        }

        return (int) $this->created_at->diffInHours(now());
    }
}
