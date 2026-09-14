<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Gives a model a non-sequential public identifier (ULID) in a dedicated
 * `ulid` column, while keeping the BIGINT auto-increment primary key (ADR-0003).
 *
 * Route-model binding resolves by the ULID, never the internal PK.
 */
trait HasPublicId
{
    use HasUlids;

    /**
     * Generate ULIDs for the public id column, not the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
