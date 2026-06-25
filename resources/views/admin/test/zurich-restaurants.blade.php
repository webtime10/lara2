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
                        <li class="breadcrumb-item active">Тест</li>
                        <li class="breadcrumb-item active">Рестораны Цюриха</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO Google Maps: <code>location_code={{ $locationCode }}</code>, keyword <code>restaurant</code>.
                        Сохраняет первые 30 популярных ресторанов с собственным сайтом в <code>food_imports</code>
                        как сырьё для GPT-классификации.
                    </p>

                    <div class="alert alert-secondary small">
                        Исключаются сети быстрого питания, фудкорты и бары без кухни.
                        <br>
                        <code>price_level</code> сохраняется как сырой признак.
                        <code>food_type</code> в <code>food_imports</code> остаётся NULL до GPT-классификации по региону.
                    </div>

                    <button type="button" class="btn btn-warning mb-3" id="btnZurichRestaurantsLoad">
                        Получить и сохранить рестораны
                    </button>

                    <div id="zurichRestaurantsAlert" class="alert d-none" role="alert"></div>
                    <div id="zurichRestaurantsResults"></div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const fetchUrl = @json(route('admin.test.zurich.restaurants.fetch'));
    const button = document.getElementById('btnZurichRestaurantsLoad');
    const alertBox = document.getElementById('zurichRestaurantsAlert');
    const resultsBox = document.getElementById('zurichRestaurantsResults');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function renderTable(items) {
        if (!Array.isArray(items) || items.length === 0) {
            resultsBox.innerHTML = '<p class="text-muted mb-0">Нет данных</p>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-bordered table-sm">'
            + '<thead><tr>'
            + '<th>name</th>'
            + '<th>website</th>'
            + '<th style="width:80px">rating</th>'
            + '<th style="width:90px">reviews</th>'
            + '<th>address</th>'
            + '<th style="width:90px">price_level</th>'
            + '</tr></thead><tbody>';

        items.forEach(function (item) {
            const website = item.website
                ? '<a href="' + escapeHtml(item.website) + '" target="_blank" rel="noopener">' + escapeHtml(item.website) + '</a>'
                : '—';

            html += '<tr>'
                + '<td>' + escapeHtml(item.name) + '</td>'
                + '<td><small>' + website + '</small></td>'
                + '<td>' + escapeHtml(item.rating ?? '—') + '</td>'
                + '<td>' + escapeHtml(item.reviews_count ?? '—') + '</td>'
                + '<td><small>' + escapeHtml(item.address || '—') + '</small></td>'
                + '<td>' + escapeHtml(item.price_level ?? '—') + '</td>'
                + '</tr>';
        });

        html += '</tbody></table></div>';
        resultsBox.innerHTML = html;
    }

    button?.addEventListener('click', function () {
        button.disabled = true;
        showAlert('info', 'Запрос к DataForSEO и сохранение в food_imports...');

        fetch(fetchUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (res.ok && res.data.success) {
                    showAlert('success', res.data.message || 'Готово');
                    renderTable(res.data.items || []);
                    return;
                }

                showAlert('danger', res.data.message || 'Ошибка');
                resultsBox.innerHTML = '';
            })
            .catch(function () {
                showAlert('danger', 'Не удалось выполнить запрос');
                resultsBox.innerHTML = '';
            })
            .finally(function () {
                button.disabled = false;
            });
    });
});
</script>
@endsection
