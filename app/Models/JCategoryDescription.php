<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JCategoryDescription extends Model
{
    protected $table = 'j_category_descriptions';

    protected $fillable = [
        'j_category_id',
        'language_id',
        'name',
        'slug',
        'image',
        'description',
        'short_description',
        'meta_title',
        'meta_description',
        'meta_keyword',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $idealFields = array_values(array_filter(
            (array) config('japan_ideal_region_category_fields.fields', []),
            static fn ($field) => is_string($field) && str_starts_with($field, 'step')
        ));

        $this->fillable = array_values(array_unique(array_merge($this->fillable, $idealFields)));
    }

    public static function uniqueSlugForLanguage(string $name, int $languageId, ?int $ignoreCategoryId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'j-category';
        }

        $slug = $base;
        $n = 2;

        while (true) {
            $query = static::query()
                ->where('language_id', $languageId)
                ->where('slug', $slug);

            if ($ignoreCategoryId) {
                $query->where('j_category_id', '<>', $ignoreCategoryId);
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
        return $this->belongsTo(JCategory::class, 'j_category_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
