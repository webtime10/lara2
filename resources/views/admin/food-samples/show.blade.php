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
                        <li class="breadcrumb-item"><a href="{{ route('admin.food-imports.index') }}">Импорт питания</a></li>
                        <li class="breadcrumb-item active">Выборка {{ $region->label }}</li>
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
                <a href="{{ route('admin.food-imports.index') }}" class="btn btn-sm btn-secondary">← Все кантоны</a>
                <a href="{{ route('admin.food-imports.show', $region->slug) }}" class="btn btn-sm btn-outline-secondary ml-2">Сырьё food_imports</a>
                <form method="get" class="d-inline-block ml-2">
                    <select name="food_type" class="form-control form-control-sm d-inline-block" style="width: 190px;">
                        <option value="">Все типы</option>
                        @foreach ($limits as $foodType => $limit)
                            <option value="{{ $foodType }}" @selected(request('food_type') === $foodType)>
                                {{ $foodType }} — TOP {{ $limit }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Фильтр</button>
                    <a href="{{ route('admin.food-samples.show', $region->slug) }}" class="btn btn-sm btn-default">Сброс</a>
                </form>
            </div>

            <div class="card card-outline card-success">
                <div class="card-body">
                    <p class="text-muted">
                        Это рабочая выборка для расчёта питания: TOP 10 cafe, TOP 10 restaurant,
                        TOP 5 restaurant_candidate по <code>reviews_count</code> и <code>rating</code>.
                        Сетевые филиалы схлопываются до одного самого популярного заведения.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Ранг</th>
                                    <th style="width: 150px;">food_type</th>
                                    <th>Название</th>
                                    <th>Сайт</th>
                                    <th style="width: 80px;">Рейтинг</th>
                                    <th style="width: 90px;">Отзывы</th>
                                    <th>Адрес</th>
                                    <th style="width: 90px;">price_level</th>
                                    <th style="width: 110px;">confidence</th>
                                    <th style="width: 140px;">Требует проверки</th>
                                    <th style="width: 180px;">place_id</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->sample_rank }}</td>
                                        <td>{{ $item->food_type }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td>
                                            @if ($item->website)
                                                <a href="{{ $item->website }}" target="_blank" rel="noopener">{{ $item->website }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $item->rating !== null ? number_format((float) $item->rating, 1) : '—' }}</td>
                                        <td>{{ $item->reviews_count ?? '—' }}</td>
                                        <td><small>{{ $item->address ?: '—' }}</small></td>
                                        <td>{{ $item->price_level ?? '—' }}</td>
                                        <td>{{ $item->classification_confidence ?: '—' }}</td>
                                        <td>{{ $item->gpt_processed ? 'нет' : 'да' }}</td>
                                        <td><small style="word-break: break-all;">{{ $item->place_id ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">
                                            Выборка ещё не сформирована
                                        </td>
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
