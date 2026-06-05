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
