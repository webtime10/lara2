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
                        <li class="breadcrumb-item active">Bern Tourist</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary mb-3">
                <div class="card-header">
                    <h3 class="card-title">Сбор DataForSEO Business Listings</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">
                        Команда: <code>php artisan dataforseo:collect-bern</code>
                        (проверка без POI: <code>--skip-listings</code>).
                    </p>
                    <button type="button" class="btn btn-primary" id="bern-collect-btn">Запустить сбор (categories + location + aggregation + listings)</button>
                    <button type="button" class="btn btn-outline-secondary" id="bern-collect-probe-btn">Только проверка (без listings)</button>
                    <div id="bern-collect-status" class="mt-2 text-muted"></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $stats['pois_total'] }}</h3>
                            <p>POI в БД</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $stats['without_coords'] }}</h3>
                            <p>Без координат</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $stats['without_rating'] }}</h3>
                            <p>Без rating</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{{ $stats['without_reviews'] }}</h3>
                            <p>Без reviews</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Локация кантона Bern</strong></div>
                <div class="card-body">
                    @if ($selectedLocation)
                        <p>
                            Выбрано: <code>{{ $selectedLocation->location_code }}</code>
                            — {{ $selectedLocation->location_name }}
                            ({{ $selectedLocation->location_type ?: 'n/a' }})
                        </p>
                        @php
                            $coord = data_get($selectedLocation->raw_data, 'location_coordinate');
                        @endphp
                        @if ($coord)
                            <p>Coordinate: <code>{{ $coord }}</code></p>
                        @endif
                        <p class="text-muted mb-2">{{ $selectedLocation->selection_reason }}</p>
                    @else
                        <p class="text-muted">Локация ещё не определена. Запустите сбор.</p>
                    @endif

                    @if ($locationCandidates->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Выбрана</th>
                                        <th>code</th>
                                        <th>name</th>
                                        <th>type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($locationCandidates as $cand)
                                        <tr class="{{ $cand->is_selected ? 'table-success' : '' }}">
                                            <td>{{ $cand->is_selected ? 'yes' : '' }}</td>
                                            <td><code>{{ $cand->location_code }}</code></td>
                                            <td>{{ $cand->location_name }}</td>
                                            <td>{{ $cand->location_type }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Туристические категории (матчинг)</strong></div>
                        <div class="card-body table-responsive p-0" style="max-height: 360px; overflow:auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Группа</th>
                                        <th>Найдено</th>
                                        <th>Категория DFS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($matches as $match)
                                        <tr>
                                            <td>{{ $match->topic_group }}</td>
                                            <td>{{ $match->matched ? 'yes' : 'no' }}</td>
                                            <td>{{ $match->category_name ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted">Нет данных</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Aggregation counts</strong></div>
                        <div class="card-body table-responsive p-0" style="max-height: 360px; overflow:auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Категория</th>
                                        <th>count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($aggregations as $row)
                                        <tr>
                                            <td>{{ $row->category_name }}</td>
                                            <td>{{ $row->objects_count }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-muted">Нет данных</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>POI Bern</strong>
                    <form method="get" class="form-inline float-right">
                        <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm mr-2" placeholder="Поиск по названию">
                        <select name="category" class="form-control form-control-sm mr-2">
                            <option value="">Все категории</option>
                            @foreach ($categoryOptions as $opt)
                                <option value="{{ $opt }}" @selected($filters['category'] === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-secondary" type="submit">Фильтр</button>
                    </form>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Категории</th>
                                <th>Rating</th>
                                <th>Reviews</th>
                                <th>Координаты</th>
                                <th>Адрес</th>
                                <th>Собрано</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pois as $poi)
                                <tr>
                                    <td>{{ $poi->name ?: $poi->title }}</td>
                                    <td>
                                        {{ $poi->primary_category }}
                                        @if ($poi->categories->count() > 1)
                                            <small class="text-muted">+{{ $poi->categories->count() - 1 }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $poi->rating ?? '—' }}</td>
                                    <td>{{ $poi->reviews_count ?? '—' }}</td>
                                    <td>
                                        @if ($poi->latitude !== null && $poi->longitude !== null)
                                            {{ $poi->latitude }}, {{ $poi->longitude }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $poi->address }}</td>
                                    <td>{{ optional($poi->collected_at)->format('d.m.Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted p-3">POI ещё нет. Запустите сбор.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($pois->hasPages())
                    <div class="card-footer">{{ $pois->links('pagination::bootstrap-4') }}</div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
  function runCollect(skipListings) {
    var status = document.getElementById('bern-collect-status');
    status.textContent = 'Запрос к DataForSEO… это может занять несколько минут.';
    fetch(@json(route('admin.bern-tourist.collect')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ skip_listings: !!skipListings, probe_limit: 3 })
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.ok) {
          status.textContent = json.message || 'Ошибка';
          return;
        }
        status.textContent = 'Готово. POI: ' + (json.stats.poi.created + json.stats.poi.updated) + '. Обновляю страницу…';
        window.location.reload();
      })
      .catch(function (err) {
        status.textContent = err && err.message ? err.message : 'Network error';
      });
  }

  var btn = document.getElementById('bern-collect-btn');
  var probeBtn = document.getElementById('bern-collect-probe-btn');
  if (btn) btn.addEventListener('click', function () { runCollect(false); });
  if (probeBtn) probeBtn.addEventListener('click', function () { runCollect(true); });
})();
</script>
@endsection
