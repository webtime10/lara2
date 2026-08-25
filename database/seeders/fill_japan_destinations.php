<?php
/**
 * Япония: язык иврит + 57 туристических направлений (j_categories).
 *
 * Запуск:
 *   php database/seeders/fill_japan_destinations.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\DB;

const JAPAN_MANUFACTURER_ID = 2;

$hebrew = Language::query()->firstOrCreate(
    ['code' => 'he'],
    [
        'name' => 'עברית',
        'locale' => 'he-il',
        'directory' => 'he',
        'image' => null,
        'sort_order' => 20,
        'status' => true,
        'is_default' => false,
        'is_active' => true,
    ]
);

$hebrew->fill([
    'name' => 'עברית',
    'locale' => 'he-il',
    'directory' => 'he',
    'status' => true,
    'is_active' => true,
])->save();

$manufacturer = Manufacturer::query()->find(JAPAN_MANUFACTURER_ID);
if (! $manufacturer) {
    fwrite(STDERR, "Manufacturer id=".JAPAN_MANUFACTURER_ID." (Япония) not found.\n");
    exit(1);
}

$destinations = japanDestinations();

echo "Hebrew language id={$hebrew->id} code={$hebrew->code}\n";
echo 'Destinations: '.count($destinations)."\n";

$created = 0;
$updated = 0;
$sort = 10;

DB::transaction(function () use ($destinations, $hebrew, &$created, &$updated, &$sort) {
    foreach ($destinations as $row) {
        $name = $row['name'];
        $region = $row['region'];
        $description = $row['description'];

        $existingDesc = JCategoryDescription::query()
            ->where('language_id', $hebrew->id)
            ->where('name', $name)
            ->first();

        if ($existingDesc) {
            $category = JCategory::query()->find($existingDesc->j_category_id);
        } else {
            $category = null;
        }

        if (! $category) {
            $category = JCategory::create([
                'parent_id' => null,
                'manufacturer_id' => JAPAN_MANUFACTURER_ID,
                'tourist_region' => $region,
                'image' => null,
                'top' => false,
                'column' => 0,
                'sort_order' => $sort,
                'status' => true,
            ]);
            $created++;
        } else {
            $category->update([
                'manufacturer_id' => JAPAN_MANUFACTURER_ID,
                'tourist_region' => $region,
                'sort_order' => $sort,
                'status' => true,
            ]);
            $updated++;
        }

        $slug = JCategoryDescription::uniqueSlugForLanguage($name, (int) $hebrew->id, (int) $category->id);

        JCategoryDescription::updateOrCreate(
            [
                'j_category_id' => $category->id,
                'language_id' => $hebrew->id,
            ],
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'short_description' => null,
                'meta_title' => null,
                'meta_description' => null,
                'meta_keyword' => null,
            ]
        );

        // Контент только на вкладке he — остальные языки очищаем.
        JCategoryDescription::query()
            ->where('j_category_id', $category->id)
            ->where('language_id', '<>', $hebrew->id)
            ->delete();

        $sort += 10;
    }

    JCategory::rebuildPaths();
});

echo "Done. created={$created} updated={$updated}\n";

/**
 * @return list<array{name:string,region:string,description:string}>
 */
function japanDestinations(): array
{
    $p = static function (string $title, string $lead, string $strong): string {
        return '<p><strong>'.$title.'</strong> — '.$lead.'</p><p>'.$strong.'</p>';
    };

    return [
        // Hokkaido (6)
        ['name' => 'Sapporo', 'region' => 'hokkaido', 'description' => $p('Sapporo', 'столица Хоккайдо и удобный хаб острова.', 'Сильны городская жизнь, фестивали, еда и зимний отдых; море и онсэны — выездом.')],
        ['name' => 'Hakodate', 'region' => 'hokkaido', 'description' => $p('Hakodate', 'портовый город с видовой горой и историческим кварталом.', 'Сильны гастрономия, ночные виды и спокойный темп; лыжи и мегаполис — не про него.')],
        ['name' => 'Otaru', 'region' => 'hokkaido', 'description' => $p('Otaru', 'канал, склады Мэйдзи и атмосфера портового городка рядом с Саппоро.', 'Сильны прогулки, сувениры и морепродукты; база на несколько дней слабее Саппоро.')],
        ['name' => 'Furano', 'region' => 'hokkaido', 'description' => $p('Furano', 'долины лаванды летом и мягкий горнолыжный профиль зимой.', 'Сильны природа, фотосезоны и семейный зимний отдых; ночная городская жизнь минимальна.')],
        ['name' => 'Niseko', 'region' => 'hokkaido', 'description' => $p('Niseko', 'один из главных powder-курортов Японии.', 'Сильны лыжи/сноуборд и апре-ски; храмы Кансая и пляжи Окинавы — это другие сценарии.')],
        ['name' => 'Asahikawa', 'region' => 'hokkaido', 'description' => $p('Asahikawa', 'второй город Хоккайдо и ворота к Дайсецудзану.', 'Сильны природа, зоопарк и логистика по острову; пляжный и мегаполисный вайб слабые.')],

        // Tohoku (5)
        ['name' => 'Sendai', 'region' => 'tohoku', 'description' => $p('Sendai', 'крупный хаб Тохоку с зелёными проспектами.', 'Сильны еда, городской комфорт и выезды к Мацусиме; «классический» Киото здесь не ищите.')],
        ['name' => 'Aomori', 'region' => 'tohoku', 'description' => $p('Aomori', 'север Хонсю: яблоки, нэбута и доступ к полуострову Симокита.', 'Сильны природа и локальная культура; шопинг уровня Токио и онсэн-курорты Кюсю — слабее.')],
        ['name' => 'Matsushima', 'region' => 'tohoku', 'description' => $p('Matsushima', 'бухта с островками — один из «трёх видов» Японии.', 'Сильны пейзаж, лодки и спокойный темп; ночная жизнь и лыжи — не главные козыри.')],
        ['name' => 'Aizu-Wakamatsu', 'region' => 'tohoku', 'description' => $p('Aizu-Wakamatsu', 'самурайская история и ворота к озеру Инавасиро.', 'Сильны замки, сакэ и традиционный колорит; океан и мегаполис далеко.')],
        ['name' => 'Hirosaki', 'region' => 'tohoku', 'description' => $p('Hirosaki', 'замок и знаменитая сакура на севере.', 'Сильны сезон цветения, парки и провинциальный шарм; круглосуточный урбан — не здесь.')],

        // Kanto (7)
        ['name' => 'Tokyo', 'region' => 'kanto', 'description' => $p('Tokyo', 'главный мегаполис Японии и узел всех маршрутов.', 'Сильны город, шопинг, еда и ночная жизнь; «тихие» онсэны и дикая природа — выездом.')],
        ['name' => 'Yokohama', 'region' => 'kanto', 'description' => $p('Yokohama', 'порт, Чайна-таун и видовой Minato Mirai рядом с Токио.', 'Сильны городская прогулка и гастрономия; высокогорье и Окинава — другой формат.')],
        ['name' => 'Kamakura', 'region' => 'kanto', 'description' => $p('Kamakura', 'храмы, Большой Будда и океан в одном дне из Токио.', 'Сильны культура и море; лыжи и ночной мегаполис — не её сильные стороны.')],
        ['name' => 'Hakone', 'region' => 'kanto', 'description' => $p('Hakone', 'онсэны, виды на Фудзи и классический weekend из Токио.', 'Сильны горячие источники, пейзаж и романтика; шопинг и ночные клубы — слабо.')],
        ['name' => 'Nikko', 'region' => 'kanto', 'description' => $p('Nikko', 'святилища Тосёгу, водопады и лесной пейзаж.', 'Сильны храмы, природа и UNESCO-атмосфера; пляжи и урбан-вайб — не про Никко.')],
        ['name' => 'Kusatsu', 'region' => 'kanto', 'description' => $p('Kusatsu', 'один из самых известных онсэн-городов Канто.', 'Сильны горячие источники и курортный темп; мегаполис и серфинг — в других направлениях.')],
        ['name' => 'Kawagoe', 'region' => 'kanto', 'description' => $p('Kawagoe', '«маленький Эдо» со складами кура и сладкой улицей.', 'Сильны традиционная атмосфера и лёгкий день из Токио; горы и океан — рядом, но не внутри.')],

        // Hokuriku Shinetsu (7)
        ['name' => 'Kanazawa', 'region' => 'hokuriku_shinetsu', 'description' => $p('Kanazawa', 'сад Кэнрокуэн, кварталы гейш и сильная кухня Хокурику.', 'Сильны культура, крафт и еда; коралловые пляжи и powder Niseko — другие сценарии.')],
        ['name' => 'Takayama', 'region' => 'hokuriku_shinetsu', 'description' => $p('Takayama', 'старый город в Японских Альпах и база к Сиракаве.', 'Сильны традиция, горы и медленный темп; ночной мегаполис отсутствует.')],
        ['name' => 'Nagano', 'region' => 'hokuriku_shinetsu', 'description' => $p('Nagano', 'хаб к дзэнко-дзи, снежным курортам и долинам.', 'Сильны лыжи, храмы и природа; океанические пляжи — далеко.')],
        ['name' => 'Matsumoto', 'region' => 'hokuriku_shinetsu', 'description' => $p('Matsumoto', '«вороний» замок и ворота к Камикоти.', 'Сильны хайкинг, замок и альпийский доступ; шопинг Осаки — не здесь.')],
        ['name' => 'Karuizawa', 'region' => 'hokuriku_shinetsu', 'description' => $p('Karuizawa', 'курортный городок у Асамы: прогулки, outlet и свежий воздух.', 'Сильны комфортный отдых у природы; жёсткий бэккантри и пляжи — слабо.')],
        ['name' => 'Shirakawa-go', 'region' => 'hokuriku_shinetsu', 'description' => $p('Shirakawa-go', 'деревни гассё-дзукури в списке UNESCO.', 'Сильны уникальная архитектура и фотопейзаж; ночёвка-база на неделю обычно в Такаяме.')],
        ['name' => 'Niigata', 'region' => 'hokuriku_shinetsu', 'description' => $p('Niigata', 'сакэ, рис и доступ к снежным курортам Японского моря.', 'Сильны еда, зима и побережье; «золотой маршрут» Киото—Токио — другой трек.')],

        // Tokai (4)
        ['name' => 'Nagoya', 'region' => 'tokai', 'description' => $p('Nagoya', 'промышленный и транспортный хаб между Токио и Осакой.', 'Сильны логистика, замок и локальная кухня; романтика Киото и пляжи Окинавы — слабее.')],
        ['name' => 'Ise', 'region' => 'tokai', 'description' => $p('Ise', 'великое святилище Исэ и паломническая атмосфера.', 'Сильны храмы и традиция; ночной мегаполис и лыжи — не про Исэ.')],
        ['name' => 'Shizuoka', 'region' => 'tokai', 'description' => $p('Shizuoka', 'чай, виды на Фудзи и доступ к побережью Идзу.', 'Сильны природа и спокойный темп; ультра-урбан Токио — выездом.')],
        ['name' => 'Hamamatsu', 'region' => 'tokai', 'description' => $p('Hamamatsu', 'озеро Хамана, унаги и база вдоль Токайдо.', 'Сильны локальная еда и удобный транзит; UNESCO-храмы Кансая — в стороне.')],

        // Kansai (7)
        ['name' => 'Kyoto', 'region' => 'kansai', 'description' => $p('Kyoto', 'главный центр храмов, садов и традиционной Японии.', 'Сильны культура, история и атмосфера; порох Niseko и кораллы Окинавы — другие цели.')],
        ['name' => 'Osaka', 'region' => 'kansai', 'description' => $p('Osaka', 'гастрономическая столица с яркой ночной жизнью.', 'Сильны еда, шопинг и городской драйв; тихие онсэны Хаконэ — выездом.')],
        ['name' => 'Nara', 'region' => 'kansai', 'description' => $p('Nara', 'первая столица, олени и Великий Будда Тодай-дзи.', 'Сильны древняя история и храмы; мегаполисный ритм Осаки рядом, но мягче.')],
        ['name' => 'Kobe', 'region' => 'kansai', 'description' => $p('Kobe', 'порт, горы Рокко и знаменитая говядина.', 'Сильны еда, виды и европейский колорит; дикие вулканы Кюсю — не здесь.')],
        ['name' => 'Himeji', 'region' => 'kansai', 'description' => $p('Himeji', 'белый замок-цапля, икона японской архитектуры.', 'Сильны замок и лёгкий день из Кансая; пляжный и лыжный профили слабые.')],
        ['name' => 'Uji', 'region' => 'kansai', 'description' => $p('Uji', 'чайный город у Бёдо-ин между Киото и Нарой.', 'Сильны матча, храмы и камерность; ночная Осака — рядом по JR.')],
        ['name' => 'Koyasan', 'region' => 'kansai', 'description' => $p('Koyasan', 'монастырская гора Сингон с ночёвкой в сёкубо.', 'Сильны духовная атмосфера и лес; шопинг и клубы — антипод.')],

        // Chugoku (6)
        ['name' => 'Hiroshima', 'region' => 'chugoku', 'description' => $p('Hiroshima', 'город мира, окономаки и хаб к Миядзиме.', 'Сильны история, еда и логистика Тюгоку; снег Хоккайдо — далеко.')],
        ['name' => 'Miyajima', 'region' => 'chugoku', 'description' => $p('Miyajima', 'торii на воде у Ицукусимы — символ Японии.', 'Сильны храм, пейзаж и островная атмосфера; урбан Токио — не её жанр.')],
        ['name' => 'Okayama', 'region' => 'chugoku', 'description' => $p('Okayama', 'сад Коракуэн и ворота к Курасики.', 'Сильны сады, комфорт и транзит; порошковый снег и серфинг — слабо.')],
        ['name' => 'Kurashiki', 'region' => 'chugoku', 'description' => $p('Kurashiki', 'канал, белые кура и музейный квартал.', 'Сильны прогулочная эстетика и камерность; мегаполисная ночь — нет.')],
        ['name' => 'Tottori', 'region' => 'chugoku', 'description' => $p('Tottori', 'песчаные дюны на берегу Японского моря.', 'Сильны необычный пейзаж и спокойный темп; храмовый Кансай — выездом.')],
        ['name' => 'Onomichi', 'region' => 'chugoku', 'description' => $p('Onomichi', 'склоны храмов и старт веломаршрута Симанами.', 'Сильны хайкинг/велосипед и винтажный вайб; люкс-онсэн курорты — другие места.')],

        // Shikoku (5)
        ['name' => 'Takamatsu', 'region' => 'shikoku', 'description' => $p('Takamatsu', 'хаб Сикоку, сад Рицурин и удон.', 'Сильны еда, сады и выезды к Наосиме; снег и мегаполис — слабо.')],
        ['name' => 'Matsuyama', 'region' => 'shikoku', 'description' => $p('Matsuyama', 'замок и онсэн Дого — один из старейших в Японии.', 'Сильны горячие источники и история; пляжи Окинавы — другой климат.')],
        ['name' => 'Tokushima', 'region' => 'shikoku', 'description' => $p('Tokushima', 'танец Ава-одори и природа восточного Сикоку.', 'Сильны фестивали и природа; шопинг уровня Токио отсутствует.')],
        ['name' => 'Kochi', 'region' => 'shikoku', 'description' => $p('Kochi', 'рынок Хиромарти, замок и побережье тихоокеанской стороны.', 'Сильны еда, море и провинциальный характер; зимний powder — не здесь.')],
        ['name' => 'Naoshima', 'region' => 'shikoku', 'description' => $p('Naoshima', 'остров современного искусства во Внутреннем море.', 'Сильны арт, дизайн и камерность; семейные лыжи и ночной урбан — нет.')],

        // Kyushu (6)
        ['name' => 'Fukuoka', 'region' => 'kyushu', 'description' => $p('Fukuoka', 'живой хаб Кюсю с ятай и коротким перелётом из Азии.', 'Сильны еда, город и доступ к острову; «золотой» Киото — отдельно по синкансену.')],
        ['name' => 'Nagasaki', 'region' => 'kyushu', 'description' => $p('Nagasaki', 'порт с многослойной историей и холмистым центром.', 'Сильны история, виды и кухня; лыжные курорты Хоккайдо — далеко.')],
        ['name' => 'Kumamoto', 'region' => 'kyushu', 'description' => $p('Kumamoto', 'замок и ворота к Асо.', 'Сильны вулканический пейзаж и локальная еда; кораллы Окинавы — южнее.')],
        ['name' => 'Beppu', 'region' => 'kyushu', 'description' => $p('Beppu', 'столица онсэнов с тысячами источников.', 'Сильны горячие источники и курортный ритм; мегаполисный шопинг — в Фукуоке.')],
        ['name' => 'Kagoshima', 'region' => 'kyushu', 'description' => $p('Kagoshima', 'вид на Сакурадзиму и южный характер Кюсю.', 'Сильны вулкан, еда и море; снежные Альпы — не этот регион.')],
        ['name' => 'Yufuin', 'region' => 'kyushu', 'description' => $p('Yufuin', 'стильный онсэн-курорт в долине у Юфу-дакэ.', 'Сильны онсэны, прогулки и камерный люкс; ночной урбан — отсутствует.')],

        // Okinawa (4)
        ['name' => 'Naha', 'region' => 'okinawa', 'description' => $p('Naha', 'столица Окинавы и хаб островов.', 'Сильны море, еда и городской старт архипелага; снег и клёновые горы — не здесь.')],
        ['name' => 'Ishigaki', 'region' => 'okinawa', 'description' => $p('Ishigaki', 'база к Яэяме: снорклинг, джунгли и острова.', 'Сильны пляжи и природа; храмы Киото и лыжи — другой климат.')],
        ['name' => 'Miyako', 'region' => 'okinawa', 'description' => $p('Miyako', 'бирюзовая вода и пляжный отдых.', 'Сильны море и релакс; культурный Кансай и зима Хоккайдо — не про Мияко.')],
        ['name' => 'Nago', 'region' => 'okinawa', 'description' => $p('Nago', 'север Окинавы-хонто, аквариум и спокойные бухты.', 'Сильны семья, море и природа; ночной мегаполис — в Нахе слабее, чем в Токио.')],
    ];
}
