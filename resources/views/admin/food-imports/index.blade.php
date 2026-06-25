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
                        <li class="breadcrumb-item active">Импорт питания</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <p class="text-muted">
                        Импорт сырья в <code>food_imports</code> выполняется отдельно по каждому кантону.
                        Ключевые слова: <code>{{ implode('</code>, <code>', $keywords) }}</code>.
                    </p>

                    <div class="mb-3">
                        <button
                            type="button"
                            id="btnSyncAllFoodImports"
                            class="btn btn-primary mr-2 mb-2"
                        >
                            Импортировать все кантоны
                        </button>
                        <button
                            type="button"
                            id="btnGenerateAllFoodSamples"
                            class="btn btn-success mr-2 mb-2"
                        >
                            Сформировать выборку для всех
                        </button>
                        <button
                            type="button"
                            id="btnClearFoodImports"
                            class="btn btn-danger mb-2"
                            data-url="{{ route('admin.food-imports.clear-all') }}"
                        >
                            Очистить всё
                        </button>
                    </div>

                    <div id="foodImportsAlert" class="alert d-none"></div>
                    <div id="foodImportsProgressWrap" class="mb-3 d-none">
                        <div class="d-flex justify-content-between mb-1">
                            <small id="foodImportsProgressText" class="text-muted">Ожидание...</small>
                            <small id="foodImportsProgressPercent" class="text-muted">0%</small>
                        </div>
                        <div class="progress" style="height: 22px;">
                            <div
                                id="foodImportsProgressBar"
                                class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar"
                                style="width: 0%;"
                                aria-valuenow="0"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            >0%</div>
                        </div>
                    </div>
                    <ul id="foodImportsLog" class="list-unstyled mb-3" style="max-height: 220px; overflow-y: auto;"></ul>

                    <div class="row">
                        @foreach ($regions as $region)
                            <div class="col-md-3 col-sm-4 col-6 mb-2" data-region-slug="{{ $region->slug }}">
                                <div class="border rounded p-2 h-100">
                                    <strong>{{ $region->label }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        location_code={{ $region->location_code }}
                                        <br>
                                        <a href="{{ route('admin.food-imports.show', $region->slug) }}" class="region-food-imports-count">
                                            {{ $region->food_imports_count }} в food_imports
                                        </a>
                                        <br>
                                        <a href="{{ route('admin.food-samples.show', $region->slug) }}" class="region-food-samples-count">
                                            {{ $region->food_samples_count }} в food_samples
                                        </a>
                                    </small>
                                    <a
                                        href="{{ route('admin.food-imports.show', $region->slug) }}"
                                        class="btn btn-sm btn-outline-secondary btn-block mt-2"
                                    >
                                        Смотреть список
                                    </a>
                                    <a
                                        href="{{ route('admin.food-samples.show', $region->slug) }}"
                                        class="btn btn-sm btn-outline-secondary btn-block mt-2"
                                    >
                                        Смотреть выборку
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary btn-block mt-2 js-sync-food-import-region"
                                        data-slug="{{ $region->slug }}"
                                        data-label="{{ $region->label }}"
                                        data-url="{{ route('admin.food-imports.sync', $region->slug) }}"
                                    >
                                        Импортировать
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger btn-block mt-2 js-clear-food-import-region"
                                        data-slug="{{ $region->slug }}"
                                        data-label="{{ $region->label }}"
                                        data-url="{{ route('admin.food-imports.clear', $region->slug) }}"
                                    >
                                        Очистить кантон
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-warning btn-block mt-2 js-classify-food-import-region"
                                        data-slug="{{ $region->slug }}"
                                        data-label="{{ $region->label }}"
                                        data-url="{{ route('admin.food-samples.classify', $region->slug) }}"
                                    >
                                        Проверить candidates
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success btn-block mt-2 js-generate-food-samples-region"
                                        data-slug="{{ $region->slug }}"
                                        data-label="{{ $region->label }}"
                                        data-url="{{ route('admin.food-samples.generate', $region->slug) }}"
                                    >
                                        Сформировать выборку
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
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var alertBox = document.getElementById('foodImportsAlert');
    var logEl = document.getElementById('foodImportsLog');
    var progressWrap = document.getElementById('foodImportsProgressWrap');
    var progressText = document.getElementById('foodImportsProgressText');
    var progressPercent = document.getElementById('foodImportsProgressPercent');
    var progressBar = document.getElementById('foodImportsProgressBar');

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

    function showAlert(type, text) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = text;
        alertBox.classList.remove('d-none');
    }

    function addLog(text, ok) {
        var li = document.createElement('li');
        li.className = ok ? 'text-success' : 'text-danger';
        li.textContent = text;
        logEl.appendChild(li);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function setProgress(current, total, text) {
        var percent = total > 0 ? Math.round((current / total) * 100) : 0;
        progressWrap.classList.remove('d-none');
        progressText.textContent = text || '';
        progressPercent.textContent = percent + '%';
        progressBar.style.width = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
        progressBar.textContent = percent + '%';
    }

    function finishProgress(text, hasErrors) {
        setProgress(1, 1, text);
        progressBar.classList.toggle('bg-warning', !!hasErrors);
        progressBar.classList.toggle('bg-success', !hasErrors);
        progressBar.classList.remove('progress-bar-animated');
    }

    function resetProgress() {
        progressWrap.classList.add('d-none');
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        progressBar.style.width = '0%';
        progressBar.setAttribute('aria-valuenow', 0);
        progressBar.textContent = '0%';
        progressText.textContent = 'Ожидание...';
        progressPercent.textContent = '0%';
    }

    function updateCount(slug, count) {
        var cell = document.querySelector('[data-region-slug="' + slug + '"] .region-food-imports-count');
        if (cell) {
            cell.textContent = count + ' в food_imports';
        }
    }

    function updateSampleCount(slug, count) {
        var cell = document.querySelector('[data-region-slug="' + slug + '"] .region-food-samples-count');
        if (cell) {
            cell.textContent = count + ' в food_samples';
        }
    }

    function updateAllCounts(count) {
        document.querySelectorAll('.region-food-imports-count').forEach(function (cell) {
            cell.textContent = count + ' в food_imports';
        });
        document.querySelectorAll('.region-food-samples-count').forEach(function (cell) {
            cell.textContent = count + ' в food_samples';
        });
    }

    function regionButtons(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function setBatchButtonsDisabled(disabled) {
        ['btnSyncAllFoodImports', 'btnGenerateAllFoodSamples', 'btnClearFoodImports'].forEach(function (id) {
            var button = document.getElementById(id);
            if (button) {
                button.disabled = disabled;
            }
        });
    }

    function runSequential(buttons, options) {
        if (!buttons.length) {
            showAlert('warning', 'Нет кантонов для запуска');
            return Promise.resolve();
        }

        setBatchButtonsDisabled(true);
        showAlert('info', options.startedText);

        var index = 0;
        var successCount = 0;
        var failCount = 0;
        resetProgress();
        setProgress(0, buttons.length, options.startedText);

        function next() {
            if (index >= buttons.length) {
                setBatchButtonsDisabled(false);
                showAlert(failCount ? 'warning' : 'success', options.finishedText + ': успешно ' + successCount + ', ошибок ' + failCount);
                finishProgress(options.finishedText + ': успешно ' + successCount + ', ошибок ' + failCount, failCount > 0);
                return Promise.resolve();
            }

            var button = buttons[index++];
            var slug = button.getAttribute('data-slug');
            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');

            addLog('→ ' + options.itemText + ': ' + label, true);
            setProgress(index - 1, buttons.length, options.itemText + ': ' + label + ' (' + index + ' из ' + buttons.length + ')');

            return postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    successCount++;
                    options.onSuccess(slug, label, res.json);
                } else {
                    failCount++;
                    addLog('✗ ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                }
            }).catch(function () {
                failCount++;
                addLog('✗ ' + label + ' — сеть', false);
            }).then(function () {
                setProgress(index, buttons.length, options.itemText + ': ' + label + ' готово (' + index + ' из ' + buttons.length + ')');
                return next();
            });
        }

        return next();
    }

    document.getElementById('btnSyncAllFoodImports')?.addEventListener('click', function () {
        if (!confirm('Импортировать food_imports для всех кантонов по очереди? Это может занять время.')) {
            return;
        }

        runSequential(regionButtons('.js-sync-food-import-region'), {
            startedText: 'Запущен импорт всех кантонов...',
            finishedText: 'Импорт всех кантонов завершён',
            itemText: 'Импорт',
            onSuccess: function (slug, label, json) {
                updateCount(slug, json.total || json.count || 0);
                addLog('✓ ' + label + ' — импортировано ' + (json.count || 0) + ', всего ' + (json.total || json.count || 0), true);
            },
        });
    });

    document.getElementById('btnGenerateAllFoodSamples')?.addEventListener('click', function () {
        if (!confirm('Сформировать food_samples для всех кантонов по очереди?')) {
            return;
        }

        runSequential(regionButtons('.js-generate-food-samples-region'), {
            startedText: 'Запущено формирование выборок для всех кантонов...',
            finishedText: 'Формирование выборок завершено',
            itemText: 'Выборка',
            onSuccess: function (slug, label, json) {
                var summary = json.summary || {};
                updateSampleCount(slug, summary.total || 0);
                addLog(
                    '✓ ' + label
                    + ' — samples ' + (summary.total || 0)
                    + ', cafe ' + (summary.cafe || 0)
                    + ', restaurant ' + (summary.restaurant || 0)
                    + ', candidates ' + (summary.restaurant_candidate || 0),
                    true
                );
            },
        });
    });

    document.getElementById('btnClearFoodImports')?.addEventListener('click', function () {
        if (!confirm('Удалить все записи из food_imports?')) {
            return;
        }

        var button = this;
        button.disabled = true;
        button.textContent = 'Очищаем...';
        showAlert('info', 'Очистка food_imports...');

        postJson(button.getAttribute('data-url')).then(function (res) {
            if (res.ok && res.json && res.json.ok) {
                updateAllCounts(0);
                addLog('✓ Удалено: food_imports ' + res.json.deleted + ', food_samples ' + (res.json.samples_deleted || 0), true);
                showAlert('success', 'food_imports и food_samples очищены, теперь 0 записей');
            } else {
                addLog('✗ Очистка — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                showAlert('danger', 'Ошибка очистки');
            }
        }).catch(function () {
            addLog('✗ Очистка — сеть', false);
            showAlert('danger', 'Ошибка сети при очистке');
        }).finally(function () {
            button.disabled = false;
            button.textContent = 'Очистить всё';
        });
    });

    document.querySelectorAll('.js-sync-food-import-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;

            var slug = button.getAttribute('data-slug');
            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');

            button.disabled = true;
            button.textContent = 'Импорт...';
            showAlert('info', 'Импорт: ' + label);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ ' + label + ' — ' + res.json.count + ' заведений', true);
                    updateCount(slug, res.json.total || res.json.count);
                    showAlert('success', 'Готово: ' + label);
                } else {
                    addLog('✗ ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                    showAlert('danger', 'Ошибка: ' + label);
                }
            }).catch(function () {
                addLog('✗ ' + label + ' — сеть', false);
                showAlert('danger', 'Ошибка сети: ' + label);
            }).finally(function () {
                button.disabled = false;
                button.textContent = 'Импортировать';
            });
        });
    });

    document.querySelectorAll('.js-clear-food-import-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;

            var slug = button.getAttribute('data-slug');
            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');

            if (!confirm('Очистить food_imports только для кантона ' + label + '?')) {
                return;
            }

            button.disabled = true;
            button.textContent = 'Очищаем...';
            showAlert('info', 'Очистка: ' + label);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    updateCount(slug, 0);
                    updateSampleCount(slug, 0);
                    addLog('✓ ' + label + ' — удалено: food_imports ' + res.json.deleted + ', food_samples ' + (res.json.samples_deleted || 0), true);
                    showAlert('success', 'Очищено: ' + label);
                } else {
                    addLog('✗ Очистка ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                    showAlert('danger', 'Ошибка очистки: ' + label);
                }
            }).catch(function () {
                addLog('✗ Очистка ' + label + ' — сеть', false);
                showAlert('danger', 'Ошибка сети при очистке: ' + label);
            }).finally(function () {
                button.disabled = false;
                button.textContent = 'Очистить кантон';
            });
        });
    });

    document.querySelectorAll('.js-classify-food-import-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;

            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');

            button.disabled = true;
            button.textContent = 'Проверка...';
            showAlert('info', 'Проверка restaurant_candidate в food_samples: ' + label);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    addLog('✓ Проверка samples ' + label + ' — обработано ' + res.json.processed + ', осталось ' + res.json.remaining, true);
                    showAlert('success', 'Проверка готова: ' + label);
                } else {
                    addLog('✗ Проверка ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                    showAlert('danger', 'Ошибка проверки: ' + label);
                }
            }).catch(function () {
                addLog('✗ Проверка ' + label + ' — сеть', false);
                showAlert('danger', 'Ошибка сети при проверке: ' + label);
            }).finally(function () {
                button.disabled = false;
                button.textContent = 'Проверить candidates';
            });
        });
    });

    document.querySelectorAll('.js-generate-food-samples-region').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;

            var slug = button.getAttribute('data-slug');
            var label = button.getAttribute('data-label');
            var url = button.getAttribute('data-url');

            button.disabled = true;
            button.textContent = 'Формируем...';
            showAlert('info', 'Формирование food_samples: ' + label);

            postJson(url).then(function (res) {
                if (res.ok && res.json && res.json.ok) {
                    var summary = res.json.summary || {};
                    updateSampleCount(slug, summary.total || 0);
                    addLog(
                        '✓ samples ' + label
                        + ' — всего ' + (summary.total || 0)
                        + ', cafe ' + (summary.cafe || 0)
                        + ', restaurant ' + (summary.restaurant || 0)
                        + ', candidates ' + (summary.restaurant_candidate || 0),
                        true
                    );
                    showAlert('success', 'food_samples сформирована: ' + label);
                } else {
                    addLog('✗ samples ' + label + ' — ' + ((res.json && res.json.message) ? res.json.message : 'Ошибка'), false);
                    showAlert('danger', 'Ошибка формирования samples: ' + label);
                }
            }).catch(function () {
                addLog('✗ samples ' + label + ' — сеть', false);
                showAlert('danger', 'Ошибка сети при формировании samples: ' + label);
            }).finally(function () {
                button.disabled = false;
                button.textContent = 'Сформировать выборку';
            });
        });
    });
})();
</script>
@endsection
