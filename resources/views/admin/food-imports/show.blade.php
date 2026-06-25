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
                <a href="{{ route('admin.food-imports.index') }}" class="btn btn-sm btn-secondary">← Все кантоны</a>
                <form method="get" class="d-inline-block ml-2">
                    <select name="keyword" class="form-control form-control-sm d-inline-block" style="width: 180px;">
                        <option value="">Все keyword</option>
                        @foreach ($keywords as $keyword)
                            <option value="{{ $keyword }}" @selected(request('keyword') === $keyword)>{{ $keyword }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Фильтр</button>
                    <a href="{{ route('admin.food-imports.show', $region->slug) }}" class="btn btn-sm btn-default">Сброс</a>
                </form>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-body">
                    <p class="text-muted">
                        Регион: <strong>{{ $region->label }}</strong>,
                        location_code=<code>{{ $region->location_code }}</code>.
                        Всего по фильтру: <strong>{{ $items->total() }}</strong>.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 90px;">keyword</th>
                                    <th>Название</th>
                                    <th>Сайт</th>
                                    <th style="width: 80px;">Рейтинг</th>
                                    <th style="width: 90px;">Отзывы</th>
                                    <th>Адрес</th>
                                    <th style="width: 90px;">price_level</th>
                                    <th style="width: 140px;">food_type</th>
                                    <th style="width: 140px;">Требует проверки</th>
                                    <th style="width: 180px;">place_id</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td><code>{{ $item->keyword }}</code></td>
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
                                        <td>{{ $item->food_type ?: '—' }}</td>
                                        <td>{{ $item->gpt_processed ? 'нет' : 'да' }}</td>
                                        <td><small style="word-break: break-all;">{{ $item->place_id ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Нет данных</td>
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
