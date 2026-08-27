<?php

namespace App\Models;

use Database\Factories\ProfitEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'profit_type_id', 'date', 'document_number', 'amount'])]
class ProfitEntry extends Model
{
    /** @use HasFactory<ProfitEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProfitType, $this>
     */
    public function profitType(): BelongsTo
    {
        return $this->belongsTo(ProfitType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
