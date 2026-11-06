<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employee_documents';

    protected $fillable = [
        'vendor_id',
        'user_id',
        'title',
        'category',
        'file_path',
        'file_size_bytes',
        'mime_type',
        'expires_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDocumentTitleAttribute(): string
    {
        return $this->title;
    }

    public function getDocumentTypeAttribute(): string
    {
        return $this->category;
    }

    public function getFileSizeAttribute(): int
    {
        return (int) $this->file_size_bytes;
    }
}
