<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherSyncRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SCHEDULE = 'schedule';

    protected $fillable = [
        'uuid',
        'status',
        'force',
        'only_empty',
        'source',
        'total',
        'succeeded',
        'failed',
        'skipped',
        'last_message',
        'recent_logs',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'force' => 'boolean',
        'only_empty' => 'boolean',
        'total' => 'integer',
        'succeeded' => 'integer',
        'failed' => 'integer',
        'skipped' => 'integer',
        'recent_logs' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function processed(): int
    {
        return $this->succeeded + $this->failed + $this->skipped;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true)
            || ($this->total > 0 && $this->processed() >= $this->total);
    }

    public function appendLog(string $message, bool $ok = true): void
    {
        $logs = is_array($this->recent_logs) ? $this->recent_logs : [];
        $logs[] = [
            'ok' => $ok,
            'text' => $message,
            'at' => now()->format('H:i:s'),
        ];
        if (count($logs) > 80) {
            $logs = array_slice($logs, -80);
        }

        $this->recent_logs = $logs;
        $this->last_message = mb_substr($message, 0, 500);
    }
}
