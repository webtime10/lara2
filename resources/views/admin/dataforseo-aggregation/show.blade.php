@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0">Aggregation run #{{ $run->id }}</h1>
                </div>
                <div class="col-sm-4">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.dataforseo-aggregation.index') }}">Aggregation</a></li>
                        <li class="breadcrumb-item active">#{{ $run->id }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="mb-3">
                <a href="{{ route('admin.dataforseo-aggregation.index') }}" class="btn btn-secondary btn-sm">← К списку Aggregation</a>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Сводка запуска</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Destination:</strong> {{ $run->destination }}</p>
                            <p class="mb-1"><strong>Location code:</strong> <code>{{ $run->location_code }}</code></p>
                            <p class="mb-1"><strong>Location coordinate:</strong> <code>{{ $run->location_coordinate }}</code></p>
                            <p class="mb-1"><strong>Status:</strong> {{ $run->status }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>API endpoint:</strong><br><code class="small text-break">{{ $run->endpoint }}</code></p>
                            <p class="mb-1"><strong>API requests:</strong> {{ $run->api_requests }}</p>
                            <p class="mb-1"><strong>Категорий:</strong> {{ $run->categories_processed }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Total objects reported:</strong> {{ $run->total_objects_reported }}
                                <span class="d-block small text-muted">сумма counts, не уникальные POI</span>
                            </p>
                            <p class="mb-1"><strong>API cost:</strong>
                                @if ($run->api_cost !== null)
                                    ${{ number_format((float) $run->api_cost, 6, '.', '') }}
                                @else
                                    —
                                @endif
                            </p>
                            <p class="mb-1"><strong>Execution time:</strong> {{ $run->execution_time_ms }} ms</p>
                            <p class="mb-0"><strong>Collected at:</strong> {{ optional($run->collected_at)->format('d.m.Y H:i:s') }}</p>
                        </div>
                    </div>
                    @if ($run->error_message)
                        <div class="alert alert-danger mt-3 mb-0">{{ $run->error_message }}</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Category counts</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Category code</th>
                                <th>Objects count</th>
                                <th>Collected at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($run->categories as $row)
                                <tr>
                                    <td>{{ $row->category_name }}</td>
                                    <td><code>{{ $row->category_code }}</code></td>
                                    <td>{{ $row->objects_count }}</td>
                                    <td>{{ optional($row->collected_at)->format('d.m.Y H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted p-3">Нет категорий в этом запуске.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
