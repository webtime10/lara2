<?php
/**
 * Полные описания Японии в стиле Швейцарии + разные фото по направлениям.
 *
 * Usage:
 *   php database/seeders/fill_japan_descriptions_rich.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;
use Illuminate\Support\Str;

const CONTENT_LANG = 'he';
const IMAGE_DIR = 'uploads/catalog/japan';

$language = Language::query()->where('code', CONTENT_LANG)->first();
if (! $language) {
    fwrite(STDERR, "Language he not found\n");
    exit(1);
}

$defs = japanRichDescriptions();
$imageMap = ensureJapanImages(array_keys($defs));

$filled = 0;
$errors = 0;

foreach ($defs as $nameEn => $def) {
    $desc = JCategoryDescription::query()
        ->where('language_id', $language->id)
        ->where('name', $nameEn)
        ->first();

    if (! $desc) {
        echo "[miss] {$nameEn}\n";
        $errors++;
        continue;
    }

    $category = JCategory::query()->find($desc->j_category_id);
    if (! $category) {
        echo "[miss-cat] {$nameEn}\n";
        $errors++;
        continue;
    }

    $html = buildRichHtml($def);
    $imagePath = $imageMap[$nameEn] ?? ($imageMap['_default'] ?? null);

    $desc->description = $html;
    $desc->save();

    if ($imagePath) {
        $category->image = $imagePath;
        $category->save();
    }

    echo "[ok] {$nameEn}\n";
    $filled++;
}

echo "\nDone. filled={$filled} errors={$errors}\n";
exit($errors > 0 ? 1 : 0);

/**
 * @param  array{title:string, why:list<string>, route:list<string>}  $def
 */
function buildRichHtml(array $def): string
{
    $why = '';
    foreach ($def['why'] as $li) {
        $why .= '<li>'.e($li).'</li>';
    }
    $route = '';
    foreach ($def['route'] as $li) {
        $route .= '<li>'.e($li).'</li>';
    }

    return '<p><strong>'.e($def['title']).'</strong></p>'
        .'<p><strong>Почему именно это направление:</strong></p>'
        .'<ul>'.$why.'</ul>'
        .'<p><strong>Что включить в ваш маршрут:</strong></p>'
        .'<ul>'.$route.'</ul>';
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Скачивает/копирует фото в public/uploads/catalog/japan/{slug}.jpg
 *
 * @param  list<string>  $names
 * @return array<string, string> name => /uploads/...
 */
function ensureJapanImages(array $names): array
{
    $absDir = public_path(IMAGE_DIR);
    if (! is_dir($absDir)) {
        mkdir($absDir, 0775, true);
    }

    $defaultSrc = public_path('uploads/catalog/screenshot-10_1787132571.png');
    $defaultPath = '/uploads/catalog/screenshot-10_1787132571.png';
    $map = ['_default' => $defaultPath];

    $urls = japanImageUrls();

    foreach ($names as $name) {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'place-'.md5($name);
        }
        $rel = '/'.IMAGE_DIR.'/'.$slug.'.jpg';
        $abs = public_path(IMAGE_DIR.'/'.$slug.'.jpg');

        if (! is_file($abs)) {
            $url = $urls[$name] ?? null;
            $ok = false;
            if ($url) {
                $ok = downloadImage($url, $abs);
            }
            if (! $ok && is_file($defaultSrc)) {
                // fallback: copy default as jpg-named file so path unique
                copy($defaultSrc, $abs);
                $ok = is_file($abs);
            }
            if (! $ok) {
                $map[$name] = $defaultPath;
                echo "[img-fallback] {$name}\n";
                continue;
            }
            echo "[img] {$name}\n";
        }

        $map[$name] = $rel;
    }

    return $map;
}

function downloadImage(string $url, string $dest): bool
{
    try {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => "User-Agent: lara2-japan-seeder/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $bin = @file_get_contents($url, false, $ctx);
        if ($bin === false || strlen($bin) < 2000) {
            return false;
        }
        return file_put_contents($dest, $bin) !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Wikimedia Commons thumb URLs (свободные фото направлений).
 *
 * @return array<string, string>
 */
function japanImageUrls(): array
{
    // Стабильные thumb URL; при падении seeder использует fallback-копию.
    return [
        'Sapporo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Sapporo_city_view_from_Mount_Moiwa.jpg/640px-Sapporo_city_view_from_Mount_Moiwa.jpg',
        'Hakodate' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Hakodate_Night_View.jpg/640px-Hakodate_Night_View.jpg',
        'Otaru' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Otaru_Canal.jpg/640px-Otaru_Canal.jpg',
        'Furano' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/Furano_lavender.jpg/640px-Furano_lavender.jpg',
        'Niseko' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2e/Niseko_Annupuri.jpg/640px-Niseko_Annupuri.jpg',
        'Asahikawa' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Asahikawa_station.jpg/640px-Asahikawa_station.jpg',
        'Sendai' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0a/Sendai_City.jpg/640px-Sendai_City.jpg',
        'Aomori' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/Aomori_Bay_Bridge.jpg/640px-Aomori_Bay_Bridge.jpg',
        'Matsushima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6d/Matsushima.jpg/640px-Matsushima.jpg',
        'Aizu-Wakamatsu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Tsuruga_Castle_01.jpg/640px-Tsuruga_Castle_01.jpg',
        'Hirosaki' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Hirosaki_Castle_02.jpg/640px-Hirosaki_Castle_02.jpg',
        'Tokyo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/12/Tokyo_Tower_and_around_Skyscrapers.jpg/640px-Tokyo_Tower_and_around_Skyscrapers.jpg',
        'Yokohama' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Minato_Mirai_21.jpg/640px-Minato_Mirai_21.jpg',
        'Kamakura' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0a/Kamakura_Daibutsu_2019.jpg/640px-Kamakura_Daibutsu_2019.jpg',
        'Hakone' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Lake_Ashi_and_Mount_Fuji.jpg/640px-Lake_Ashi_and_Mount_Fuji.jpg',
        'Nikko' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/Nikko_Toshogu_Yomeimon.jpg/640px-Nikko_Toshogu_Yomeimon.jpg',
        'Kusatsu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/Kusatsu_Onsen_Yubatake.jpg/640px-Kusatsu_Onsen_Yubatake.jpg',
        'Kawagoe' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Kawagoe_Kurazukuri.jpg/640px-Kawagoe_Kurazukuri.jpg',
        'Kanazawa' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Kenrokuen_Garden_Kanazawa.jpg/640px-Kenrokuen_Garden_Kanazawa.jpg',
        'Takayama' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Takayama_old_town.jpg/640px-Takayama_old_town.jpg',
        'Nagano' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Zenkoji_Nagano.jpg/640px-Zenkoji_Nagano.jpg',
        'Matsumoto' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/48/Matsumoto_Castle_05.jpg/640px-Matsumoto_Castle_05.jpg',
        'Karuizawa' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Karuizawa.jpg/640px-Karuizawa.jpg',
        'Shirakawa-go' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ogimachi_village.jpg/640px-Ogimachi_village.jpg',
        'Niigata' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/Niigata_City.jpg/640px-Niigata_City.jpg',
        'Nagoya' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3d/Nagoya_Castle_01.jpg/640px-Nagoya_Castle_01.jpg',
        'Ise' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Ise_Jingu_Geku.jpg/640px-Ise_Jingu_Geku.jpg',
        'Shizuoka' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/Mount_Fuji_from_Shizuoka.jpg/640px-Mount_Fuji_from_Shizuoka.jpg',
        'Hamamatsu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Hamamatsu_Castle.jpg/640px-Hamamatsu_Castle.jpg',
        'Kyoto' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Kiyomizu-dera_in_Kyoto-r.jpg/640px-Kiyomizu-dera_in_Kyoto-r.jpg',
        'Osaka' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c0/Dotonbori_at_night%2C_Osaka.jpg/640px-Dotonbori_at_night%2C_Osaka.jpg',
        'Nara' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0a/Todaiji_Daibutsu.jpg/640px-Todaiji_Daibutsu.jpg',
        'Kobe' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Kobe_Harborland.jpg/640px-Kobe_Harborland.jpg',
        'Himeji' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c2/Himeji_Castle_The_Keep_Tower.jpg/640px-Himeji_Castle_The_Keep_Tower.jpg',
        'Uji' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Byodoin_Phoenix_Hall_Uji_2014.jpg/640px-Byodoin_Phoenix_Hall_Uji_2014.jpg',
        'Koyasan' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5a/Okunoin_cemetery_Koyasan.jpg/640px-Okunoin_cemetery_Koyasan.jpg',
        'Hiroshima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Atomic_Bomb_Dome_Hiroshima.jpg/640px-Atomic_Bomb_Dome_Hiroshima.jpg',
        'Miyajima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/Itsukushima_Torii.jpg/640px-Itsukushima_Torii.jpg',
        'Okayama' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Korakuen_Okayama.jpg/640px-Korakuen_Okayama.jpg',
        'Kurashiki' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/Kurashiki_Bikan.jpg/640px-Kurashiki_Bikan.jpg',
        'Tottori' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Tottori_Sand_Dunes.jpg/640px-Tottori_Sand_Dunes.jpg',
        'Onomichi' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7e/Onomichi.jpg/640px-Onomichi.jpg',
        'Takamatsu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Ritsurin_Garden.jpg/640px-Ritsurin_Garden.jpg',
        'Matsuyama' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Dogo_Onsen_Honkan.jpg/640px-Dogo_Onsen_Honkan.jpg',
        'Tokushima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Naruto_Whirlpools.jpg/640px-Naruto_Whirlpools.jpg',
        'Kochi' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5a/Kochi_Castle.jpg/640px-Kochi_Castle.jpg',
        'Naoshima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8e/Yayoi_Kusama_Pumpkin_Naoshima.jpg/640px-Yayoi_Kusama_Pumpkin_Naoshima.jpg',
        'Fukuoka' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Fukuoka_Tower.jpg/640px-Fukuoka_Tower.jpg',
        'Nagasaki' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Nagasaki_Megane_Bridge.jpg/640px-Nagasaki_Megane_Bridge.jpg',
        'Kumamoto' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Kumamoto_Castle_2011.jpg/640px-Kumamoto_Castle_2011.jpg',
        'Beppu' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6f/Beppu_onsen.jpg/640px-Beppu_onsen.jpg',
        'Kagoshima' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Sakurajima_from_Kagoshima.jpg/640px-Sakurajima_from_Kagoshima.jpg',
        'Yufuin' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7b/Yufuin.jpg/640px-Yufuin.jpg',
        'Naha' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Shuri_Castle.jpg/640px-Shuri_Castle.jpg',
        'Ishigaki' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Ishigaki_Island.jpg/640px-Ishigaki_Island.jpg',
        'Miyako' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Yonaha_Maehama_Beach.jpg/640px-Yonaha_Maehama_Beach.jpg',
        'Nago' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Churaumi_Aquarium.jpg/640px-Churaumi_Aquarium.jpg',
    ];
}

/**
 * @return array<string, array{title:string, why:list<string>, route:list<string>}>
 */
function japanRichDescriptions(): array
{
    return [
        'Sapporo' => [
            'title' => 'Саппоро — столица Хоккайдо',
            'why' => [
                'Крупный хаб острова с удобной логистикой к курортам и природе.',
                'Сильная городская жизнь: фестивали, шопинг и плотная гастрономия.',
                'Зимой — снежный вайб и база к лыжным направлениям Хоккайдо.',
            ],
            'route' => [
                'Проспект Одори и ночной вид с ТВ-башни / горы Мойва.',
                'Рынок морепродуктов и рамен Мисо.',
                'Day-trip в Отару или к снежным курортам.',
            ],
        ],
        'Hakodate' => [
            'title' => 'Хакодате — порт и ночные огни Хоккайдо',
            'why' => [
                'Один из самых красивых ночных видов Японии с горы Хакодате.',
                'Спокойный портовый ритм и сильная гастрономия.',
                'Удобен на 2–4 дня без мегаполисной суеты.',
            ],
            'route' => [
                'Утренний рынок и свежие морепродукты.',
                'Исторический квартал Мотомати и канатная дорога.',
                'Ночной смотровой вид на бухту.',
            ],
        ],
        'Otaru' => [
            'title' => 'Отару — канал и атмосфера Мэйдзи',
            'why' => [
                'Камерный портовый городок рядом с Саппоро.',
                'Красивые склады, канал и сувенирная прогулка.',
                'Идеален как день или короткая ночёвка.',
            ],
            'route' => [
                'Прогулка по каналу Отару.',
                'Стеклодувные мастерские и музыкальные шкатулки.',
                'Ужин с морепродуктами у порта.',
            ],
        ],
        'Furano' => [
            'title' => 'Фурано — лаванда и мягкие горы Хоккайдо',
            'why' => [
                'Летом — цветочные поля и фотопейзаж.',
                'Зимой — спокойный семейный ski-профиль.',
                'Природа важнее ночной городской жизни.',
            ],
            'route' => [
                'Поля лаванды Farm Tomita (в сезон).',
                'Прогулки по долине и смотровые точки.',
                'Зимой — лёгкие трассы и онсэн рядом.',
            ],
        ],
        'Niseko' => [
            'title' => 'Нисеко — powder №1 Хоккайдо',
            'why' => [
                'Один из лучших снежных курортов Азии.',
                'Международная инфраструктура и апре-ски.',
                'Летом тоже красив, но главный смысл — зима.',
            ],
            'route' => [
                'Катание на Annupuri / Hirafu.',
                'Онсэн после склонов.',
                'Вид на гору Йотэй.',
            ],
        ],
        'Asahikawa' => [
            'title' => 'Асахикава — ворота к Дайсецудзану',
            'why' => [
                'Второй город Хоккайдо и удобная база центра острова.',
                'Сильны природа и логистика к нацпарку.',
                'Меньше «открыточного» туризма, больше пространства.',
            ],
            'route' => [
                'Зоопарк Асахияма.',
                'Выезд в Дайсецудзан.',
                'Дегустация местного сакэ и рамена.',
            ],
        ],
        'Sendai' => [
            'title' => 'Сендай — хаб зелёного Тохоку',
            'why' => [
                'Комфортный крупный город с удобным синкансеном.',
                'Сильная локальная кухня и база к Мацусиме.',
                'Подходит как старт маршрута по северу Хонсю.',
            ],
            'route' => [
                'Проспект дзёоздзэндзи и центр города.',
                'Гютан на ужин.',
                'Day-trip в Мацусиму.',
            ],
        ],
        'Aomori' => [
            'title' => 'Аомори — север Хонсю и фестивали',
            'why' => [
                'Характерный северный колорит и природа полуостровов.',
                'Летом — Нэбута, круглый год — яблоки и море.',
                'Для тех, кто хочет Японию вне золотого маршрута.',
            ],
            'route' => [
                'Музей Нэбута / фестиваль в сезон.',
                'Выезд к Симоките или озеру Товада.',
                'Локальные морепродукты и яблочная тема.',
            ],
        ],
        'Matsushima' => [
            'title' => 'Мацусима — один из трёх видов Японии',
            'why' => [
                'Бухта с островками — классический «открыточный» пейзаж.',
                'Спокойный темп и лодочные прогулки.',
                'Отлично комбинируется с Сендаем.',
            ],
            'route' => [
                'Круиз по бухте Мацусима.',
                'Храм Зуйгандзи и смотровые площадки.',
                'Устрицы и ужин у воды.',
            ],
        ],
        'Aizu-Wakamatsu' => [
            'title' => 'Айдзу-Вакамацу — самурайский Тохоку',
            'why' => [
                'Замки, сакэ и сильная историческая атмосфера.',
                'Ворота к озеру Инавасиро и природе Айдзу.',
                'Традиционный колорит без мегаполиса.',
            ],
            'route' => [
                'Замок Цуруга.',
                'Квартал сакэварен.',
                'Озеро Инавасиро или Оути-дзюку.',
            ],
        ],
        'Hirosaki' => [
            'title' => 'Хиросаки — замок и сакура севера',
            'why' => [
                'Один из лучших sakura-спотов Японии.',
                'Провинциальный шарм и парковая атмосфера.',
                'Идеален весной, хорош и в другие сезоны.',
            ],
            'route' => [
                'Замок Хиросаки и парк.',
                'Яблочные сады префектуры.',
                'Прогулка по старому центру.',
            ],
        ],
        'Tokyo' => [
            'title' => 'Токио — мегаполис современной Японии',
            'why' => [
                'Главный транспортный и культурный хаб страны.',
                'Максимум городской жизни, шопинга и кухни мира.',
                'База для day-trip в Камакуру, Хаконэ и Никко.',
            ],
            'route' => [
                'Сибуя / Синдзюку и контраст Асакусы.',
                'Районные прогулки: Янака, Сибуя, Одайба — по вкусу.',
                'Вечерняя гастрономия и смотровые точки.',
            ],
        ],
        'Yokohama' => [
            'title' => 'Йокогама — порт у Токио',
            'why' => [
                'Современный Minato Mirai и портовая набережная.',
                'Чайна-таун и спокойнее токийского ритма.',
                'Удобен как день или короткая база.',
            ],
            'route' => [
                'Minato Mirai и колесо обозрения.',
                'Чайна-таун и порт.',
                'Прогулка по набережной.',
            ],
        ],
        'Kamakura' => [
            'title' => 'Камакура — храмы и океан рядом с Токио',
            'why' => [
                'Большой Будда, храмы и море в одном дне.',
                'Классический day-trip из Токио.',
                'Культура сильнее ночного мегаполиса.',
            ],
            'route' => [
                'Дайбуцу и храм Хасэ-дэра.',
                'Цуругаока Хатимангу.',
                'Пляж Юйгахама.',
            ],
        ],
        'Hakone' => [
            'title' => 'Хаконэ — онсэны и виды на Фудзи',
            'why' => [
                'Классический weekend из Токио с рёканами.',
                'Вулканический пейзаж и озеро Аси.',
                'Сильный романтический и wellness-профиль.',
            ],
            'route' => [
                'Онсэн в рёкане.',
                'Круиз по озеру Аси и канатная дорога.',
                'Открытка Фудзи в ясную погоду.',
            ],
        ],
        'Nikko' => [
            'title' => 'Никко — Тосёгу, лес и водопады',
            'why' => [
                'UNESCO-святилища и мощная природа Канто.',
                'Осень с момдзи — один из лучших сезонов.',
                'Храмы и водопады в одном маршруте.',
            ],
            'route' => [
                'Святилище Тосёгу.',
                'Водопад Кэгон и озеро Чузендзи.',
                'Прогулка по лесным тропам.',
            ],
        ],
        'Kusatsu' => [
            'title' => 'Кусацу — великий онсэн Канто',
            'why' => [
                'Один из трёх великих онсэнов Японии.',
                'Юбатакэ и курортный темп без мегаполиса.',
                'Идеален для отдыха «на водах».',
            ],
            'route' => [
                'Юбатакэ и прогулка по курорту.',
                'Купание в общественных / рёкан-онсэнах.',
                'Вечерний тихий ритм городка.',
            ],
        ],
        'Kawagoe' => [
            'title' => 'Кавагоэ — «маленький Эдо» у Токио',
            'why' => [
                'Склады кура и атмосфера старого Эдо.',
                'Лёгкий day-trip без долгой дороги в Кансай.',
                'Сладкая улица и камерные прогулки.',
            ],
            'route' => [
                'Улица Курадзукури.',
                'Колокольня Токи-но-канэ.',
                'Сладости на Канэцуки-дори.',
            ],
        ],
        'Kanazawa' => [
            'title' => 'Канадзава — сад Кэнрокуэн и крафт Хокурику',
            'why' => [
                'Один из лучших традиционных городов вне Киото.',
                'Сильная кухня Японского моря и ремёсла.',
                'Кварталы гейш и цельная культурная атмосфера.',
            ],
            'route' => [
                'Сад Кэнрокуэн.',
                'Хигаси-тяя и рынок Омитё.',
                'Музеи крафта / район Нагамати.',
            ],
        ],
        'Takayama' => [
            'title' => 'Такаяма — старый город Японских Альп',
            'why' => [
                'Отлично сохранившиеся улицы Санмати.',
                'База к Сиракаве и горному пейзажу.',
                'Медленный традиционный темп.',
            ],
            'route' => [
                'Утренний рынок и Санмати.',
                'Сакэварни старого города.',
                'Day-trip в Сиракава-го.',
            ],
        ],
        'Nagano' => [
            'title' => 'Нагано — храмы, снег и горный хаб',
            'why' => [
                'Ворота к лыжным курортам и долинам Альп.',
                'Храм Дзэнко-дзи как культурный якорь.',
                'Хорош и зимой, и для летнего хайкинга.',
            ],
            'route' => [
                'Храм Дзэнко-дзи.',
                'Выезд на ski-курорт или в Дзигокудани (сезон).',
                'Горные прогулки в тёплый сезон.',
            ],
        ],
        'Matsumoto' => [
            'title' => 'Мацумото — вороний замок и Камикоти',
            'why' => [
                'Один из красивейших замков Японии.',
                'Лучшая база к Камикоти и хайкингу Альп.',
                'Горы важнее шопинга и ночного города.',
            ],
            'route' => [
                'Замок Мацумото.',
                'День в Камикоти.',
                'Прогулка по накамати и музеям.',
            ],
        ],
        'Karuizawa' => [
            'title' => 'Каруидзава — курорт у Асамы',
            'why' => [
                'Комфортный отдых у природы без жёсткого бэккантри.',
                'Прогулки, outlet и свежий воздух.',
                'Удобен семьям и «мягкому» уикенду из Токио.',
            ],
            'route' => [
                'Прогулка по Kyu-Karuizawa.',
                'Ширакабаэ / тропы у курорта.',
                'Outlet и спокойный вечер.',
            ],
        ],
        'Shirakawa-go' => [
            'title' => 'Сиракава-го — деревни гассё-дзукури',
            'why' => [
                'Уникальная UNESCO-архитектура в горах.',
                'Зимой и осенью — особенно фотогеничен.',
                'Чаще day-trip из Такаямы.',
            ],
            'route' => [
                'Деревня Огимати и смотровая площадка.',
                'Интерьер дома гассё-дзукури.',
                'Комбинация с Такаямой.',
            ],
        ],
        'Niigata' => [
            'title' => 'Ниигата — сакэ, рис и снежное побережье',
            'why' => [
                'Гастрономический бренд риса и сакэ.',
                'Зимой — доступ к курортам Японского моря.',
                'Альтернатива «золотому маршруту» Кансая.',
            ],
            'route' => [
                'Дегустация сакэ.',
                'Набережная и морепродукты.',
                'Выезд на снежный курорт в сезон.',
            ],
        ],
        'Nagoya' => [
            'title' => 'Нагоя — хаб между Токио и Осакой',
            'why' => [
                'Один из лучших транспортных узлов страны.',
                'Сильная локальная кухня и замок.',
                'Удобен как база или пересадка на маршруте.',
            ],
            'route' => [
                'Замок Нагоя.',
                'Хицумабуси и мисо-кацу.',
                'Район Сакаэ / Осу.',
            ],
        ],
        'Ise' => [
            'title' => 'Исэ — великое святилище Японии',
            'why' => [
                'Духовный центр синто и паломническая атмосфера.',
                'Традиция сильнее ночного мегаполиса.',
                'Сильный культурный акцент маршрута.',
            ],
            'route' => [
                'Внутреннее и внешнее святилище Исэ.',
                'Улица Охараи-мати.',
                'Локальная кухня паломнического городка.',
            ],
        ],
        'Shizuoka' => [
            'title' => 'Сидзуока — чай и виды на Фудзи',
            'why' => [
                'Чайные плантации и спокойный токайский ритм.',
                'Доступ к побережью Идзу и видам Фудзи.',
                'Природа рядом без ультра-урбана Токио.',
            ],
            'route' => [
                'Чайная дегустация / плантации.',
                'Смотровые на Фудзи.',
                'Выезд к побережью Идзу.',
            ],
        ],
        'Hamamatsu' => [
            'title' => 'Хамамацу — озеро Хамана и Токайдо',
            'why' => [
                'Удобная точка вдоль Токайдо.',
                'Озеро Хамана и локальная кухня унаги.',
                'Больше транзит и локальный колорит, чем must-see хайп.',
            ],
            'route' => [
                'Озеро Хамана.',
                'Унаги на ужин.',
                'Замок / центр города.',
            ],
        ],
        'Kyoto' => [
            'title' => 'Киото — сердце традиционной Японии',
            'why' => [
                'Максимум храмов, садов и исторической атмосферы.',
                'Лучший выбор для культуры и «классической» Японии.',
                'Весна и осень — пиковые сезоны красоты.',
            ],
            'route' => [
                'Фусими Инари и Арасияма / Кинкаку по приоритету.',
                'Гион и вечерняя прогулка.',
                'Чайный опыт или сад при храме.',
            ],
        ],
        'Osaka' => [
            'title' => 'Осака — кухня и ночной Кансай',
            'why' => [
                'Гастрономическая столица с ярким городским драйвом.',
                'Шопинг, Дотонбори и удобный хаб Кансая.',
                'Отлично сочетается с Киото и Нарой.',
            ],
            'route' => [
                'Дотонбори и стрит-фуд.',
                'Замок Осака.',
                'Вечерний Синсэкай / Умэда.',
            ],
        ],
        'Nara' => [
            'title' => 'Нара — первая столица и Тодай-дзи',
            'why' => [
                'Древняя история и Великий Будда.',
                'Парк с оленями — мягкий семейный опыт.',
                'Классический день из Киото или Осаки.',
            ],
            'route' => [
                'Тодай-дзи и парк Нара.',
                'Касуга-тайся.',
                'Прогулка по старым улицам.',
            ],
        ],
        'Kobe' => [
            'title' => 'Кобе — порт, Рокко и говядина',
            'why' => [
                'Европейский колорит порта и виды с гор Рокко.',
                'Знаменитая говядина и сильная гастрономия.',
                'Комфортная база рядом с Осакой.',
            ],
            'route' => [
                'Harborland и набережная.',
                'Ужин с Kobe beef.',
                'Канатная дорога / ночной вид Рокко.',
            ],
        ],
        'Himeji' => [
            'title' => 'Химэдзи — белый замок-цапля',
            'why' => [
                'Самый узнаваемый замок Японии (UNESCO).',
                'Идеален как день из Кансая.',
                'Архитектура важнее пляжа и лыж.',
            ],
            'route' => [
                'Осмотр замка Химэдзи.',
                'Сад Ниси-но-мару.',
                'Короткий центр города.',
            ],
        ],
        'Uji' => [
            'title' => 'Удзи — матча и Бёдо-ин',
            'why' => [
                'Чайная столица между Киото и Нарой.',
                'Камерный культурный день без толп центра Киото.',
                'Матча и храмы в одном маршруте.',
            ],
            'route' => [
                'Бёдо-ин (Фениксовый зал).',
                'Дегустация матча.',
                'Прогулка вдоль реки Удзи.',
            ],
        ],
        'Koyasan' => [
            'title' => 'Коя-сан — монастырская гора Сингон',
            'why' => [
                'Уникальная ночёвка в сёкубо.',
                'Духовная атмосфера и лес Окуно-ин.',
                'Антипод шопинга и ночных мегаполисов.',
            ],
            'route' => [
                'Кладбище Окуно-ин.',
                'Храм Конгобу-дзи / Дандзё Гаран.',
                'Ужин сёдзин-рёри в монастыре.',
            ],
        ],
        'Hiroshima' => [
            'title' => 'Хиросима — город мира и хаб Тюгоку',
            'why' => [
                'Важная историческая точка и мемориальный парк.',
                'Сильная локальная кухня и логистика к Миядзиме.',
                'Удобный хаб западного Хонсю.',
            ],
            'route' => [
                'Мемориальный парк и Atomic Dome.',
                'Окономияки Хиросимы.',
                'Паром на Миядзиму.',
            ],
        ],
        'Miyajima' => [
            'title' => 'Миядзима — торii на воде',
            'why' => [
                'Один из главных символов Японии.',
                'Храм Ицукусима и островная атмосфера.',
                'Сильный must-see рядом с Хиросимой.',
            ],
            'route' => [
                'Плавающие торii и святилище.',
                'Прогулка по острову / подъём на Мисэн.',
                'Момидзи манзю и олени.',
            ],
        ],
        'Okayama' => [
            'title' => 'Окаяма — сад Коракуэн и ворота Курасики',
            'why' => [
                'Один из трёх великих садов Японии.',
                'Комфортный хаб к Курасики и Тюгоку.',
                'Спокойный городской ритм.',
            ],
            'route' => [
                'Сад Коракуэн.',
                'Замок Окаяма.',
                'Day-trip в Курасики.',
            ],
        ],
        'Kurashiki' => [
            'title' => 'Курасики — канал и белые кура',
            'why' => [
                'Камерный исторический квартал Бикан.',
                'Музеи и прогулочная эстетика.',
                'Тише и уютнее крупных must-see.',
            ],
            'route' => [
                'Квартал Бикан и канал.',
                'Музей Охара.',
                'Кофе / галереи в старых складах.',
            ],
        ],
        'Tottori' => [
            'title' => 'Тоттори — песчаные дюны Японии',
            'why' => [
                'Уникальный «пустынный» береговой пейзаж.',
                'Спокойный темп вне золотого маршрута.',
                'Для тех, кто хочет необычный кадр.',
            ],
            'route' => [
                'Песчаные дюны Тоттори.',
                'Верблюд / смотровые (по сезону).',
                'Побережье Японского моря.',
            ],
        ],
        'Onomichi' => [
            'title' => 'Ономити — храмы и старт Симанами',
            'why' => [
                'Винтажный склон с храмами и видом на Внутреннее море.',
                'Лучший старт веломаршрута Симанами Кайдо.',
                'Активный отдых + камерный вайб.',
            ],
            'route' => [
                'Храмовая тропа по склону.',
                'Аренда велосипеда на Симанами.',
                'Закат у пролива.',
            ],
        ],
        'Takamatsu' => [
            'title' => 'Такамацу — хаб Сикоку и сад Рицурин',
            'why' => [
                'Удобный вход на Сикоку.',
                'Сад Рицурин и удон как гастроякорь.',
                'База к Наосиме и островам Сэто.',
            ],
            'route' => [
                'Сад Рицурин.',
                'Удон Санки.',
                'Паром/день на Наосиму.',
            ],
        ],
        'Matsuyama' => [
            'title' => 'Мацуяма — замок и онсэн Дого',
            'why' => [
                'Один из старейших онсэнов Японии.',
                'Замок и курортная история Сикоку.',
                'Термальный отдых + культура.',
            ],
            'route' => [
                'Онсэн Дого.',
                'Замок Мацуяма.',
                'Прогулка по курортному кварталу.',
            ],
        ],
        'Tokushima' => [
            'title' => 'Токусима — Ава-одори и природа Сикоку',
            'why' => [
                'Фестиваль Ава-одори — летний must региона.',
                'Природа восточного Сикоку сильнее урбана.',
                'Для путешественников вне стандартного Кансая.',
            ],
            'route' => [
                'Ава-одори в сезон / музей танца.',
                'Ущелье Наруто / водовороты.',
                'Природные выезды по префектуре.',
            ],
        ],
        'Kochi' => [
            'title' => 'Коти — рынок, замок и тихоокеанский берег',
            'why' => [
                'Провинциальный характер и сильная еда.',
                'Замок и рынок Хиромарти.',
                'Море тихоокеанской стороны Сикоку.',
            ],
            'route' => [
                'Рынок Хиромарти.',
                'Замок Коти.',
                'Побережье / кацуо на ужин.',
            ],
        ],
        'Naoshima' => [
            'title' => 'Наосима — остров современного искусства',
            'why' => [
                'Бенэссе и outdoor-арт во Внутреннем море.',
                'Камерный дизайн-опыт мирового уровня.',
                'Антипод семейных лыж и ночного урбана.',
            ],
            'route' => [
                'Тыква Кусамы и Benesse House.',
                'Музей Титицу / Lee Ufan.',
                'Прогулка на велосипеде по острову.',
            ],
        ],
        'Fukuoka' => [
            'title' => 'Фукуока — живой хаб Кюсю',
            'why' => [
                'Короткий перелёт из Азии и удобный старт острова.',
                'Ятай, рамен Хаката и яркая еда.',
                'Городской комфорт + доступ к Кюсю.',
            ],
            'route' => [
                'Ятай вечером.',
                'Храмы / набережная каналов.',
                'Рамен Хаката и шопинг Тэндзин.',
            ],
        ],
        'Nagasaki' => [
            'title' => 'Нагасаки — порт с многослойной историей',
            'why' => [
                'Уникальная история открытого порта.',
                'Холмистый центр и сильные виды.',
                'Кухня и культура южного Кюсю.',
            ],
            'route' => [
                'Дэдзима / Голландия квартал.',
                'Мир / мемориальные места.',
                'Вид с горы Инаса.',
            ],
        ],
        'Kumamoto' => [
            'title' => 'Кумамото — замок и ворота к Асо',
            'why' => [
                'Мощный замок и локальная еда.',
                'Лучшая база к кальдере Асо.',
                'Вулканический пейзаж Кюсю рядом.',
            ],
            'route' => [
                'Замок Кумамото.',
                'Выезд к Асо.',
                'Басуси / локальная кухня.',
            ],
        ],
        'Beppu' => [
            'title' => 'Беппу — столица онсэнов Кюсю',
            'why' => [
                'Тысячи источников и «адские» котлы.',
                'Максимум термального туризма.',
                'Курортный ритм вместо мегаполисного шопинга.',
            ],
            'route' => [
                'Адские источники (Jigoku Meguri).',
                'Купание в онсэнах.',
                'Вид на залив Беппу.',
            ],
        ],
        'Kagoshima' => [
            'title' => 'Кагосима — Сакурадзима и юг Кюсю',
            'why' => [
                'Вид на действующий вулкан Сакурадзима.',
                'Южный характер, еда и море.',
                'Сильный природный кадр Кюсю.',
            ],
            'route' => [
                'Смотровые на Сакурадзиму.',
                'Паром к вулкану.',
                'Местная кухня и онсэн.',
            ],
        ],
        'Yufuin' => [
            'title' => 'Юфуин — стильный онсэн у Юфу-дакэ',
            'why' => [
                'Камерный люкс и прогулочная долина.',
                'Онсэны без суеты крупного Беппу.',
                'Сильный wellness и фотопейзаж.',
            ],
            'route' => [
                'Прогулка по Юноцубо-кайдо.',
                'Озеро Кинрин.',
                'Рёкан с онсэном.',
            ],
        ],
        'Naha' => [
            'title' => 'Наха — столица Окинавы и хаб островов',
            'why' => [
                'Старт архипелага и субтропический ритм.',
                'Еда, Кокусай-дори и доступ к островам.',
                'Море важнее снега и клёновых гор.',
            ],
            'route' => [
                'Замок Сюри.',
                'Кокусай-дори и рынок.',
                'Пляж / выезд на север острова.',
            ],
        ],
        'Ishigaki' => [
            'title' => 'Исигаки — база Яэямы',
            'why' => [
                'Кораллы, джунгли и острова архипелага.',
                'Лучший снорклинг/дайвинг сценарий Окинавы.',
                'Природа важнее храмов Кансая.',
            ],
            'route' => [
                'Снорклинг / кабан.',
                'Выезд на Такетоми или Ириомоте.',
                'Закат на пляже.',
            ],
        ],
        'Miyako' => [
            'title' => 'Мияко — бирюзовая вода Окинавы',
            'why' => [
                'Эталон пляжного релакса.',
                'Цвет воды — главная причина поездки.',
                'Для пары и спокойного beach-сценария.',
            ],
            'route' => [
                'Пляж Ёнаха Маэхама.',
                'Снорклинг у рифов.',
                'Медленный островной день.',
            ],
        ],
        'Nago' => [
            'title' => 'Наго — север Окинавы для семьи',
            'why' => [
                'Аквариум Тюрауми и спокойные бухты.',
                'Сильный семейный профиль.',
                'Море и природа сильнее ночного города.',
            ],
            'route' => [
                'Аквариум Тюрауми.',
                'Пляжи севера острова.',
                'Прогулка по набережной Наго.',
            ],
        ],
    ];
}
