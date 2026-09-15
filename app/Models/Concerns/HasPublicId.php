<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * A stable public identifier, separate from the numeric primary key.
 *
 * Sequential ids leak two things in a URL: how many records exist, and
 * whether a neighbouring record exists. `/super-admin/societies/6` says
 * there are at least six societies and invites a try at 5. A ULID says
 * nothing and cannot be guessed.
 *
 * This deliberately does NOT replace the primary key. `societies.id` stays
 * the integer that 40 tables join against; `public_id` is only what the
 * outside world sees. Keeping both means no foreign key has to change.
 *
 * ULID rather than UUIDv4 because it sorts by creation time, so an index on
 * it stays compact and `order by public_id` is chronological - the same
 * reason Laravel added Str::ulid().
 *
 * Usage: `use HasPublicId;` on the model, and add a 26-character unique
 * `public_id` column. Route-model binding then resolves by it automatically.
 *
 * NOTE: never add `public_id` to $fillable. It is assigned here on create
 * and must not be settable from request input.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getPublicIdColumn()})) {
                $model->{$model->getPublicIdColumn()} = (string) Str::ulid();
            }
        });
    }

    public function getPublicIdColumn(): string
    {
        return 'public_id';
    }

    /**
     * Bind routes by the public identifier rather than the primary key.
     *
     * Every `route('...', $model)` call and every implicit `{model}` binding
     * picks this up, so links stop exposing the sequence without any caller
     * having to change.
     */
    public function getRouteKeyName(): string
    {
        return $this->getPublicIdColumn();
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<static>  $query */
    public function scopeWherePublicId($query, string $publicId)
    {
        return $query->where($this->getPublicIdColumn(), $publicId);
    }
}
