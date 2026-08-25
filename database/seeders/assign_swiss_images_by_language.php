<?php
/**
 * Швейцария: разные фото по языкам.
 *   ar → alpine screenshot (основной набор)
 *   he → /uploads/catalog/swiss-he/{slug}.jpg (отдельный набор)
 *
 * php database/seeders/assign_swiss_images_by_language.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\CategoryDescription;
use App\Models\Language;
use Illuminate\Support\Str;

$ar = Language::query()->where('code', 'ar')->first();
$he = Language::query()->where('code', 'he')->first();
if (! $ar || ! $he) {
    fwrite(STDERR, "Need ar and he languages\n");
    exit(1);
}

$swissImage = '/uploads/catalog/screenshot-10_1787132571.png';
$dir = public_path('uploads/catalog/swiss-he');
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$arOk = 0;
$heOk = 0;

$categories = Category::query()
    ->where('manufacturer_id', 1)
    ->with(['descriptions' => fn ($q) => $q->whereIn('language_id', [$ar->id, $he->id])])
    ->orderBy('id')
    ->get();

foreach ($categories as $category) {
    $arDesc = $category->descriptions->firstWhere('language_id', $ar->id);
    if (! $arDesc) {
        continue;
    }

    $arImg = $arDesc->image ?: ($category->image ?: $swissImage);
    $arDesc->image = $arImg;
    $arDesc->save();
    $category->image = $arImg;
    $category->save();
    $arOk++;

    $slug = Str::slug($arDesc->name) ?: ('id-'.$category->id);
    $rel = '/uploads/catalog/swiss-he/'.$slug.'.jpg';
    $abs = public_path('uploads/catalog/swiss-he/'.$slug.'.jpg');

    if (! is_file($abs) || filesize($abs) < 5000) {
        $url = 'https://picsum.photos/seed/swiss-he-'.rawurlencode($slug).'/960/640.jpg';
        $bin = download($url);
        if ($bin) {
            file_put_contents($abs, $bin);
        } elseif (is_file(public_path('uploads/catalog/japan/'.$slug.'.jpg'))) {
            // запасной уникальный файл, если picsum недоступен
            copy(public_path('uploads/catalog/japan/'.$slug.'.jpg'), $abs);
        }
    }

    if (! is_file($abs)) {
        echo "[skip-he-img] {$arDesc->name}\n";
        continue;
    }

    // he → только другое фото (без name/description/step-дублей)
    $payload = [
        'name' => '',
        'slug' => 'he-img-'.$category->id,
        'image' => $rel,
        'description' => null,
        'meta_title' => null,
        'meta_description' => null,
        'meta_keyword' => null,
    ];
    foreach ($arDesc->getAttributes() as $key => $value) {
        if (str_starts_with($key, 'step')) {
            $payload[$key] = null;
        }
    }

    CategoryDescription::updateOrCreate(
        [
            'category_id' => $category->id,
            'language_id' => $he->id,
        ],
        $payload
    );
    $heOk++;
    echo "[ok] {$arDesc->name}\n";
}

echo "\nSwiss ar={$arOk} he={$heOk}\n";

function download(string $url): ?string
{
    if (! function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'lara2-swiss-seeder/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $bin = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($code >= 200 && $code < 300 && is_string($bin) && strlen($bin) > 5000) ? $bin : null;
}
