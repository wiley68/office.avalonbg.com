<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['name'])]
#[Hidden(['access_encryption_key'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Group $group): void {
            $group->access_encryption_key = Crypt::encryptString(base64_encode(random_bytes(32)));
        });
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<CalendarEvent, $this> */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /** @return HasMany<Access, $this> */
    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }

    /** @return HasMany<ProfitEntry, $this> */
    public function profitEntries(): HasMany
    {
        return $this->hasMany(ProfitEntry::class);
    }

    /** @return HasMany<ProfitType, $this> */
    public function profitTypes(): HasMany
    {
        return $this->hasMany(ProfitType::class);
    }

    /** @return HasMany<ProjectEmailLink, $this> */
    public function projectEmailLinks(): HasMany
    {
        return $this->hasMany(ProjectEmailLink::class);
    }
}
