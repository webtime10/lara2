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
                <button type="button" class="btn btn-sm btn-success" id="btn-batch-123">
                    Прогнать всё
                </button>
            </div>

            <div id="batch-123-wrap" class="card card-outline card-success mb-3" style="display:none;">
                <div class="card-body">
                    <div class="progress mb-2" style="height: 22px;">
                        <div id="batch-123-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%">0%</div>
                    </div>
                    <p id="batch-123-status" class="mb-2 text-muted">Подготовка…</p>
                    <ul id="batch-123-log" class="list-unstyled mb-0 small" style="max-height:220px; overflow-y:auto;"></ul>
                </div>
            </div>

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO: <code>{{ $apiHint }}</code>, <code>keyword=hotels</code>, <code>currency=USD</code>.
                        Класс 1 / 2 / 3 — по цене внутри кантона.
                        Название отеля открывает сетку occupancy (1A, 2A, 3A…).
                        Кнопка <strong>Прогнать всё</strong> — все отели кантона по галочкам из настроек (уже залитые пропускаются).
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
                                        <td>
                                            <a href="{{ route('admin.budget.hotels.hotel', ['slug' => $region->slug, 'hotel' => $item->id]) }}">
                                                {{ $item->title }}
                                            </a>
                                            @if (! $item->hotel_identifier)
                                                <span class="badge badge-warning ml-1" title="Нужно обновить кантон из API">нет id</span>
                                            @endif
                                        </td>
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

@section('scripts')
<script>
(function ($) {
    var listUrl = @json(route('admin.budget.hotels.occupancy-batch-hotels', $region->slug));
    var occupancyUrlTpl = @json(url('/admin/budget/hotels/'.$region->slug.'/hotel/__ID__/occupancy'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var running = false;

    function logLine(html, cls) {
        $('#batch-123-log').prepend('<li class="' + (cls || '') + '">' + html + '</li>');
    }

    function setProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        $('#batch-123-bar').css('width', pct + '%').text(pct + '%');
    }

    function occupancyUrl(hotelId) {
        return occupancyUrlTpl.replace('__ID__', String(hotelId));
    }

    function fetchCell(hotelId, key) {
        return $.ajax({
            url: occupancyUrl(hotelId),
            method: 'POST',
            timeout: 180000,
            headers: { 'X-CSRF-TOKEN': csrf },
            data: { key: key, skip_filled: 1 },
            dataType: 'json'
        });
    }

    $('#btn-batch-123').on('click', function () {
        if (running) return;
        if (!confirm('Прогнать всё по галочкам из настроек?\nУже залитые ячейки будут пропущены.')) {
            return;
        }

        running = true;
        var btn = $(this).prop('disabled', true);
        $('#batch-123-wrap').show();
        $('#batch-123-log').empty();
        $('#batch-123-status').text('Загрузка списка отелей…');
        setProgress(0, 1);

        $.getJSON(listUrl).done(function (res) {
            if (!res || !res.ok || !Array.isArray(res.hotels)) {
                $('#batch-123-status').text('Не удалось получить список отелей');
                running = false;
                btn.prop('disabled', false);
                return;
            }

            var keys = res.keys || [];
            if (!keys.length) {
                $('#batch-123-status').text('В настройках не выбрано ни одной ячейки');
                running = false;
                btn.prop('disabled', false);
                return;
            }
            var hotels = res.hotels;
            var queue = [];
            hotels.forEach(function (h) {
                keys.forEach(function (k) {
                    queue.push({ id: h.id, title: h.title, key: k });
                });
            });

            var total = queue.length;
            var i = 0;
            var ok = 0;
            var skipped = 0;
            var fail = 0;

            if (!total) {
                $('#batch-123-status').text('Нет отелей с hotel_identifier');
                running = false;
                btn.prop('disabled', false);
                return;
            }

            $('#batch-123-status').text('Отелей: ' + hotels.length + ', ячеек: ' + keys.length + ', задач: ' + total);

            function next() {
                if (i >= total) {
                    setProgress(total, total);
                    $('#batch-123-status').text('Готово. ok=' + ok + ', пропущено=' + skipped + ', ошибок=' + fail);
                    logLine('<strong>Финиш</strong>: ok=' + ok + ', skip=' + skipped + ', fail=' + fail, 'text-success');
                    running = false;
                    btn.prop('disabled', false);
                    return;
                }

                var job = queue[i];
                $('#batch-123-status').text((i + 1) + '/' + total + ' — ' + job.title + ' / ' + job.key);
                setProgress(i, total);

                fetchCell(job.id, job.key).done(function (r) {
                    if (!r || !r.ok) {
                        fail++;
                        logLine(job.title + ' / ' + job.key + ': ' + ((r && r.message) || 'ошибка'), 'text-danger');
                    } else if (r.skipped) {
                        skipped++;
                    } else if (r.cell && r.cell.price != null) {
                        ok++;
                        logLine(job.title + ' / ' + job.key + ': $' + Math.round(r.cell.price), 'text-success');
                    } else {
                        fail++;
                        logLine(job.title + ' / ' + job.key + ': ' + ((r.cell && r.cell.error) || 'нет цены'), 'text-warning');
                    }
                }).fail(function (xhr) {
                    fail++;
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'ошибка запроса';
                    logLine(job.title + ' / ' + job.key + ': ' + msg, 'text-danger');
                }).always(function () {
                    i++;
                    next();
                });
            }

            next();
        }).fail(function () {
            $('#batch-123-status').text('Ошибка загрузки списка отелей');
            running = false;
            btn.prop('disabled', false);
        });
    });
})(jQuery);
</script>
@endsection