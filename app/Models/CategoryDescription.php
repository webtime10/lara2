<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CategoryDescription extends Model
{
    protected $fillable = [
        'category_id',
        'language_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'meta_keyword',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $idealFields = array_values(array_filter(
            (array) config('ideal_region_category_fields.fields', []),
            static fn ($field) => is_string($field) && str_starts_with($field, 'step')
        ));

        $this->fillable = array_values(array_unique(array_merge($this->fillable, $idealFields)));
    }

    /**
     * Уникальный slug по названию в рамках языка: lucerne, lucerne-2, lucerne-3…
     */
    public static function uniqueSlugForLanguage(string $name, int $languageId, ?int $ignoreCategoryId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $n = 2;

        while (true) {
            $query = static::query()
                ->where('language_id', $languageId)
                ->where('slug', $slug);

            if ($ignoreCategoryId) {
                $query->where('category_id', '<>', $ignoreCategoryId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = $base.'-'.$n;
            $n++;
        }
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
