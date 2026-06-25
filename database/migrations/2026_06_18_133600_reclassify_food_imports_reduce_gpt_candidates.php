<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $cafeKeywords = [
        'cafe',
        'coffee shop',
        'bakery',
    ];

    /** @var list<string> */
    private array $restaurantKeywords = [
        'restaurant',
        'pizza restaurant',
        'swiss restaurant',
        'vegetarian restaurant',
        'seafood restaurant',
        'steak house',
        'brunch restaurant',
        'family restaurant',
    ];

    /** @var list<string> */
    private array $premiumNameParts = [
        'fine dining',
        'gourmet',
        'michelin',
        'luxury',
        'signature',
        'chef',
    ];

    /** @var list<string> */
    private array $premiumDomainParts = [
        'michelin',
        'gaultmillau',
        'fine-dining',
        'finedining',
        'gourmet',
        'chef',
    ];

    public function up(): void
    {
        DB::table('food_imports')
            ->where(function ($query) {
                $query->whereNull('website')->orWhere('website', '');
            })
            ->delete();

        DB::table('food_imports')
            ->whereIn('keyword', $this->cafeKeywords)
            ->update(['food_type' => 'cafe', 'gpt_processed' => true]);

        DB::table('food_imports')
            ->whereIn('keyword', $this->restaurantKeywords)
            ->orderBy('id')
            ->select(['id', 'name', 'website', 'rating', 'reviews_count'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $premium = $this->looksPremium($row);

                    DB::table('food_imports')
                        ->where('id', $row->id)
                        ->update([
                            'food_type' => $premium ? 'restaurant_candidate' : 'restaurant',
                            'gpt_processed' => ! $premium,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('food_imports')
            ->whereIn('keyword', $this->restaurantKeywords)
            ->update(['food_type' => 'restaurant_candidate', 'gpt_processed' => false]);
    }

    private function looksPremium(object $row): bool
    {
        if ((int) ($row->reviews_count ?? 0) > 1000 && (float) ($row->rating ?? 0) >= 4.5) {
            return true;
        }

        $name = mb_strtolower((string) $row->name);
        foreach ($this->premiumNameParts as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        $website = mb_strtolower((string) $row->website);
        foreach ($this->premiumDomainParts as $part) {
            if (str_contains($website, $part)) {
                return true;
            }
        }

        return false;
    }
};
