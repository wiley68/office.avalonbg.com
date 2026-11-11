<?php

namespace App\Models;

use App\Enums\ProfitTypeKind;
use Database\Factories\ProfitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind'])]
class ProfitType extends Model
{
    /** @use HasFactory<ProfitTypeFactory> */
    use HasFactory;

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasMany<ProfitEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ProfitEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProfitTypeKind::class,
        ];
    }
}
