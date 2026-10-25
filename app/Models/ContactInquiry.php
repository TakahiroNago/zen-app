<?php

namespace App\Models;

use Database\Factories\ContactInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'subject', 'message', 'mail_sent', 'resolved_at'])]
class ContactInquiry extends Model
{
    /** @use HasFactory<ContactInquiryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mail_sent' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
