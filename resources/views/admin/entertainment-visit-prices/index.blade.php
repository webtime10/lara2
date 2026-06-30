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
                        <li class="breadcrumb-item active">Цены развлечений</li>
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
            @if (! $pricesTableExists)
                <div class="alert alert-warning">
                    Таблица <code>entertainment_visit_prices</code> ещё не создана. Запустите миграции Laravel, после этого цены можно будет сохранять.
                </div>
            @endif

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label>Модель для получения цен развлечений</label>
                            <select id="entertainmentVisitAiModel" class="form-control" @disabled(! $pricesTableExists)>
                                @foreach ($aiModelLabels as $modelKey => $modelLabel)
                                    <option value="{{ $modelKey }}" @selected($modelKey === $defaultAiModel)>
                                        {{ $modelLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <button type="button" id="entertainmentVisitSyncAllBtn" class="btn btn-warning" @disabled(! $pricesTableExists)>
                                Получить все цены
                            </button>
                        </div>
                    </div>

                    <div id="entertainmentVisitProgress" class="mt-3" style="display: none;">
                        <div class="progress mb-2" style="height: 24px;">
                            <div id="entertainmentVisitProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" style="width: 0%;">0%</div>
                        </div>
                        <p id="entertainmentVisitStatus" class="mb-2 text-muted">Подготовка...</p>
                        <ul id="entertainmentVisitLog" class="list-unstyled mb-0" style="max-height: 220px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ route('admin.entertainment-visit-prices.save') }}">
                @csrf
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0">Ручные цены за одно посещение</h3>
                        <button type="submit" class="btn btn-success btn-sm ml-auto" @disabled(! $pricesTableExists)>Сохранить цены</button>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 180px;">Регион</th>
                                    @foreach ($categories as $category)
                                        <th style="min-width: 220px;">{{ $category }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        /** @var \App\Models\SwissRegion $region */
                                        $region = $row['region'];
                                        $prices = $row['prices'];
                                        $counts = $row['counts'];
                                        $filled = $prices->filter(fn ($price) => $price && $price->adult_avg_price !== null)->count();
                                        $firstPrice = $prices->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $region->label }}</strong>
                                            <br><small class="text-muted">{{ $region->slug }}</small>
                                            <br><small class="{{ $filled > 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $filled > 0 ? 'заполнено: '.$filled : 'пусто' }}
                                            </small>
                                            @if ($firstPrice?->ai_model)
                                                <br><small class="text-muted">{{ $aiModelLabels[$firstPrice->ai_model] ?? $firstPrice->ai_model }}</small>
                                            @endif
                                            <br>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning mt-2 js-entertainment-visit-region"
                                                data-slug="{{ $region->slug }}"
                                                data-label="{{ $region->label }}"
                                                data-url="{{ route('admin.entertainment-visit-prices.refresh', $region->slug) }}"
                                                @disabled(! $pricesTableExists)
                                            >
                                                {{ $filled > 0 ? 'Обновить' : 'Получить' }}
                                            </button>
                                        </td>
                                        @foreach ($categories as $category)
                                            @php
                                                $price = $prices->get($category);
                                                $count = (int) ($counts->get($category)->count_items ?? 0);
                                            @endphp
                                            <td>
                                                <input type="hidden" name="prices[{{ $region->id }}][{{ $category }}][places_count]" value="{{ $count }}">
                                                <div class="small text-muted mb-1">Мест: {{ $count }}</div>
                                                <label class="small mb-1">Взрослый $</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="form-control form-control-sm mb-2"
                                                    name="prices[{{ $region->id }}][{{ $category }}][adult]"
                                                    value="{{ $price?->adult_avg_price }}"
                                                >
                                                <label class="small mb-1">Ребёнок $</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="form-control form-control-sm"
                                                    name="prices[{{ $region->id }}][{{ $category }}][child]"
                                                    value="{{ $price?->child_avg_price }}"
                                                >
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
    var allBtn = document.getElementById('entertainmentVisitSyncAllBtn');
    var modelEl = document.getElementById('entertainmentVisitAiModel');
    var progressBox = document.getElementById('entertainmentVisitProgress');
    var progressBar = document.getElementById('entertainmentVisitProgressBar');
    var statusEl = document.getElementById('entertainmentVisitStatus');
    var logEl = document.getElementById('entertainmentVisitLog');
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
            return price.category + ' $' + price.adult_avg_price + '/$' + price.child_avg_price;
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
        if (!confirm('Получить цены развлечений через ' + selectedModelLabel() + ' по всем кантонам?')) return;

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
                    ? 'Готово: все цены развлечений сохранены. Обновляем страницу...'
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

    document.querySelectorAll('.js-entertainment-visit-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (running || button.disabled) return;
            var region = {
                slug: button.dataset.slug,
                label: button.dataset.label,
                url: button.dataset.url,
            };
            if (!confirm('Получить/обновить цены развлечений через ' + selectedModelLabel() + ' для ' + region.label + '?')) return;

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
