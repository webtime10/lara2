@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Бюджет</li>
                        <li class="breadcrumb-item active">Цены авто</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label>Модель для получения цен авто</label>
                            <select id="carRentalAiModel" class="form-control">
                                @foreach ($aiModelLabels as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}" @selected($modelKey === $defaultAiModel)>
                                        {{ $modelLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <button type="button" id="carRentalSyncAllBtn" class="btn btn-warning">
                                Получить все цены
                            </button>
                            <button type="button" id="carRentalClearAllBtn" class="btn btn-danger ml-2" data-url="{{ route('admin.car-rental-prices.clear-all') }}">
                                Очистить всё
                            </button>
                        </div>
                    </div>

                    <div id="carRentalProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="carRentalProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" style="width: 0%;">0%</div>
                        </div>
                        <p id="carRentalStatus" class="mb-2 text-muted">Подготовка...</p>
                        <ul id="carRentalLog" class="list-unstyled mb-0" style="max-height: 220px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Регион</th>
                                @foreach ($carClasses as $class)
                                    <th style="width: 150px;">{{ $carClassLabels[$class] ?? $class }} $/день</th>
                                @endforeach
                                <th style="width: 150px;">Статус</th>
                                <th style="width: 220px;">Модель</th>
                                <th style="width: 170px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    /** @var \App\Models\SwissRegion $region */
                                    $region = $row['region'];
                                    /** @var \Illuminate\Support\Collection $prices */
                                    $prices = $row['prices'];
                                    $filled = 0;
                                    $lastChecked = null;
                                    $model = null;
                                    foreach ($carClasses as $class) {
                                        $price = $prices->get($class);
                                        if ($price && $price->daily_price !== null) {
                                            $filled++;
                                            $lastChecked = $price->last_checked;
                                            $model = $price->ai_model ?: $model;
                                        }
                                    }
                                    $hasPrices = $filled === count($carClasses);
                                @endphp
                                <tr>
                                    <td>{{ $region->label }}</td>
                                    @foreach ($carClasses as $class)
                                        @php $price = $prices->get($class); @endphp
                                        <td>
                                            @if ($price?->daily_price !== null)
                                                <strong>${{ number_format((float) $price->daily_price, 2, '.', ' ') }}</strong>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @if ($hasPrices)
                                            <span class="text-success font-weight-bold">✓ получено</span>
                                            <br><small class="text-muted">{{ $lastChecked ? $lastChecked->format('d.m.Y H:i') : '—' }}</small>
                                        @else
                                            <span class="text-danger font-weight-bold">✗ пусто</span>
                                            @if ($filled > 0)
                                                <br><small class="text-muted">заполнено {{ $filled }} / {{ count($carClasses) }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $model ? ($aiModelLabels[$model] ?? $model) : '—' }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-warning js-car-rental-region"
                                            data-slug="{{ $region->slug }}"
                                            data-label="{{ $region->label }}"
                                            data-url="{{ route('admin.car-rental-prices.refresh', $region->slug) }}"
                                        >
                                            {{ $hasPrices ? 'Обновить' : 'Получить' }}
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
    var allBtn = document.getElementById('carRentalSyncAllBtn');
    var clearAllBtn = document.getElementById('carRentalClearAllBtn');
    var modelEl = document.getElementById('carRentalAiModel');
    var progressBox = document.getElementById('carRentalProgress');
    var progressBar = document.getElementById('carRentalProgressBar');
    var statusEl = document.getElementById('carRentalStatus');
    var logEl = document.getElementById('carRentalLog');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var regions = @json($regionsPayload);
    var running = false;

    function selectedModel() {
        return modelEl ? modelEl.value : @json($defaultAiModel);
    }

    function selectedModelLabel() {
        return modelEl && modelEl.options[modelEl.selectedIndex]
            ? modelEl.options[modelEl.selectedIndex].text.trim()
            : 'AI';
    }

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ai_model: selectedModel() }),
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, json: j };
            });
        });
    }

    function setProgress(done, total) {
        var pct = total ? Math.round((done / total) * 100) : 0;
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

    function reloadSoon() {
        window.setTimeout(function () {
            window.location.reload();
        }, 1200);
    }

    function pricesText(prices) {
        if (!Array.isArray(prices)) {
            return '';
        }

        return prices.map(function (price) {
            return price.car_class + ' $' + price.daily_price;
        }).join(', ');
    }

    function runRegion(region, button) {
        var oldText = button ? button.textContent : null;
        if (button) {
            button.disabled = true;
            button.textContent = selectedModelLabel() + '...';
        }

        progressBox.style.display = 'block';
        statusEl.textContent = selectedModelLabel() + ': ' + region.label;

        return postJson(region.url).then(function (res) {
            if (res.ok && res.json && res.json.ok) {
                addLog('✓ ' + region.label + ' — ' + pricesText(res.json.prices), true);
                return true;
            }

            addLog('✗ ' + region.label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
            return false;
        }).catch(function () {
            addLog('✗ ' + region.label + ' — сеть', false);
            return false;
        }).finally(function () {
            if (button) {
                button.disabled = false;
                button.textContent = oldText;
            }
        });
    }

    allBtn?.addEventListener('click', function () {
        if (running) return;
        if (!confirm('Получить дневные цены авто через ' + selectedModelLabel() + ' по всем кантонам?')) return;

        running = true;
        allBtn.disabled = true;
        logEl.innerHTML = '';
        progressBox.style.display = 'block';
        progressBar.classList.add('progress-bar-animated');
        setProgress(0, regions.length);

        var index = 0;
        var failed = 0;

        function next() {
            if (index >= regions.length) {
                statusEl.textContent = failed === 0
                    ? 'Готово: все цены авто сохранены. Обновляем страницу...'
                    : 'Завершено с ошибками: ' + failed + ' из ' + regions.length;
                progressBar.classList.remove('progress-bar-animated');
                allBtn.disabled = false;
                running = false;

                if (failed === 0) {
                    reloadSoon();
                }
                return;
            }

            var region = regions[index];
            statusEl.textContent = selectedModelLabel() + ': ' + region.label + ' (' + (index + 1) + ' / ' + regions.length + ')';
            runRegion(region, null).then(function (ok) {
                if (!ok) failed++;
            }).finally(function () {
                index++;
                setProgress(index, regions.length);
                next();
            });
        }

        next();
    });

    clearAllBtn?.addEventListener('click', function () {
        if (running || clearAllBtn.disabled) return;
        if (!confirm('Очистить все цены авто по всем кантонам?')) return;

        clearAllBtn.disabled = true;
        logEl.innerHTML = '';
        progressBox.style.display = 'block';
        statusEl.textContent = 'Очищаем цены авто...';
        setProgress(0, 1);

        postJson(clearAllBtn.dataset.url).then(function (res) {
            if (res.ok && res.json && res.json.ok) {
                addLog('✓ Очищено записей: ' + res.json.deleted, true);
                statusEl.textContent = 'Готово: цены авто очищены. Обновляем страницу...';
                setProgress(1, 1);
                reloadSoon();
            } else {
                addLog('✗ Очистка — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                statusEl.textContent = 'Ошибка очистки цен авто';
            }
        }).catch(function () {
            addLog('✗ Очистка — сеть', false);
            statusEl.textContent = 'Ошибка сети при очистке';
        }).finally(function () {
            clearAllBtn.disabled = false;
        });
    });

    document.querySelectorAll('.js-car-rental-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (running || button.disabled) return;
            var region = {
                slug: button.dataset.slug,
                label: button.dataset.label,
                url: button.dataset.url,
            };
            if (!confirm('Получить/обновить цены авто через ' + selectedModelLabel() + ' для ' + region.label + '?')) return;

            logEl.innerHTML = '';
            setProgress(0, 1);
            runRegion(region, button).then(function (ok) {
                setProgress(1, 1);
                if (ok) {
                    statusEl.textContent = 'Готово: ' + region.label + '. Обновляем страницу...';
                    reloadSoon();
                } else {
                    statusEl.textContent = 'Ошибка: ' + region.label;
                }
            });
        });
    });
})();
</script>
@endsection
