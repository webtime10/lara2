@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">{{ $pageTitle }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.entertainments.index') }}">Бюджет — Развлечения</a></li>
                        <li class="breadcrumb-item active">{{ $region->label }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (! empty($error))
                <div class="alert alert-danger">{{ $error }}</div>
            @endif

            <div class="mb-3">
                <a href="{{ route('admin.budget.entertainments.index') }}" class="btn btn-sm btn-secondary">← Все кантоны</a>
                <a href="{{ route('admin.budget.entertainments.show', ['slug' => $region->slug, 'refresh' => 1]) }}" class="btn btn-sm btn-primary">Обновить из API</a>
                <a href="{{ route('admin.entertainment-visit-prices.index') }}" class="btn btn-sm btn-outline-success">Цены развлечений (AI)</a>
            </div>

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO / Google Maps: <code>{{ $apiHint }}</code>.
                        Здесь сохраняются только места и категории — без цен.
                        Цены за 1 визит получаются отдельно на странице
                        <a href="{{ route('admin.entertainment-visit-prices.index') }}">Бюджет → Цены развлечений</a>.
                        Частота визитов и итоговый бюджет считаются в Laravel.
                        @if ($region->entertainments_synced_at)
                            <br>Сохранено в БД: <strong>{{ $syncedCount ?? $items->total() }}</strong> развлечений,
                            обновлено {{ $region->entertainments_synced_at->format('d.m.Y H:i') }}.
                        @endif
                    </p>

                    @if (! empty($summary))
                        <p class="text-muted mb-3">
                            Объектов: <strong>{{ $summary['total_objects'] }}</strong>,
                            сетей/брендов: <strong>{{ $summary['total_brands'] }}</strong>.
                            @foreach ($summary['categories'] as $category => $row)
                                @if ($row['objects'] > 0)
                                    <br>{{ $category }}: {{ $row['objects'] }} объектов / {{ $row['brands'] }} сетей
                                @endif
                            @endforeach
                        </p>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th style="width: 150px;">Категория</th>
                                    <th style="width: 220px;">Сайт</th>
                                    <th style="width: 90px;">Рейтинг</th>
                                    <th style="width: 90px;">Отзывы</th>
                                    <th>Адрес</th>
                                    <th style="width: 220px;">place_id</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td><strong>{{ $item->category }}</strong></td>
                                        <td>
                                            @if ($item->website)
                                                <a href="{{ $item->website }}" target="_blank" rel="noopener">{{ $item->website }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $item->rating !== null ? number_format((float) $item->rating, 1) : '—' }}</td>
                                        <td>{{ $item->reviews ?? '—' }}</td>
                                        <td><small>{{ $item->address ?: '—' }}</small></td>
                                        <td><small style="word-break: break-all;">{{ $item->place_id ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Нет данных</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $items->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </section>
@endsection
