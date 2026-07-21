<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = Schema::getColumnListing('category_descriptions');
echo 'has slug: '.(in_array('slug', $cols, true) ? 'yes' : 'no').PHP_EOL;
$indexes = DB::select('SHOW INDEX FROM category_descriptions WHERE Key_name LIKE "%slug%"');
foreach ($indexes as $i) {
    echo $i->Key_name.' | '.$i->Column_name.PHP_EOL;
}
