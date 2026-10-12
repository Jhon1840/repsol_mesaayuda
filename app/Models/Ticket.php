<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $attributes = ['status' => 'pending', 'priority' => 'medium', 'version' => 1];

    protected $guarded = ['id'];

    protected $appends = ['code'];

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'priority' => TicketPriority::class,
            'assigned_at' => 'datetime', 'started_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function getCodeAttribute(): string
    {
        return 'HD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }
}
