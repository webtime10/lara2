<?php
/**
 * Разные фото по языкам:
 *   he (Япония) → /uploads/catalog/japan/...
 *   ar (арабский) → /uploads/catalog/screenshot-10_... (набор Швейцарии)
 *   + category_descriptions.image для Швейцарии (ar)
 *
 * php database/seeders/assign_images_by_language.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\CategoryDescription;
use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;
use Illuminate\Support\Str;

$he = Language::query()->where('code', 'he')->first();
$ar = Language::query()->where('code', 'ar')->first();

if (! $he || ! $ar) {
    fwrite(STDERR, "Need languages he and ar\n");
    exit(1);
}

$swissImage = '/uploads/catalog/screenshot-10_1787132571.png';
$japanDir = public_path('uploads/catalog/japan');

$heOk = 0;
$arOk = 0;

$heDescs = JCategoryDescription::query()->where('language_id', $he->id)->with('category')->get();

foreach ($heDescs as $heDesc) {
    $category = $heDesc->category;
    if (! $category) {
        continue;
    }

    $slug = Str::slug($heDesc->name) ?: ('id-'.$category->id);
    $japanRel = '/uploads/catalog/japan/'.$slug.'.jpg';
    $japanAbs = public_path('uploads/catalog/japan/'.$slug.'.jpg');

    // he → японское фото
    if (is_file($japanAbs)) {
        $heDesc->image = $japanRel;
    } elseif ($category->image) {
        $heDesc->image = $category->image;
    }
    $heDesc->save();
    $category->image = $heDesc->image;
    $category->save();
    $heOk++;

    // ar → только другое фото (без name/description/step-дублей)
    $payload = [
        'name' => '',
        'slug' => 'ar-img-'.$category->id,
        'image' => $swissImage,
        'description' => null,
        'short_description' => null,
        'meta_title' => null,
        'meta_description' => null,
        'meta_keyword' => null,
    ];
    foreach ($heDesc->getAttributes() as $key => $value) {
        if (str_starts_with($key, 'step')) {
            $payload[$key] = null;
        }
    }

    JCategoryDescription::updateOrCreate(
        [
            'j_category_id' => $category->id,
            'language_id' => $ar->id,
        ],
        $payload
    );
    $arOk++;
}

// Швейцария: ar descriptions получают alpine-фото
$swissOk = 0;
$swissCats = Category::query()->where('manufacturer_id', 1)->get();
foreach ($swissCats as $cat) {
    $img = $cat->image ?: $swissImage;
    CategoryDescription::query()
        ->where('category_id', $cat->id)
        ->where('language_id', $ar->id)
        ->update(['image' => $img]);
    $swissOk++;
}

echo "Japan he images: {$heOk}\n";
echo "Japan ar images (swiss set): {$arOk}\n";
echo "Swiss ar images: {$swissOk}\n";
echo "Done.\n";
