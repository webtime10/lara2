@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.weather.index') }}">Погода</a></li>
                        <li class="breadcrumb-item active">Швейцария</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (! $tableExists || ! $runsExist)
                <div class="alert alert-warning">
                    Нужны таблицы <code>weather_month_stats</code> / <code>weather_sync_runs</code>. Запустите миграции Laravel.
                </div>
            @endif

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label>Модель</label>
                            <p class="form-control-plaintext mb-0">
                                <strong>{{ $aiModelLabels[$defaultAiModel] ?? 'Gemini 2.5 Flash бесплатный' }}</strong>
                            </p>
                            <small class="form-text text-muted">
                                Промт:
                                <a href="{{ route('admin.prompts-wp.weather') }}">Промты → Швейцария → Погода</a>.
                                Очередь: 1 клетка = 1 джоб, паузы {{ \App\Services\WeatherSyncDispatcher::staggerLabel() }} между джобами, авто — <strong>1-го числа в 03:00</strong>.
                            </small>
                            @if ($lastScheduledSuccess)
                                <p class="mb-0 mt-2">
                                    <span class="badge badge-success">
                                        Автозапуск сработал {{ $lastScheduledSuccess->finished_at?->format('d.m.Y H:i') }}
                                    </span>
                                    <span class="text-muted small ml-1">
                                        ok={{ $lastScheduledSuccess->succeeded }},
                                        fail={{ $lastScheduledSuccess->failed }},
                                        skip={{ $lastScheduledSuccess->skipped }}
                                        / {{ $lastScheduledSuccess->total }}
                                    </span>
                                </p>
                            @else
                                <p class="mb-0 mt-2 text-muted small">
                                    Автозапуск по расписанию ещё не срабатывал (нужен <code>php artisan schedule:work</code> или cron).
                                </p>
                            @endif
                        </div>
                        <div class="col-md-7">
                            <button type="button" id="weatherQueueEmptyBtn" class="btn btn-success" @disabled(! $tableExists || ! $runsExist)>
                                Дозалить
                            </button>
                            <button type="button" id="weatherQueueForceBtn" class="btn btn-outline-warning ml-1" @disabled(! $tableExists || ! $runsExist)>
                                Обновить всё
                            </button>
                            <button type="button" id="weatherClearAllBtn" class="btn btn-danger ml-2" data-url="{{ route('admin.weather.clear-all', [], false) }}" @disabled(! $tableExists)>
                                Очистить всё
                            </button>
                            <small class="form-text text-muted mt-1">
                                <strong>Дозалить</strong> — только пустые («—»). <strong>Обновить всё</strong> — перезаписать все клетки.
                            </small>
                        </div>
                    </div>

                    <p class="mb-0 mt-2 text-muted small">
                        Воркер: <code>php artisan queue:work --queue=weather</code>
                        · планировщик: <code>php artisan schedule:work</code> (или cron <code>* * * * * php artisan schedule:run</code>)
                    </p>

                    <div id="weatherProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="weatherProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" style="width: 0%;">0%</div>
                        </div>
                        <p id="weatherStatus" class="mb-2 text-muted">Подготовка...</p>
                        <ul id="weatherLog" class="list-unstyled mb-0" style="max-height: 260px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Регионы × месяцы</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered table-sm mb-0 text-center">
                        <thead>
                            <tr>
                                <th class="text-left" style="min-width: 220px; position: sticky; left: 0; background: #f4f6f9; z-index: 1;">Регион (RU)</th>
                                <th class="text-left" style="min-width: 140px;">AR</th>
                                @foreach ($months as $month)
                                    <th style="min-width: 88px;">{{ $monthNames[$month] ?? $month }}</th>
                                @endforeach
                                <th style="min-width: 180px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $canton = $row['canton'];
                                    $stats = $row['stats'];
                                    $filled = (int) $row['filled'];
                                    $missing = 12 - $filled;
                                    $regionQueueUrl = route('admin.weather.queue-region', $canton['slug'], false);
                                @endphp
                                <tr>
                                    <td class="text-left font-weight-bold" style="position: sticky; left: 0; background: #fff; z-index: 1;">
                                        {{ $canton['name_ru'] }}
                                        <br>
                                        <small class="{{ $filled > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $filled > 0 ? 'заполнено: '.$filled.'/12' : 'пусто' }}
                                            @if ($missing > 0 && $filled > 0)
                                                · пусто: {{ $missing }}
                                            @endif
                                        </small>
                                    </td>
                                    <td class="text-left text-muted" style="direction: rtl;">{{ $canton['name_ar'] }}</td>
                                    @foreach ($months as $month)
                                        @php
                                            /** @var \App\Models\WeatherMonthStat|null $stat */
                                            $stat = $stats->get($month);
                                        @endphp
                                        <td>
                                            @if ($stat && $stat->isFilled())
                                                <span
                                                    class="d-inline-block"
                                                    style="font-size: 0.72rem; line-height: 1.25;"
                                                    title="t: {{ $stat->average_temperature }}&#10;осадки: {{ $stat->precipitation }}&#10;солнце: {{ $stat->sunny_days }}&#10;сезон: {{ $stat->season }}"
                                                >
                                                    <strong>{{ $stat->average_temperature }}</strong>
                                                    <br><span class="text-muted">{{ $stat->season }}</span>
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-nowrap">
                                        @if ($missing > 0)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success js-weather-region mb-1"
                                                data-slug="{{ $canton['slug'] }}"
                                                data-label="{{ $canton['name_ru'] }}"
                                                data-url="{{ $regionQueueUrl }}"
                                                data-force="0"
                                                @disabled(! $tableExists || ! $runsExist)
                                            >
                                                Дозалить
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-warning js-weather-region mb-1"
                                            data-slug="{{ $canton['slug'] }}"
                                            data-label="{{ $canton['name_ru'] }}"
                                            data-url="{{ $regionQueueUrl }}"
                                            data-force="1"
                                            @disabled(! $tableExists || ! $runsExist)
                                        >
                                            Обновить
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
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
    var queueEmptyBtn = document.getElementById('weatherQueueEmptyBtn');
    var queueForceBtn = document.getElementById('weatherQueueForceBtn');
    var clearAllBtn = document.getElementById('weatherClearAllBtn');
    var progressBox = document.getElementById('weatherProgress');
    var progressBar = document.getElementById('weatherProgressBar');
    var statusEl = document.getElementById('weatherStatus');
    var logEl = document.getElementById('weatherLog');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var queueAllUrl = @json($queueAllUrl);
    var statusUrl = @json($statusUrl);
    var activeRun = @json($activeRun ? [
        'uuid' => $activeRun->uuid,
    ] : null);
    var pollTimer = null;
    var currentUuid = activeRun ? activeRun.uuid : null;
    var lastLogCount = 0;

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, json: j };
            });
        });
    }

    function getJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, json: j };
            });
        });
    }

    function setProgress(pct) {
        pct = Math.max(0, Math.min(100, pct || 0));
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
    }

    function addLog(text, ok) {
        var li = document.createElement('li');
        li.className = ok ? 'text-success' : 'text-danger';
        li.textContent = text;
        logEl.appendChild(li);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function renderLogs(logs) {
        if (!Array.isArray(logs)) return;
        if (logs.length < lastLogCount) {
            logEl.innerHTML = '';
            lastLogCount = 0;
        }
        for (var i = lastLogCount; i < logs.length; i++) {
            var item = logs[i];
            addLog((item.at ? '[' + item.at + '] ' : '') + item.text, !!item.ok);
        }
        lastLogCount = logs.length;
    }

    function showProgressBox() {
        progressBox.style.display = 'block';
        try {
            progressBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {}
    }

    function applyRun(run) {
        if (!run) return;
        showProgressBox();
        setProgress(run.percent || 0);
        statusEl.textContent = (run.last_message || run.status)
            + ' · ok=' + run.succeeded
            + ' skip=' + run.skipped
            + ' fail=' + run.failed
            + ' / ' + run.total;
        renderLogs(run.logs || []);
        if (run.finished) {
            progressBar.classList.remove('progress-bar-animated');
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            if (run.failed === 0) {
                statusEl.textContent = 'Готово. Обновляем страницу...';
                window.setTimeout(function () { window.location.reload(); }, 1500);
            }
        } else {
            progressBar.classList.add('progress-bar-animated');
        }
    }

    function startPolling(uuid) {
        currentUuid = uuid;
        lastLogCount = 0;
        logEl.innerHTML = '';
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            getJson(statusUrl + '?uuid=' + encodeURIComponent(currentUuid)).then(function (res) {
                if (res.ok && res.json && res.json.run) {
                    applyRun(res.json.run);
                } else {
                    statusEl.textContent = 'Ошибка опроса статуса'
                        + (res.json && res.json.message ? (': ' + res.json.message) : '');
                }
            }).catch(function () {
                statusEl.textContent = 'Ошибка сети при опросе статуса';
            });
        }, 2500);
    }

    function queueStart(force, url) {
        showProgressBox();
        progressBar.classList.add('progress-bar-animated');
        setProgress(0);
        statusEl.textContent = 'Ставим задачи в очередь...';
        lastLogCount = 0;
        logEl.innerHTML = '';

        postJson(url || queueAllUrl, { force: !!force }).then(function (res) {
            if (res.ok && res.json && res.json.ok) {
                if (res.json.hint) addLog(res.json.hint, true);
                applyRun(res.json.run);
                if (res.json.run && !res.json.run.finished) {
                    startPolling(res.json.run.uuid);
                }
            } else {
                addLog((res.json && res.json.message) ? res.json.message : 'Ошибка постановки', false);
                statusEl.textContent = 'Ошибка';
            }
        }).catch(function () {
            addLog('Сеть', false);
            statusEl.textContent = 'Ошибка сети';
        });
    }

    queueEmptyBtn?.addEventListener('click', function () {
        if (!confirm('Дозалить только незаполненные клетки по всем регионам?')) return;
        queueStart(false);
    });

    queueForceBtn?.addEventListener('click', function () {
        if (!confirm('Полностью обновить все клетки через очередь?')) return;
        queueStart(true);
    });

    clearAllBtn?.addEventListener('click', function () {
        if (!confirm('Очистить всю погоду?')) return;
        postJson(clearAllBtn.dataset.url, {}).then(function (res) {
            if (res.ok && res.json && res.json.ok) {
                window.location.reload();
            } else {
                alert((res.json && res.json.message) || 'Ошибка очистки');
            }
        });
    });

    document.querySelectorAll('.js-weather-region').forEach(function (button) {
        button.addEventListener('click', function () {
            var force = String(button.dataset.force || '1') === '1';
            var msg = force
                ? ('Обновить все 12 месяцев для ' + button.dataset.label + '?')
                : ('Дозалить только пустые месяцы для ' + button.dataset.label + '?');
            if (!confirm(msg)) return;
            queueStart(force, button.dataset.url);
        });
    });

    if (currentUuid) {
        startPolling(currentUuid);
        getJson(statusUrl + '?uuid=' + encodeURIComponent(currentUuid)).then(function (res) {
            if (res.ok && res.json && res.json.run) applyRun(res.json.run);
        });
    }
})();
</script>
@endsection
