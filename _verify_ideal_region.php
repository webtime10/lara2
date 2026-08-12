<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\CategoryDescription;

$scoreFields = array_values(array_filter(
    (array) config('ideal_region_category_fields.fields', []),
    static fn ($f) => is_string($f) && str_starts_with($f, 'step') && ! str_ends_with($f, '_description')
));

$cats = Category::query()
    ->where('manufacturer_id', 1)
    ->where('status', true)
    ->with('descriptions')
    ->orderBy('id')
    ->get();

$withVesnoy = 0;
$complete = 0;
$incomplete = [];

foreach ($cats as $c) {
    $d = $c->descriptions->first();
    if (! $d) {
        $incomplete[] = "id={$c->id} no description";
        continue;
    }
    if ($d->step1_vesnoy !== null && $d->step1_vesnoy !== '') {
        $withVesnoy++;
    }
    $missing = [];
    foreach ($scoreFields as $f) {
        $v = $d->$f;
        if ($v === null || $v === '') {
            $missing[] = $f;
            continue;
        }
        $desc = $d->{$f . '_description'};
        if ($desc === null || trim((string) $desc) === '') {
            $missing[] = $f . '_description';
        }
    }
    if (empty(trim((string) $d->description))) {
        $missing[] = 'description';
    }
    if ($missing === []) {
        $complete++;
    } else {
        $incomplete[] = "id={$c->id} " . ($d->name ?? '?') . ' missing=' . implode(',', array_slice($missing, 0, 5));
    }
}

echo "active_swiss={$cats->count()}\n";
echo "step1_vesnoy_filled={$withVesnoy}\n";
echo "fully_complete={$complete}\n";
if ($incomplete) {
    echo "incomplete:\n";
    foreach ($incomplete as $line) {
        echo "  {$line}\n";
    }
}
