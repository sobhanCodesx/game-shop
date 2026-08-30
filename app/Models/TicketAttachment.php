<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $fillable = ['ticket_reply_id', 'user_id', 'path', 'original_name', 'mime_type', 'type', 'size'];

    public function reply(): BelongsTo { return $this->belongsTo(TicketReply::class, 'ticket_reply_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
