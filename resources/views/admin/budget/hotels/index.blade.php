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
                        <li class="breadcrumb-item">Бюджет</li>
                        <li class="breadcrumb-item active">Отели</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <button
                        type="button"
                        id="swissHotelsSyncAllBtn"
                        class="btn btn-primary btn-lg"
                    >
                        <span id="swissHotelsSyncAllBtnTitle">Залить все кантоны</span>
                        <br>
                        <small id="swissHotelsSyncAllBtnDate" class="font-weight-normal">
                            @if ($lastFullSyncAt)
                                Последняя заливка: {{ $lastFullSyncAt->format('d.m.Y H:i') }}
                            @else
                                Ещё не заливали
                            @endif
                        </small>
                    </button>

                    <button
                        type="button"
                        id="swissHotelsOccupancyAllBtn"
                        class="btn btn-success btn-lg ml-2"
                    >
                        Прогнать всё
                        <br>
                        <small class="font-weight-normal">по составам гостей (дозалить с места обрыва)</small>
                    </button>
                    <button
                        type="button"
                        id="swissHotelsOccupancyStopBtn"
                        class="btn btn-outline-danger btn-lg ml-2"
                        style="display:none;"
                    >
                        Остановить
                    </button>

                    <div id="swissHotelsSyncProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="swissHotelsSyncBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%;">0%</div>
                        </div>
                        <p id="swissHotelsSyncStatus" class="mb-2 text-muted">Подготовка...</p>
                        <p id="swissHotelsOccupancyKeys" class="mb-2 small text-muted"></p>
                        <ul id="swissHotelsSyncLog" class="list-unstyled mb-0" style="max-height: 220px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Кантоны Швейцарии</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Выберите кантон — откроется таблица из базы. Для отдельной дозаливки используйте кнопку рядом с кантоном.
                        Сетку occupancy для карточек отелей настраивайте в
                        <a href="{{ route('admin.budget.hotels.settings') }}">Отели — настройки</a>.
                    </p>
                    <div class="row" id="swissRegionsGrid">
                        @foreach ($regions as $region)
                            <div class="col-md-3 col-sm-4 col-6 mb-2" data-region-slug="{{ $region->slug }}">
                                <div class="border rounded p-2 h-100">
                                    <a href="{{ route('admin.budget.hotels.show', $region->slug) }}" class="d-block font-weight-bold mb-1">
                                        {{ $region->label }}
                                    </a>
                                    <div class="small text-muted mb-2">
                                        <span class="region-hotels-count">{{ $region->hotels_count }} в БД</span>
                                        <br>
                                        <span class="region-hotels-date">
                                            @if ($region->hotels_synced_at)
                                                {{ $region->hotels_synced_at->format('d.m.Y H:i') }}
                                            @else
                                                не заливали
                                            @endif
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary btn-block js-sync-hotel-region"
                                        data-slug="{{ $region->slug }}"
                                        data-label="{{ $region->label }}"
                                        data-url="{{ route('admin.budget.hotels.sync', $region->slug) }}"
                                    >
                                        Дозалить
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
    var btn = document.getElementById('swissHotelsSyncAllBtn');
    var btnDate = document.getElementById('swissHotelsSyncAllBtnDate');
    var progressBox = document.getElementById('swissHotelsSyncProgress');
    var progressBar = document.getElementById('swissHotelsSyncBar');
    var statusEl = document.getElementById('swissHotelsSyncStatus');
    var logEl = document.getElementById('swissHotelsSyncLog');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var regions = @json($regions->map(fn ($r) => ['slug' => $r->slug, 'label' => $r->label])->values());
    var syncUrlBase = @json(url('/admin/budget/hotels/sync'));
    var completeUrl = @json(route('admin.budget.hotels.sync-all.complete', [], false));
    var occupancyPlanUrl = @json(route('admin.budget.hotels.occupancy-batch-all'));
    var occupancyUrlBase = @json(url('/admin/budget/hotels'));
    var running = false;
    var occupancyRunning = false;
    var occupancyStop = false;

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: '{}',
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); });
    }

    function postOccupancy(slug, hotelId, key) {
        var url = occupancyUrlBase + '/' + encodeURIComponent(slug) + '/hotel/' + hotelId + '/occupancy';
        var body = new URLSearchParams();
        body.set('key', key);
        body.set('skip_filled', '1');
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); });
    }

    function addLog(text, ok) {
        var li = document.createElement('li');
        li.className = ok ? 'text-success' : 'text-danger';
        li.textContent = text;
        logEl.appendChild(li);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function updateRegionCount(slug, count) {
        var cell = document.querySelector('[data-region-slug="' + slug + '"] .region-hotels-count');
        if (cell) {
            cell.textContent = count + ' в БД';
        }
    }

    function updateRegionDate(slug, date) {
        var cell = document.querySelector('[data-region-slug="' + slug + '"] .region-hotels-date');
        if (cell && date) {
            cell.textContent = date;
        }
    }

    function setProgress(done, total) {
        var pct = total ? Math.round((done / total) * 100) : 0;
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
    }

    var occBtn = document.getElementById('swissHotelsOccupancyAllBtn');
    var occStopBtn = document.getElementById('swissHotelsOccupancyStopBtn');
    var occKeysEl = document.getElementById('swissHotelsOccupancyKeys');

    occStopBtn.addEventListener('click', function () {
        if (!occupancyRunning) return;
        occupancyStop = true;
        occStopBtn.disabled = true;
        statusEl.textContent = 'Останавливаю после текущей ячейки…';
    });

    occBtn.addEventListener('click', function () {
        if (running || occupancyRunning) return;
        if (!confirm('Прогнать незалитые ячейки по всем кантонам?\nСоставы — из настроек «по посетителям». Уже залитое пропускается.')) {
            return;
        }

        occupancyRunning = true;
        occupancyStop = false;
        occBtn.disabled = true;
        btn.disabled = true;
        occStopBtn.style.display = 'inline-block';
        occStopBtn.disabled = false;
        logEl.innerHTML = '';
        occKeysEl.textContent = '';
        progressBox.style.display = 'block';
        progressBar.classList.add('progress-bar-animated', 'bg-success');
        progressBar.classList.remove('bg-primary');
        statusEl.textContent = 'Считаю незалитые ячейки по составам…';
        setProgress(0, 1);

        fetch(occupancyPlanUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || !res.ok) {
                statusEl.textContent = 'Не удалось получить план';
                occupancyRunning = false;
                occBtn.disabled = false;
                btn.disabled = false;
                occStopBtn.style.display = 'none';
                return;
            }

            var keys = res.keys || [];
            var labels = res.key_labels || {};
            var jobs = res.jobs || [];
            var stats = res.stats || { done: 0, pending: 0, total: 0, hotels: 0 };

            occKeysEl.textContent = 'Составы: ' + keys.map(function (k) { return labels[k] || k; }).join(' · ');

            if (!keys.length) {
                statusEl.textContent = 'В настройках не выбрано ни одной ячейки';
                occupancyRunning = false;
                occBtn.disabled = false;
                btn.disabled = false;
                occStopBtn.style.display = 'none';
                return;
            }

            if (!jobs.length) {
                setProgress(1, 1);
                statusEl.textContent = 'Уже всё залито: ' + stats.done + '/' + stats.total;
                addLog('Нечего дозаливать', true);
                occupancyRunning = false;
                occBtn.disabled = false;
                btn.disabled = false;
                occStopBtn.style.display = 'none';
                return;
            }

            var total = jobs.length;
            var i = 0;
            var ok = 0;
            var fail = 0;

            statusEl.textContent = 'Уже было: ' + stats.done + '/' + stats.total + '. Осталось: ' + total;
            addLog('Продолжаю с незалитых: ' + total + ' задач', true);

            function next() {
                if (occupancyStop) {
                    setProgress(i, total);
                    statusEl.textContent = 'Остановлено. ok=' + ok + ', fail=' + fail + ', осталось=' + (total - i);
                    addLog('Стоп на ' + i + '/' + total, false);
                    occupancyRunning = false;
                    occupancyStop = false;
                    occBtn.disabled = false;
                    btn.disabled = false;
                    occStopBtn.style.display = 'none';
                    return;
                }

                if (i >= total) {
                    setProgress(total, total);
                    statusEl.textContent = 'Готово. ok=' + ok + ', fail=' + fail;
                    addLog('Финиш: ok=' + ok + ', fail=' + fail, true);
                    occupancyRunning = false;
                    occBtn.disabled = false;
                    btn.disabled = false;
                    occStopBtn.style.display = 'none';
                    return;
                }

                var job = jobs[i];
                var label = labels[job.key] || job.key;
                statusEl.textContent = (stats.done + i + 1) + '/' + stats.total + ' — ' + job.region_label + ' / ' + job.title + ' / ' + label;
                setProgress(i, total);

                postOccupancy(job.region_slug, job.hotel_id, job.key).then(function (res) {
                    if (res.ok && res.json && res.json.ok && res.json.cell && res.json.cell.price != null) {
                        ok++;
                        if (!res.json.skipped) {
                            addLog(job.region_label + ' / ' + job.title + ' / ' + job.key + ' — $' + Math.round(res.json.cell.price), true);
                        }
                    } else {
                        fail++;
                        var msg = (res.json && (res.json.message || (res.json.cell && res.json.cell.error))) || 'ошибка';
                        addLog(job.region_label + ' / ' + job.title + ' / ' + job.key + ' — ' + msg, false);
                    }
                }).catch(function () {
                    fail++;
                    addLog(job.region_label + ' / ' + job.title + ' / ' + job.key + ' — сеть', false);
                }).finally(function () {
                    i++;
                    next();
                });
            }

            next();
        }).catch(function () {
            statusEl.textContent = 'Ошибка загрузки плана';
            occupancyRunning = false;
            occBtn.disabled = false;
            btn.disabled = false;
            occStopBtn.style.display = 'none';
        });
    });

    btn.addEventListener('click', function () {
        if (running || occupancyRunning) {
            return;
        }
        running = true;
        btn.disabled = true;
        occBtn.disabled = true;
        logEl.innerHTML = '';
        occKeysEl.textContent = '';
        progressBox.style.display = 'block';
        progressBar.classList.add('progress-bar-animated', 'bg-primary');
        progressBar.classList.remove('bg-success');
        setProgress(0, regions.length);

        var index = 0;
        var failed = 0;

        function next() {
            if (index >= regions.length) {
                statusEl.textContent = failed === 0
                    ? 'Готово: все ' + regions.length + ' кантонов залиты.'
                    : 'Завершено с ошибками: ' + failed + ' из ' + regions.length;

                if (failed === 0) {
                    postJson(completeUrl).then(function (res) {
                        if (res.ok && res.json && res.json.last_full_sync_at) {
                            btnDate.textContent = 'Последняя заливка: ' + res.json.last_full_sync_at;
                        }
                    });
                }

                progressBar.classList.remove('progress-bar-animated');
                btn.disabled = false;
                occBtn.disabled = false;
                running = false;
                return;
            }

            var region = regions[index];
            var step = index + 1;
            statusEl.textContent = 'Загрузка: ' + region.label + ' (' + step + ' / ' + regions.length + ')';
            var url = syncUrlBase + '/' + encodeURIComponent(region.slug);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ ' + region.label + ' — ' + res.json.count + ' отелей', true);
                    updateRegionCount(region.slug, res.json.count);
                    updateRegionDate(region.slug, res.json.synced_at);
                } else {
                    failed++;
                    var msg = (res.json && res.json.message) ? res.json.message : 'Ошибка';
                    addLog('✗ ' + region.label + ' — ' + msg, false);
                }
            }).catch(function () {
                failed++;
                addLog('✗ ' + region.label + ' — сеть', false);
            }).finally(function () {
                index++;
                setProgress(index, regions.length);
                next();
            });
        }

        next();
    });

    document.querySelectorAll('.js-sync-hotel-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled || running || occupancyRunning) {
                return;
            }

            var slug = button.getAttribute('data-slug');
            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');
            var originalText = button.textContent;

            button.disabled = true;
            button.textContent = 'Заливаем...';
            progressBox.style.display = 'block';
            statusEl.textContent = 'Дозаливка: ' + label;

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ ' + label + ' — ' + res.json.count + ' отелей', true);
                    updateRegionCount(slug, res.json.count);
                    updateRegionDate(slug, res.json.synced_at);
                } else {
                    addLog('✗ ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                }
            }).catch(function () {
                addLog('✗ ' + label + ' — сеть', false);
            }).finally(function () {
                button.disabled = false;
                button.textContent = originalText;
                statusEl.textContent = 'Готово';
            });
        });
    });
})();
</script>
@endsection
