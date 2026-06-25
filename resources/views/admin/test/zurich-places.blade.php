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
                        <li class="breadcrumb-item active">Тест</li>
                        <li class="breadcrumb-item active">Places Цюрих</li>
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

            <div class="alert alert-info">
                DataForSEO Google Maps Live Advanced. location_code: <code>{{ $locationCode }}</code> (Zurich, Switzerland).
                Без пересчётов — значения как в ответе API. Пагинация: 100 на страницу.
            </div>

            <div class="card card-outline card-primary mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">Развлечения — keyword: <code>museums attractions</code></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Название места</th>
                                    <th>Тип/Категория</th>
                                    <th style="width: 160px;">Уровень цен (price_level)</th>
                                    <th style="width: 220px;">Рейтинг</th>
                                    <th>Доп. поля (цена/описание)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($entertainment as $item)
                                    <tr>
                                        <td>{{ $item['title'] ?? '—' }}</td>
                                        <td>{{ $placesService->entertainmentCategory($item) }}</td>
                                        <td>{{ $placesService->rawPriceLevel($item) }}</td>
                                        <td><small>{{ $placesService->rawRating($item) }}</small></td>
                                        <td><small>{{ $placesService->rawPriceDescriptionExtras($item) }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Нет данных</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $entertainment->links('pagination::bootstrap-4') }}
                </div>
            </div>

            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title mb-0">Еда — keyword: <code>restaurants</code></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Название ресторана</th>
                                    <th style="width: 200px;">Уровень цен (price_level)</th>
                                    <th style="width: 220px;">Рейтинг</th>
                                    <th>Доп. поля (цена/описание)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($food as $item)
                                    <tr>
                                        <td>{{ $item['title'] ?? '—' }}</td>
                                        <td>{{ $placesService->rawPriceLevel($item) }}</td>
                                        <td><small>{{ $placesService->rawRating($item) }}</small></td>
                                        <td><small>{{ $placesService->rawPriceDescriptionExtras($item) }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Нет данных</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $food->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </section>
@endsection
