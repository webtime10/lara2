<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JCategory extends Model
{
    protected $table = 'j_categories';

    protected $fillable = [
        'image',
        'parent_id',
        'manufacturer_id',
        'tourist_region',
        'top',
        'column',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'top' => 'boolean',
        'status' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function touristRegionLabel(): string
    {
        $key = (string) ($this->tourist_region ?? '');
        if ($key === '') {
            return '';
        }

        $regions = (array) config('japan_tourist_regions.regions', []);

        return (string) ($regions[$key] ?? $key);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(JCategoryDescription::class, 'j_category_id');
    }

    public function descriptionForLanguage(int $languageId): ?JCategoryDescription
    {
        return $this->descriptions->firstWhere('language_id', $languageId);
    }

    /**
     * @return list<int>
     */
    public function descendantIdList(): array
    {
        $this->loadMissing('children');
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIdList());
        }

        return $ids;
    }

    /**
     * @param  array<int>  $excludeIds
     */
    public static function treeForParentSelect(?Language $lang = null, array $excludeIds = []): Collection
    {
        $lang = $lang ?? Language::getDefault();
        if (! $lang) {
            return collect();
        }

        $all = static::with(['descriptions' => fn ($q) => $q->where('language_id', $lang->id)])
            ->orderBy('sort_order')
            ->get();

        $rows = collect();

        $walk = function ($parentId, $depth) use (&$walk, $all, $lang, &$rows, $excludeIds) {
            $items = $all->filter(function ($c) use ($parentId) {
                if ($parentId === null) {
                    return $c->parent_id === null;
                }

                return (int) $c->parent_id === (int) $parentId;
            })->sortBy('sort_order')->values();

            foreach ($items as $cat) {
                if (in_array((int) $cat->id, array_map('intval', $excludeIds), true)) {
                    continue;
                }
                $d = $cat->descriptions->firstWhere('language_id', $lang->id);
                $label = str_repeat('— ', $depth).($d->name ?? ('#'.$cat->id));
                $rows->push(['id' => $cat->id, 'label' => $label]);
                $walk($cat->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $rows;
    }

    public static function rebuildPaths(): void
    {
        DB::table('j_category_paths')->delete();
        $categories = static::all();
        foreach ($categories as $category) {
            $chain = [];
            $current = $category;
            while ($current) {
                array_unshift($chain, $current->id);
                $current = $current->parent_id ? static::find($current->parent_id) : null;
            }
            foreach ($chain as $level => $pathId) {
                DB::table('j_category_paths')->insert([
                    'j_category_id' => $category->id,
                    'path_id' => $pathId,
                    'level' => $level,
                ]);
            }
        }
    }
}
