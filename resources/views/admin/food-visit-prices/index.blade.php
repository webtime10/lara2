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
                        <li class="breadcrumb-item active">{{ $typeLabel }}</li>
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
                            <label>Модель для получения среднего чека</label>
                            <select id="foodVisitAiModel" class="form-control">
                                @foreach ($aiModelLabels as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}" @selected($modelKey === $defaultAiModel)>
                                        {{ $modelLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <button type="button" id="foodVisitSyncAllBtn" class="btn btn-warning">
                                Получить все цены
                            </button>
                        </div>
                    </div>

                    <div id="foodVisitProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="foodVisitProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" style="width: 0%;">0%</div>
                        </div>
                        <p id="foodVisitStatus" class="mb-2 text-muted">Подготовка...</p>
                        <ul id="foodVisitLog" class="list-unstyled mb-0" style="max-height: 220px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Регион</th>
                                <th style="width: 120px;">Сырьё</th>
                                <th style="width: 170px;">Средний чек взрослого $</th>
                                <th style="width: 170px;">Средний чек ребёнка $</th>
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
                                    /** @var \App\Models\FoodVisitPrice|null $price */
                                    $price = $row['price'];
                                    $hasPrices = $price && $price->adult_avg_price !== null;
                                @endphp
                                <tr>
                                    <td>{{ $region->label }}</td>
                                    <td>{{ $row['places_count'] }} мест</td>
                                    <td>
                                        @if ($price?->adult_avg_price !== null)
                                            <strong>${{ number_format((float) $price->adult_avg_price, 2, '.', ' ') }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($price?->child_avg_price !== null)
                                            <strong>${{ number_format((float) $price->child_avg_price, 2, '.', ' ') }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($hasPrices)
                                            <span class="text-success font-weight-bold">✓ получено</span>
                                            <br><small class="text-muted">{{ $price->last_checked ? $price->last_checked->format('d.m.Y H:i') : '—' }}</small>
                                        @else
                                            <span class="text-danger font-weight-bold">✗ пусто</span>
                                        @endif
                                    </td>
                                    <td>{{ $price?->ai_model ? ($aiModelLabels[$price->ai_model] ?? $price->ai_model) : '—' }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-warning js-food-visit-region"
                                            data-slug="{{ $region->slug }}"
                                            data-label="{{ $region->label }}"
                                            data-url="{{ route('admin.food-visit-prices.refresh', [$type, $region->slug]) }}"
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
    var allBtn = document.getElementById('foodVisitSyncAllBtn');
    var modelEl = document.getElementById('foodVisitAiModel');
    var progressBox = document.getElementById('foodVisitProgress');
    var progressBar = document.getElementById('foodVisitProgressBar');
    var statusEl = document.getElementById('foodVisitStatus');
    var logEl = document.getElementById('foodVisitLog');
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
                addLog('✓ ' + region.label + ' — взрослый $' + res.json.adult_avg_price + ', ребёнок $' + res.json.child_avg_price, true);
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
        if (!confirm('Получить средний чек через ' + selectedModelLabel() + ' по всем кантонам?')) return;

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
                    ? 'Готово: все цены сохранены. Обновляем страницу...'
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

    document.querySelectorAll('.js-food-visit-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (running || button.disabled) return;
            var region = {
                slug: button.dataset.slug,
                label: button.dataset.label,
                url: button.dataset.url,
            };
            if (!confirm('Получить/обновить средний чек через ' + selectedModelLabel() + ' для ' + region.label + '?')) return;

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
