<?php

namespace Modules\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $fillable = [
        'user_id',
        'guest_token',
        'guest_name',
        'guest_email',
        'status',
        'assigned_admin_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id')->orderBy('id');
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Guest visitor';
    }

    public function unreadForAdminCount(): int
    {
        return $this->messages()
            ->where('sender_type', 'customer')
            ->whereNull('read_by_admin_at')
            ->count();
    }

    public function unreadForCustomerCount(): int
    {
        return $this->messages()
            ->where('sender_type', 'admin')
            ->whereNull('read_by_customer_at')
            ->count();
    }
}
