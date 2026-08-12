<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$d = App\Models\CategoryDescription::where('category_id', 7)->first();
if (! $d) {
    echo "no lucerne\n";
    exit(1);
}

echo "name={$d->name}\n";
$fields = (array) config('ideal_region_category_fields.fields');
foreach ($fields as $f) {
    if (str_ends_with($f, '_description')) {
        continue;
    }
    echo "{$f}={$d->$f}\n";
}
echo "DESCRIPTION:\n{$d->description}\n";
echo "---DESCS---\n";
foreach ($fields as $f) {
    if (! str_ends_with($f, '_description')) {
        continue;
    }
    echo "{$f}={$d->$f}\n\n";
}

// list active swiss cats
$cats = App\Models\Category::query()
    ->where('manufacturer_id', 1)
    ->where('status', true)
    ->with('descriptions')
    ->orderBy('id')
    ->get();
echo "ACTIVE_SWISS_COUNT=".$cats->count()."\n";
foreach ($cats as $c) {
    $name = optional($c->descriptions->first())->name;
    $v = optional($c->descriptions->first())->step1_vesnoy;
    echo "id={$c->id}\tname={$name}\tvesnoy=".var_export($v, true)."\n";
}
