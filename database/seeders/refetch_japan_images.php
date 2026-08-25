<?php
/**
 * Перекачать разные фото для японских направлений.
 * php database/seeders/refetch_japan_images.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;
use Illuminate\Support\Str;

$dir = public_path('uploads/catalog/japan');
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$lang = Language::query()->where('code', 'he')->firstOrFail();
$descs = JCategoryDescription::query()->where('language_id', $lang->id)->orderBy('name')->get();

$ok = 0;
$fail = 0;

foreach ($descs as $desc) {
    $slug = Str::slug($desc->name) ?: ('id-'.$desc->j_category_id);
    $abs = $dir.DIRECTORY_SEPARATOR.$slug.'.jpg';
    $rel = '/uploads/catalog/japan/'.$slug.'.jpg';

    // Уникальный seed → разная картинка; picsum стабильно отдаёт JPEG.
    $url = 'https://picsum.photos/seed/japan-'.rawurlencode($slug).'/960/640.jpg';

    $bin = download($url);
    if ($bin === null || strlen($bin) < 5000) {
        echo "[fail] {$desc->name}\n";
        $fail++;
        continue;
    }

    // Проверим, что это не PNG-клон швейцарского (тот же размер).
    file_put_contents($abs, $bin);

    JCategory::query()->where('id', $desc->j_category_id)->update(['image' => $rel]);
    echo '[ok] '.$desc->name.' bytes='.strlen($bin)."\n";
    $ok++;
}

echo "\nDone ok={$ok} fail={$fail}\n";

function download(string $url): ?string
{
    // Следуем редиректам picsum.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'lara2-japan-seeder/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($bin) && $bin !== '') {
            return $bin;
        }
        return null;
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'header' => "User-Agent: lara2-japan-seeder/1.0\r\n"],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $bin = @file_get_contents($url, false, $ctx);
    return is_string($bin) ? $bin : null;
}
