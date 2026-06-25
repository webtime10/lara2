@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Админ-панель</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <p class="mb-0">Добро пожаловать в админ-панель.</p>
                </div>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title mb-0">Базы данных — MySQL + PostgreSQL</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">
                        Основное подключение: <code>{{ $defaultDbConnection }}</code>.
                        MySQL — <code>DB_MYSQL_*</code>, PostgreSQL — <code>DB_PGSQL_*</code> в <code>.env</code>.
                    </p>

                    <button type="button" class="btn btn-info mb-3" id="btnDatabasesTest">
                        Проверить обе базы
                    </button>

                    <div id="databasesAlert" class="alert d-none" role="alert"></div>
                    <div id="databasesResults"></div>
                </div>
            </div>

            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title mb-0">DataForSEO API — подключение</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">
                        Проверка подключения к <code>api.dataforseo.com</code> через учётные данные из <code>.env</code>
                        (<code>DATAFORSEO_LOGIN</code>, <code>DATAFORSEO_PASSWORD</code>).
                    </p>

                    @if (! $dataForSeoConfigured)
                        <div class="alert alert-warning mb-0">
                            В <code>.env</code> не заданы <code>DATAFORSEO_LOGIN</code> или <code>DATAFORSEO_PASSWORD</code>.
                        </div>
                    @else
                        <p class="mb-2">Логин в .env: <strong>{{ $dataForSeoLoginMasked }}</strong></p>

                        <button type="button" class="btn btn-success mb-3" id="btnDataForSeoTest">
                            Проверить подключение
                        </button>

                        <div id="dataForSeoAlert" class="alert d-none" role="alert"></div>
                        <div id="dataForSeoResults"></div>
                    @endif
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">Gemini API — ключи из .env</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">
                        Модель: <code>{{ $geminiModel }}</code>.
                        В <code>GEMINI_API_KEY</code> через запятую.
                        Round-robin: 1-й запрос weather → ключ 1, 2-й → ключ 2, и т.д.
                    </p>

                    @if ($geminiKeyCount === 0)
                        <div class="alert alert-warning mb-0">
                            Ключи не найдены. Задайте <code>GEMINI_API_KEY</code> в <code>.env</code> (несколько через запятую).
                        </div>
                    @else
                        <p><strong>Найдено ключей:</strong> {{ $geminiKeyCount }}</p>
                        <ul class="list-unstyled mb-3">
                            @foreach ($geminiMaskedKeys as $row)
                                <li>#{{ $row['index'] }} — <code>{{ $row['mask'] }}</code></li>
                            @endforeach
                        </ul>

                        <div class="btn-group flex-wrap mb-3" role="group">
                            <button type="button" class="btn btn-primary" id="btnGeminiTestAll">
                                Проверить все ключи
                            </button>
                            <button type="button" class="btn btn-info" id="btnGeminiTestNext">
                                Проверить следующий (round-robin)
                            </button>
                            <button type="button" class="btn btn-secondary" id="btnGeminiResetRotation">
                                Сбросить очередь на №1
                            </button>
                        </div>

                        <div id="geminiKeysAlert" class="alert d-none" role="alert"></div>
                        <div id="geminiKeysResults"></div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const dbAlert = document.getElementById('databasesAlert');
    const dbResults = document.getElementById('databasesResults');
    const dbBtn = document.getElementById('btnDatabasesTest');

    function showDbAlert(type, message) {
        dbAlert.className = 'alert alert-' + type;
        dbAlert.textContent = message;
        dbAlert.classList.remove('d-none');
    }

    function renderDbResults(results) {
        if (!Array.isArray(results) || results.length === 0) {
            dbResults.innerHTML = '';
            return;
        }

        let html = '<table class="table table-sm table-bordered"><thead><tr>'
            + '<th>Подключение</th><th>Драйвер</th><th>Хост</th><th>База</th><th>Время</th><th>Статус</th></tr></thead><tbody>';

        results.forEach(function (row) {
            const ok = row.ok ? 'success' : 'danger';
            const defaultMark = row.is_default ? ' <span class="badge badge-primary">default</span>' : '';
            html += '<tr class="table-' + ok + '">'
                + '<td><code>' + row.connection + '</code>' + defaultMark + '</td>'
                + '<td>' + row.driver + '</td>'
                + '<td>' + row.host + '</td>'
                + '<td>' + row.database + '</td>'
                + '<td>' + (row.response_ms != null ? row.response_ms + ' мс' : '—') + '</td>'
                + '<td>' + (row.ok ? 'OK' : row.message) + '</td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        dbResults.innerHTML = html;
    }

    function runDbTest() {
        if (!dbBtn) {
            return;
        }

        dbBtn.disabled = true;
        showDbAlert('info', 'Проверяем MySQL и PostgreSQL…');

        fetch(@json(route('admin.databases.test', [], false)), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            renderDbResults(res.data.results || []);
            showDbAlert(res.data.success ? 'success' : 'danger', res.data.message || '');
        })
        .catch(function () {
            showDbAlert('danger', 'Не удалось выполнить запрос');
        })
        .finally(function () {
            dbBtn.disabled = false;
        });
    }

    dbBtn?.addEventListener('click', runDbTest);
    runDbTest();
});
</script>
@if ($dataForSeoConfigured)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const alertBox = document.getElementById('dataForSeoAlert');
    const resultsBox = document.getElementById('dataForSeoResults');
    const btn = document.getElementById('btnDataForSeoTest');

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function renderResult(result) {
        if (!result) {
            resultsBox.innerHTML = '';
            return;
        }

        let html = '<table class="table table-sm table-bordered" style="max-width: 640px;"><tbody>'
            + '<tr><th style="width: 200px;">Учётные данные</th><td>' + (result.credentials_ok ? 'заданы' : 'не заданы') + '</td></tr>'
            + '<tr><th>Логин</th><td>' + (result.login_masked || '—') + '</td></tr>'
            + '<tr><th>API доступен</th><td>' + (result.api_reachable ? 'да' : 'нет') + '</td></tr>'
            + '<tr><th>Эндпоинт</th><td><small>' + (result.endpoint || '') + '</small></td></tr>';

        if (result.response_ms != null) {
            html += '<tr><th>Время ответа</th><td>' + result.response_ms + ' мс</td></tr>';
        }

        if (result.balance != null) {
            html += '<tr><th>Баланс</th><td>' + result.balance + (result.currency ? ' ' + result.currency : '') + '</td></tr>';
        }

        html += '<tr><th>Проверено</th><td><small>' + (result.tested_at || '') + '</small></td></tr>'
            + '</tbody></table>';

        resultsBox.innerHTML = html;
    }

    function runTest() {
        if (!btn) {
            return;
        }

        btn.disabled = true;
        showAlert('info', 'Проверяем подключение к API…');

        fetch(@json(route('admin.dataforseo.test', [], false)), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            const result = res.data.result || {};
            renderResult(result);

            if (res.data.success) {
                showAlert('success', res.data.message || 'Подключение активно');
            } else {
                showAlert('danger', res.data.message || 'Ошибка подключения');
            }
        })
        .catch(function () {
            showAlert('danger', 'Не удалось выполнить запрос');
        })
        .finally(function () {
            btn.disabled = false;
        });
    }

    btn?.addEventListener('click', runTest);
    runTest();
});
</script>
@endif
@if ($geminiKeyCount > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const alertBox = document.getElementById('geminiKeysAlert');
    const resultsBox = document.getElementById('geminiKeysResults');

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function renderResults(results) {
        if (!Array.isArray(results) || results.length === 0) {
            resultsBox.innerHTML = '';
            return;
        }
        let html = '<table class="table table-sm table-bordered"><thead><tr>'
            + '<th>#</th><th>Ключ</th><th>HTTP</th><th>Модель</th><th>Результат</th></tr></thead><tbody>';
        results.forEach(function (row) {
            const ok = row.ok ? 'success' : 'danger';
            const status = row.status != null ? row.status : '—';
            html += '<tr class="table-' + ok + '">'
                + '<td>' + row.index + '</td>'
                + '<td><code>' + row.mask + '</code></td>'
                + '<td>' + status + '</td>'
                + '<td><small>' + (row.model || '') + '</small></td>'
                + '<td>' + (row.message || '') + (row.answer ? '<br><small>' + row.answer + '</small>' : '') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        resultsBox.innerHTML = html;
    }

    function postJson(url, btn) {
        btn.disabled = true;
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .finally(function () { btn.disabled = false; });
    }

    document.getElementById('btnGeminiTestAll')?.addEventListener('click', function () {
        postJson(@json(route('admin.gemini-keys.test-all', [], false)), this).then(function (res) {
            if (res.data.success) {
                showAlert('success', res.data.message);
                renderResults(res.data.results);
            } else {
                showAlert('danger', res.data.message || 'Ошибка');
            }
        });
    });

    document.getElementById('btnGeminiTestNext')?.addEventListener('click', function () {
        postJson(@json(route('admin.gemini-keys.test-next', [], false)), this).then(function (res) {
            showAlert(res.data.success ? 'success' : 'danger', res.data.message || '');
            if (res.data.result) {
                renderResults([res.data.result]);
            }
        });
    });

    document.getElementById('btnGeminiResetRotation')?.addEventListener('click', function () {
        postJson(@json(route('admin.gemini-keys.reset-rotation', [], false)), this).then(function (res) {
            showAlert(res.data.success ? 'info' : 'danger', res.data.message || '');
        });
    });
});
</script>
@endif
@endsection
