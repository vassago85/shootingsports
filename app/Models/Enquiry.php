<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'type', 'name', 'email', 'phone', 'subject', 'body',
    'about_type', 'about_id', 'status', 'ip_address', 'user_agent', 'read_at',
])]
class Enquiry extends Model
{
    protected function casts(): array
    {
        return [
            'type' => EnquiryType::class,
            'status' => EnquiryStatus::class,
            'read_at' => 'immutable_datetime',
        ];
    }

    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    public function markRead(): void
    {
        if ($this->status === EnquiryStatus::New) {
            $this->forceFill([
                'status' => EnquiryStatus::Read,
                'read_at' => now(),
            ])->save();
        }
    }
}
