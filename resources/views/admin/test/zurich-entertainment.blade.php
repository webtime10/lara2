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
                        <li class="breadcrumb-item active">Развлечения Цюриха</li>
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
                        Каталог с однозначной категорией — ключ для коэффициентов в модуле бюджета.
                        Стоимость здесь не рассчитывается.
                        Сортировка: отзывы ↓, рейтинг ↓.
                    </p>

                    <div class="alert alert-secondary mb-3 small">
                        <strong>10 категорий:</strong>
                        Museum, Cinema, Zoo, Aquarium, Amusement park, Theme park, Water park,
                        Escape room, Boat tour, Ski resort.
                        <br><br>
                        <strong>Правила:</strong>
                        в названии «Zoo» → Zoo, «Aquarium» → Aquarium;
                        Escape room — отдельная категория;
                        отзывов &lt; 10 — исключить;
                        Ortsmuseum / Heimatmuseum / Gemeindemuseum с отзывами &lt; 100 — исключить.
                        <br><br>
                        <strong>Статистика региона:</strong>
                        объекты и уникальные сети/бренды (например, 4 кинотеатра blue Cinema = 1 сеть).
                    </div>

                    <button type="button" class="btn btn-warning mb-3" id="btnZurichEntertainmentLoad">
                        Загрузить каталог
                    </button>

                    <div id="zurichEntertainmentAlert" class="alert d-none" role="alert"></div>
                    <div id="zurichEntertainmentStats" class="text-muted mb-2"></div>

                    <div id="zurichEntertainmentResults"></div>
                    <div id="zurichEntertainmentPagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const fetchUrl = @json(route('admin.test.zurich.entertainment.fetch'));
    const alertBox = document.getElementById('zurichEntertainmentAlert');
    const statsBox = document.getElementById('zurichEntertainmentStats');
    const resultsBox = document.getElementById('zurichEntertainmentResults');
    const paginationBox = document.getElementById('zurichEntertainmentPagination');
    const loadBtn = document.getElementById('btnZurichEntertainmentLoad');

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

    function renderStats(categorySummary, regionSummary) {
        if (!categorySummary && !regionSummary) {
            statsBox.textContent = '';
            return;
        }

        const parts = [];

        if (regionSummary) {
            parts.push('Объектов: ' + regionSummary.total_objects);
            parts.push('сетей/брендов: ' + regionSummary.total_brands);
        } else if (categorySummary) {
            parts.push('Всего: ' + categorySummary.total);
        }

        const categories = regionSummary?.categories || null;
        const keys = categories
            ? Object.keys(categories)
            : Object.keys(categorySummary || {}).filter(function (key) { return key !== 'total'; });

        keys.sort().forEach(function (key) {
            if (categories) {
                const row = categories[key];
                if (row.objects > 0) {
                    parts.push(key + ': ' + row.objects + ' / ' + row.brands + ' сетей');
                }
            } else if (categorySummary[key] > 0) {
                parts.push(key + ': ' + categorySummary[key]);
            }
        });

        statsBox.textContent = parts.join(' | ');
    }

    function renderTable(items) {
        if (!Array.isArray(items) || items.length === 0) {
            resultsBox.innerHTML = '<p class="text-muted mb-0">Нет данных</p>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-bordered table-sm">'
            + '<thead><tr>'
            + '<th>Название</th>'
            + '<th style="width:140px">Категория</th>'
            + '<th style="width:180px">Сайт</th>'
            + '<th style="width:70px">Рейтинг</th>'
            + '<th style="width:80px">Отзывы</th>'
            + '<th>Адрес</th>'
            + '<th style="width:200px">place_id</th>'
            + '</tr></thead><tbody>';

        items.forEach(function (item) {
            const website = item.website
                ? '<a href="' + escapeHtml(item.website) + '" target="_blank" rel="noopener">' + escapeHtml(item.website) + '</a>'
                : '—';

            html += '<tr>'
                + '<td>' + escapeHtml(item.name) + '</td>'
                + '<td><strong>' + escapeHtml(item.category || '—') + '</strong></td>'
                + '<td><small>' + website + '</small></td>'
                + '<td>' + escapeHtml(item.rating != null ? item.rating : '—') + '</td>'
                + '<td>' + escapeHtml(item.reviews != null ? item.reviews : '—') + '</td>'
                + '<td><small>' + escapeHtml(item.address || '—') + '</small></td>'
                + '<td><small style="word-break:break-all">' + escapeHtml(item.place_id || '—') + '</small></td>'
                + '</tr>';
        });

        html += '</tbody></table></div>';
        resultsBox.innerHTML = html;
    }

    function renderPagination(pagination, onPage) {
        if (!pagination || pagination.last_page <= 1) {
            paginationBox.innerHTML = '';
            return;
        }

        let html = '<nav><ul class="pagination mb-0">';
        for (let page = 1; page <= pagination.last_page; page++) {
            const active = page === pagination.current_page ? ' active' : '';
            html += '<li class="page-item' + active + '">'
                + '<button type="button" class="page-link" data-page="' + page + '">' + page + '</button>'
                + '</li>';
        }
        html += '</ul></nav>';
        paginationBox.innerHTML = html;

        paginationBox.querySelectorAll('[data-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                onPage(parseInt(btn.getAttribute('data-page'), 10));
            });
        });
    }

    function loadData(page) {
        if (!loadBtn) {
            return;
        }

        loadBtn.disabled = true;
        showAlert('info', 'Запрос к DataForSEO… подождите 1–2 мин.');

        const targetUrl = page > 1 ? fetchUrl + '?page=' + page : fetchUrl;

        fetch(targetUrl, {
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
            if (res.data.success) {
                showAlert('success', res.data.message || 'Данные загружены');
                renderTable(res.data.items || []);
                renderStats(res.data.category_summary || null, res.data.region_summary || null);
                renderPagination(res.data.pagination || null, loadData);
            } else {
                showAlert('danger', res.data.message || 'Ошибка загрузки');
                resultsBox.innerHTML = '';
                statsBox.textContent = '';
                paginationBox.innerHTML = '';
            }
        })
        .catch(function () {
            showAlert('danger', 'Не удалось выполнить запрос');
        })
        .finally(function () {
            loadBtn.disabled = false;
        });
    }

    loadBtn?.addEventListener('click', function () {
        loadData(1);
    });
});
</script>
@endsection
