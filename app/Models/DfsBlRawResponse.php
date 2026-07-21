<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DfsBlRawResponse extends Model
{
    protected $table = 'dfs_bl_raw_responses';

    protected $fillable = [
        'endpoint',
        'destination_slug',
        'request_key',
        'payload_json',
        'response_json',
        'http_cost',
        'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
    ];
}
