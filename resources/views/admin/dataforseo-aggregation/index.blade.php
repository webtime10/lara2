@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0">DataForSEO Categories Aggregation</h1>
                </div>
                <div class="col-sm-4">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item active">Aggregation</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="alert alert-info">
                Эта страница выполняет <strong>только</strong> Categories Aggregation API.
                Business Listings Search и POI здесь не используются и не загружаются.
            </div>

            <div class="card card-outline card-primary mb-3">
                <div class="card-header"><strong>Параметры</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Destination:</strong> {{ $destination }}</p>
                            <p class="mb-1"><strong>Location code:</strong> <code>{{ $locationCode }}</code></p>
                            <p class="mb-1"><strong>Location coordinate:</strong> <code>{{ $locationCoordinate }}</code></p>
                            <p class="mb-1"><strong>API endpoint:</strong> <code class="d-inline-block text-break">{{ $endpoint }}</code></p>
                            <p class="mb-1"><strong>Туристических категорий:</strong> {{ $selectedCategoriesCount }}</p>
                            <p class="mb-1"><strong>Планируемых API requests:</strong> {{ $plannedApiRequests }}</p>
                            <p class="mb-0">
                                <strong>Дата последнего сбора:</strong>
                                @if ($latestRun)
                                    {{ optional($latestRun->collected_at)->format('d.m.Y H:i:s') }}
                                @else
                                    — ещё не было
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>API cost последнего запроса:</strong>
                                @if ($latestRun && $latestRun->api_cost !== null)
                                    ${{ number_format((float) $latestRun->api_cost, 6, '.', '') }}
                                @else
                                    —
                                @endif
                            </p>
                            <p class="mb-2 text-muted small">
                                Стоимость берётся только из ответа DataForSEO (`cost`), без выдуманных оценок.
                            </p>
                            <button type="button" class="btn btn-primary" id="dfs-agg-fetch-btn">
                                Получить Aggregation данные
                            </button>
                            <div id="dfs-agg-status" class="mt-2 text-muted"></div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($selectedCategoriesCount > 0)
                <div class="card mb-3">
                    <div class="card-header"><strong>Выбранные туристические категории (из справочника)</strong></div>
                    <div class="card-body table-responsive p-0" style="max-height: 240px; overflow:auto;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Группа</th>
                                    <th>Category</th>
                                    <th>Category code</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($selectedCategories as $cat)
                                    <tr>
                                        <td>{{ $cat['topic_group'] }}</td>
                                        <td>{{ $cat['category_name'] }}</td>
                                        <td><code>{{ $cat['category_code'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="alert alert-warning">
                    Туристические категории не найдены в справочнике.
                    Сначала выполните получение Categories на существующей странице DataForSEO
                    (справка сохранится в `dfs_bl_categories` / matches).
                </div>
            @endif

            @if ($latestRun && $latestRun->status === 'success')
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Последний результат</strong>
                        <a href="{{ route('admin.dataforseo-aggregation.show', $latestRun) }}" class="btn btn-xs btn-outline-primary float-right">Открыть запуск #{{ $latestRun->id }}</a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>API endpoint</strong><br><code class="small text-break">{{ $latestRun->endpoint }}</code></div>
                            <div class="col-md-2"><strong>Location code</strong><br><code>{{ $latestRun->location_code }}</code></div>
                            <div class="col-md-2"><strong>API requests</strong><br>{{ $latestRun->api_requests }}</div>
                            <div class="col-md-2"><strong>Категорий</strong><br>{{ $latestRun->categories_processed }}</div>
                            <div class="col-md-3"><strong>Total objects reported</strong><br>{{ $latestRun->total_objects_reported }}
                                <div class="small text-muted">сумма counts (не уникальные POI)</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>API cost</strong><br>
                                @if ($latestRun->api_cost !== null)
                                    ${{ number_format((float) $latestRun->api_cost, 6, '.', '') }}
                                @else
                                    —
                                @endif
                            </div>
                            <div class="col-md-3"><strong>Execution time</strong><br>{{ $latestRun->execution_time_ms }} ms</div>
                            <div class="col-md-3"><strong>Collected at</strong><br>{{ optional($latestRun->collected_at)->format('d.m.Y H:i:s') }}</div>
                            <div class="col-md-3"><strong>Status</strong><br>{{ $latestRun->status }}</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Category code</th>
                                        <th>Objects count</th>
                                        <th>Collected at</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($latestRun->categories as $row)
                                        <tr>
                                            <td>{{ $row->category_name }}</td>
                                            <td><code>{{ $row->category_code }}</code></td>
                                            <td>{{ $row->objects_count }}</td>
                                            <td>{{ optional($row->collected_at)->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><strong>История Aggregation сборов</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Destination</th>
                                <th>Категорий</th>
                                <th>Total objects reported</th>
                                <th>API requests</th>
                                <th>API cost</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $run)
                                <tr>
                                    <td>{{ optional($run->collected_at)->format('d.m.Y H:i') }}</td>
                                    <td>{{ $run->destination }}</td>
                                    <td>{{ $run->categories_processed ?: $run->categories_selected }}</td>
                                    <td>{{ $run->total_objects_reported }}</td>
                                    <td>{{ $run->api_requests }}</td>
                                    <td>
                                        @if ($run->api_cost !== null)
                                            ${{ number_format((float) $run->api_cost, 6, '.', '') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $run->status }}</td>
                                    <td>
                                        <a href="{{ route('admin.dataforseo-aggregation.show', $run) }}">Открыть</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-muted p-3">История пуста. Нажмите «Получить Aggregation данные».</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
  var btn = document.getElementById('dfs-agg-fetch-btn');
  var status = document.getElementById('dfs-agg-status');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var planned = {{ (int) $plannedApiRequests }};
    var msg =
      'Будет выполнен только Categories Aggregation API запрос. Business Listings и конкретные POI загружаться не будут.\n\n' +
      'Планируется API requests: ' + planned + '.\n\n' +
      'Продолжить?';
    if (!window.confirm(msg)) {
      return;
    }

    status.textContent = 'Запрос Categories Aggregation…';
    btn.disabled = true;

    fetch(@json(route('admin.dataforseo-aggregation.fetch')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ confirmed: true })
    })
      .then(function (r) { return r.json().then(function (json) { return { okHttp: r.ok, json: json }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.json.ok) {
          status.textContent = res.json.message || 'Ошибка';
          return;
        }
        var cost = res.json.stats && res.json.stats.api_cost != null
          ? (' Cost: $' + res.json.stats.api_cost)
          : '';
        status.textContent = 'Готово. Requests: ' + res.json.stats.api_requests + '.' + cost + ' Открываю запуск…';
        window.location.href = res.json.redirect;
      })
      .catch(function (err) {
        btn.disabled = false;
        status.textContent = err && err.message ? err.message : 'Network error';
      });
  });
})();
</script>
@endsection
