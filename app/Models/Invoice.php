<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'supplier_name',
        'supplier_tax_number',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_total',
        'total',
        'currency',
        'original_file_path',
        'original_file_paths',
        'processed_file_path',
        'mime_type',
        'raw_extracted_text',
        'extracted_data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'invoice_date'         => 'date',
        'due_date'             => 'date',
        'extracted_data'       => 'array',
        'original_file_paths'  => 'array',
        'subtotal'             => 'decimal:2',
        'tax_total'            => 'decimal:2',
        'total'                => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_order');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'uploaded'   => 'Enviado',
            'processing' => 'A processar',
            'extracted'  => 'Extraído',
            'reviewed'   => 'Revisto',
            'confirmed'  => 'Confirmado',
            'failed'     => 'Falhou',
            default      => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'uploaded'   => 'bg-blue-500/20 text-blue-300',
            'processing' => 'bg-amber-500/20 text-amber-300',
            'extracted'  => 'bg-indigo-500/20 text-indigo-300',
            'reviewed'   => 'bg-purple-500/20 text-purple-300',
            'confirmed'  => 'bg-emerald-500/20 text-emerald-300',
            'failed'     => 'bg-red-500/20 text-red-300',
            default      => 'bg-slate-500/20 text-slate-300',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['extracted', 'reviewed', 'failed']);
    }
}
