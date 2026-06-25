<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseConnectionProbeService
{
    /**
     * @return list<array{
     *     connection: string,
     *     driver: string,
     *     ok: bool,
     *     message: string,
     *     host: string,
     *     database: string,
     *     response_ms: int|null,
     *     is_default: bool
     * }>
     */
    public function probeAll(): array
    {
        $results = [];

        foreach (['mysql', 'pgsql'] as $connection) {
            $results[] = $this->probe($connection);
        }

        return $results;
    }

    /**
     * @return array{
     *     connection: string,
     *     driver: string,
     *     ok: bool,
     *     message: string,
     *     host: string,
     *     database: string,
     *     response_ms: int|null,
     *     is_default: bool
     * }
     */
    public function probe(string $connection): array
    {
        $config = config('database.connections.'.$connection, []);
        $host = (string) ($config['host'] ?? '—');
        $database = (string) ($config['database'] ?? '—');
        $driver = (string) ($config['driver'] ?? $connection);
        $isDefault = config('database.default') === $connection;

        $startedAt = microtime(true);

        try {
            DB::connection($connection)->getPdo();
            DB::connection($connection)->select('select 1 as ok');

            return [
                'connection' => $connection,
                'driver' => $driver,
                'ok' => true,
                'message' => 'Подключение активно',
                'host' => $host,
                'database' => $database,
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'is_default' => $isDefault,
            ];
        } catch (Throwable $e) {
            return [
                'connection' => $connection,
                'driver' => $driver,
                'ok' => false,
                'message' => $e->getMessage(),
                'host' => $host,
                'database' => $database,
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'is_default' => $isDefault,
            ];
        }
    }
}
