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
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.hotels.index') }}">Бюджет — Отели</a></li>
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
                <a href="{{ route('admin.budget.hotels.index') }}" class="btn btn-sm btn-secondary">← Все кантоны</a>
                <a href="{{ route('admin.budget.hotels.show', ['slug' => $region->slug, 'refresh' => 1]) }}" class="btn btn-sm btn-primary">Обновить из API</a>
            </div>

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO: <code>{{ $apiHint }}</code>, <code>keyword=hotels</code>, <code>currency=USD</code>.
                        Класс 1 / 2 / 3 — по цене внутри кантона.
                        @if ($region->hotels_synced_at)
                            <br>Сохранено в БД: <strong>{{ $syncedCount ?? $items->total() }}</strong> отелей,
                            обновлено {{ $region->hotels_synced_at->format('d.m.Y H:i') }}.
                        @endif
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Название отеля</th>
                                    <th style="width: 100px;">Класс</th>
                                    <th style="width: 100px;">Звёзды</th>
                                    <th style="width: 200px;">Цена за 2-местный номер ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td class="text-center">{{ $item->level }}</td>
                                        <td class="text-center">{{ $item->stars ? $item->stars.'*' : '—' }}</td>
                                        <td>${{ number_format($item->price_usd, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Нет данных</td>
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
