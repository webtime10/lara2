<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;

$cats = Category::query()
    ->with('descriptions')
    ->where('manufacturer_id', 1)
    ->where('status', true)
    ->orderBy('id')
    ->get();

foreach ($cats as $c) {
    $d = $c->descriptions->first();
    $filled = $d && filled($d->step1_vesnoy) ? 'FILLED' : 'empty';
    echo "{$c->id}\t{$filled}\t" . ($d->name ?? '?') . "\n";
}
echo 'TOTAL=' . $cats->count() . "\n";
