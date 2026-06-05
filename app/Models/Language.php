<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'locale',
        'directory',
        'image',
        'sort_order',
        'status',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'status' => 'boolean',
    ];

    public function categoryDescriptions(): HasMany
    {
        return $this->hasMany(CategoryDescription::class);
    }

    public static function getActive()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Языки для форм админки: если все «неактивны», берём любые — иначе поля названия не рисуются.
     */
    public static function forAdminForms()
    {
        $langs = static::getActive();
        if ($langs->isEmpty()) {
            $langs = static::query()->orderBy('sort_order')->orderBy('id')->get();
        }

        return $langs;
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Коды языков для API и плагинов (активные; если нет — все из таблицы).
     *
     * @return list<string>
     */
    public static function activeCodes(): array
    {
        $rows = static::getActive();
        if ($rows->isEmpty()) {
            $rows = static::query()->orderBy('sort_order')->orderBy('id')->get();
        }

        $codes = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) $row->code));
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }
}
