<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const fetchUrl = @json($fetchUrl);
    const alertBox = document.getElementById('zurichAlert');
    const resultsBox = document.getElementById('zurichResults');
    const paginationBox = document.getElementById('zurichPagination');
    const loadBtn = document.getElementById('btnZurichLoad');

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

        let html = '<div class="table-responsive"><table class="table table-striped table-bordered">'
            + '<thead><tr>'
            + '<th>Название</th>'
            + '<th style="width:80px">Класс</th>'
            + '<th style="width:160px">Цена за 1 ночь</th>'
            + '</tr></thead><tbody>';

        items.forEach(function (item) {
            const level = item.level != null ? String(item.level) : '—';
            const price = item.price_usd != null
                ? '$' + Number(item.price_usd).toLocaleString('en-US', { maximumFractionDigits: 0 })
                : '—';

            html += '<tr>'
                + '<td>' + escapeHtml(item.name) + '</td>'
                + '<td class="text-center">' + level + '</td>'
                + '<td>' + price + '</td>'
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
        showAlert('info', 'Запрос к DataForSEO… подождите 30–60 сек.');

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
                renderPagination(res.data.pagination || null, loadData);
            } else {
                showAlert('danger', res.data.message || 'Ошибка загрузки');
                resultsBox.innerHTML = '';
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
