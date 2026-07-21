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
        'step1_goroda',
        'step1_goroda_description',
        'step1_ozera',
        'step1_ozera_description',
        'step1_vodopady',
        'step1_vodopady_description',
        'step1_gory',
        'step1_gory_description',
        'step2_restorany',
        'step2_restorany_description',
        'step2_razvlecheniya',
        'step2_razvlecheniya_description',
        'step2_otdyh',
        'step2_otdyh_description',
        'step2_gulyat',
        'step2_gulyat_description',
        'step3_aktivnyi',
        'step3_aktivnyi_description',
        'step3_srednii',
        'step3_srednii_description',
        'step3_spokoinyi',
        'step3_spokoinyi_description',
        'step4_kultura',
        'step4_kultura_description',
        'step4_eda',
        'step4_eda_description',
        'step4_komfort',
        'step4_komfort_description',
        'step4_priroda',
        'step4_priroda_description',
        'step5_odin',
        'step5_odin_description',
        'step5_druzya',
        'step5_druzya_description',
        'step5_semya',
        'step5_semya_description',
        'step5_para',
        'step5_para_description',
        'step6_vkusnaya_eda',
        'step6_vkusnaya_eda_description',
        'step6_krasivye_vidy',
        'step6_krasivye_vidy_description',
        'step6_vpechatleniya',
        'step6_vpechatleniya_description',
        'step6_otdohnut',
        'step6_otdohnut_description',
        'step7_parki',
        'step7_parki_description',
        'step7_muzei',
        'step7_muzei_description',
        'step7_progulki',
        'step7_progulki_description',
        'step7_shopping_razvlecheniya',
        'step7_shopping_razvlecheniya_description',
    ];

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
