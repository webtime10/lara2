@extends('admin.layouts.layout')

@php
    $priceLabels = \App\Models\FoodSource::priceLabels();
    $groceryPriceFields = \App\Models\FoodSource::GROCERY_PRICE_FIELDS;
@endphp

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Бюджет</li>
                        <li class="breadcrumb-item active">Цены питания</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Модель для получения цен</label>
                            <select id="foodSourcesAiModel" class="form-control">
                                @foreach ($aiModelLabels as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}" @selected($modelKey === $defaultAiModel)>
                                        {{ $modelLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Этот выбор используется для кнопок Gemini по всем кантонам и по одному кантону.
                            </small>
                        </div>
                    </div>

                    <form method="get" class="row align-items-end">
                        <div class="col-md-4">
                            <label>Регион</label>
                            <select name="region_id" class="form-control">
                                <option value="">Все регионы</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>
                                        {{ $region->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <button type="submit" class="btn btn-primary">Фильтр</button>
                            <a href="{{ route('admin.food-sources.index') }}" class="btn btn-default">Сброс</a>
                            <button type="button" id="foodSourcesGeminiAllBtn" class="btn btn-warning ml-2">
                                Получить все цены
                            </button>
                            <a href="{{ route('admin.food-sources.create') }}" class="btn btn-success float-right">Добавить</a>
                        </div>
                    </form>

                    <div id="foodSourcesGeminiProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="foodSourcesGeminiBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" style="width: 0%;">0%</div>
                        </div>
                        <p id="foodSourcesGeminiStatus" class="mb-2 text-muted">Подготовка...</p>
                        <ul id="foodSourcesGeminiLog" class="list-unstyled mb-0" style="max-height: 220px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Регион</th>
                                <th>Название</th>
                                <th style="width: 150px;">Тип</th>
                                <th>Цены</th>
                                <th style="width: 90px;">Валюта</th>
                                <th style="width: 160px;">Средняя корзина $</th>
                                <th style="width: 150px;">Статус</th>
                                <th style="width: 260px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($foodRows as $row)
                                @php
                                    /** @var \App\Models\SwissRegion $region */
                                    $region = $row['region'];
                                    /** @var \App\Models\FoodSource|null $source */
                                    $source = $row['source'];
                                    $hasPrices = false;
                                    $basketTotal = null;
                                    if ($source) {
                                        $basketTotal = 0.0;
                                        foreach ($source->activePriceFields() as $field) {
                                            if ($source->{$field} !== null) {
                                                $hasPrices = true;
                                                if (in_array($field, $groceryPriceFields, true)) {
                                                    $basketTotal += (float) $source->{$field};
                                                }
                                            }
                                        }
                                        if (! $hasPrices) {
                                            $basketTotal = null;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $source?->id ?? '—' }}</td>
                                    <td>{{ $region->label }}</td>
                                    <td>
                                        <strong>{{ $source?->name ?? 'Продуктовая корзина — '.$region->label }}</strong>
                                        @if ($source?->website)
                                            <br><small><a href="{{ $source->website }}" target="_blank" rel="noopener">{{ $source->website }}</a></small>
                                        @endif
                                    </td>
                                    <td>{{ $foodTypes[\App\Models\FoodSource::TYPE_HOME_COOKING] ?? 'Продуктовая корзина' }}</td>
                                    <td>
                                        @if ($source)
                                            @foreach ($source->activePriceFields() as $field)
                                                @if ($source->{$field} !== null)
                                                    <span class="badge badge-light">{{ $priceLabels[$field] ?? $field }}: {{ $source->{$field} }}</span>
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $source?->currency ?? 'CHF' }}</td>
                                    <td>
                                        @if ($basketTotal !== null)
                                            <strong>${{ number_format($basketTotal, 2, '.', ' ') }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($hasPrices)
                                            <span class="text-success font-weight-bold">✓ получено</span>
                                            <br><small class="text-muted">{{ $source->last_checked ? $source->last_checked->format('d.m.Y H:i') : '—' }}</small>
                                        @else
                                            <span class="text-danger font-weight-bold">✗ пусто</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($source)
                                            <a href="{{ route('admin.food-sources.edit', $source) }}" class="btn btn-sm btn-info">Изм.</a>
                                        @endif
                                        <form action="{{ route('admin.food-sources.gemini', $region->slug) }}" method="post" class="d-inline js-food-source-row-ai-form">
                                            @csrf
                                            <input type="hidden" name="ai_model" value="{{ $defaultAiModel }}">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Получить/обновить цены через выбранную модель?');">
                                                {{ $hasPrices ? 'Обновить' : 'Получить' }}
                                            </button>
                                        </form>
                                        @if ($source)
                                            <form action="{{ route('admin.food-sources.destroy', $source) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Удал.</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Нет регионов</td>
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
    var allBtn = document.getElementById('foodSourcesGeminiAllBtn');
    var progressBox = document.getElementById('foodSourcesGeminiProgress');
    var progressBar = document.getElementById('foodSourcesGeminiBar');
    var statusEl = document.getElementById('foodSourcesGeminiStatus');
    var logEl = document.getElementById('foodSourcesGeminiLog');
    var aiModelEl = document.getElementById('foodSourcesAiModel');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var regions = @json($regions->map(fn ($r) => ['slug' => $r->slug, 'label' => $r->label])->values());
    var syncUrlBase = @json(url('/admin/food-sources/gemini'));
    var running = false;

    function selectedModel() {
        return aiModelEl ? aiModelEl.value : @json($defaultAiModel);
    }

    function selectedModelLabel() {
        return aiModelEl && aiModelEl.options[aiModelEl.selectedIndex]
            ? aiModelEl.options[aiModelEl.selectedIndex].text.trim()
            : 'Gemini';
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

    function setRegionButtonsDisabled(disabled) {
        document.querySelectorAll('.js-food-gemini-region').forEach(function (button) {
            button.disabled = disabled;
        });
    }

    function reloadSoon() {
        window.setTimeout(function () {
            window.location.reload();
        }, 1200);
    }

    allBtn?.addEventListener('click', function () {
        if (running) return;
        if (!confirm('Получить цены продуктовой корзины через ' + selectedModelLabel() + ' по всем кантонам?')) return;

        running = true;
        allBtn.disabled = true;
        setRegionButtonsDisabled(true);
        logEl.innerHTML = '';
        progressBox.style.display = 'block';
        progressBar.classList.add('progress-bar-animated');
        setProgress(0, regions.length);

        var index = 0;
        var failed = 0;

        function next() {
            if (index >= regions.length) {
                statusEl.textContent = failed === 0
                    ? 'Готово: цены по всем кантонам сохранены. Обновляем страницу...'
                    : 'Завершено с ошибками: ' + failed + ' из ' + regions.length;
                progressBar.classList.remove('progress-bar-animated');
                allBtn.disabled = false;
                setRegionButtonsDisabled(false);
                running = false;

                if (failed === 0) {
                    reloadSoon();
                }
                return;
            }

            var region = regions[index];
            var step = index + 1;
            statusEl.textContent = selectedModelLabel() + ': ' + region.label + ' (' + step + ' / ' + regions.length + ')';
            var url = syncUrlBase + '/' + encodeURIComponent(region.slug);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ ' + region.label + ' — ' + selectedModelLabel() + ', сохранено цен: ' + res.json.prices_count, true);
                } else {
                    failed++;
                    addLog('✗ ' + region.label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
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

    document.querySelectorAll('.js-food-gemini-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (running || button.disabled) return;
            if (!confirm('Получить цены продуктовой корзины через ' + selectedModelLabel() + ' для ' + button.dataset.label + '?')) return;

            var oldText = button.textContent;
            button.disabled = true;
            button.textContent = selectedModelLabel() + '...';
            progressBox.style.display = 'block';
            logEl.innerHTML = '';
            statusEl.textContent = selectedModelLabel() + ': ' + button.dataset.label;
            setProgress(0, 1);

            postJson(button.dataset.url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ ' + button.dataset.label + ' — ' + selectedModelLabel() + ', сохранено цен: ' + res.json.prices_count, true);
                    statusEl.textContent = 'Готово: ' + button.dataset.label + '. Обновляем страницу...';
                    setProgress(1, 1);
                    reloadSoon();
                } else {
                    addLog('✗ ' + button.dataset.label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                    statusEl.textContent = 'Ошибка: ' + button.dataset.label;
                }
            }).catch(function () {
                addLog('✗ ' + button.dataset.label + ' — сеть', false);
                statusEl.textContent = 'Ошибка сети: ' + button.dataset.label;
            }).finally(function () {
                button.disabled = false;
                button.textContent = oldText;
                setProgress(1, 1);
            });
        });
    });

    document.querySelectorAll('.js-food-source-row-ai-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var input = form.querySelector('input[name="ai_model"]');
            if (input) {
                input.value = selectedModel();
            }
        });
    });
})();
</script>
@endsection
