<?php

declare(strict_types=1);

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * Keeps a normalised `search_text` column in sync for trigram search (ADR-0013).
 *
 * @mixin Model
 */
trait HasSearchText
{
    public static function bootHasSearchText(): void
    {
        static::saving(function (self $model): void {
            $model->setAttribute('search_text', SearchText::from($model->searchableValues()));
        });
    }

    /**
     * @return list<mixed>
     */
    abstract public function searchableValues(): array;
}
