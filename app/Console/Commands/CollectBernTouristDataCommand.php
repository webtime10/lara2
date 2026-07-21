<?php

namespace App\Console\Commands;

use App\Services\BusinessListings\BernTouristCollectService;
use Illuminate\Console\Command;

class CollectBernTouristDataCommand extends Command
{
    protected $signature = 'dataforseo:collect-bern
                            {--skip-listings : Только categories + location + aggregation (без полной выгрузки POI)}
                            {--probe-limit=3 : Сколько категорий для проверочной aggregation}';

    protected $description = 'DataForSEO Business Listings: тестовый сбор туристических данных по кантону Bern';

    public function handle(BernTouristCollectService $service): int
    {
        $this->info('Старт сбора Bern (Business Listings)...');

        try {
            $stats = $service->collect(
                (bool) $this->option('skip-listings'),
                (int) $this->option('probe-limit')
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Итог ===');
        $this->line('location_code: '.$stats['location_code'].' — '.$stats['location']['location_name']);
        $this->line('location_coordinate: '.($stats['location_coordinate'] ?? '—'));
        $this->line('selection: '.$stats['location']['selection_reason']);
        $this->line('categories total: '.$stats['categories_total']);
        $this->line('tourist matched: '.$stats['tourist_matched_count']);
        $this->line('unmatched groups: '.implode(', ', $stats['tourist_unmatched_groups'] ?: ['—']));
        $this->newLine();
        $this->line('Aggregation counts:');
        foreach ($stats['aggregation_counts'] as $row) {
            $this->line(sprintf('  - %s: %d', $row['category_name'], $row['objects_count']));
        }
        $this->newLine();
        $poi = $stats['poi'];
        $this->line('API requests: '.$stats['api_requests']);
        $this->line('API cost estimate: '.$stats['api_cost_estimate']);
        $this->line('POI fetched: '.$poi['fetched']);
        $this->line('POI created: '.$poi['created']);
        $this->line('POI updated: '.$poi['updated']);
        $this->line('Duplicates seen: '.$poi['duplicates']);
        $this->line('Without coords: '.$poi['without_coords']);
        $this->line('Without rating: '.$poi['without_rating']);
        $this->line('Without reviews: '.$poi['without_reviews']);
        $this->line('Elapsed: '.$stats['elapsed_seconds'].'s');
        $this->newLine();
        $this->info('Админка: /admin/bern-tourist');

        return self::SUCCESS;
    }
}
