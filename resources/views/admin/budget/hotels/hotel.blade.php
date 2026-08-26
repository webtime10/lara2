@extends('admin.layouts.layout')

@section('content')
    @php
        $storedCells = collect($stored['cells'] ?? [])->keyBy('key');
        $hasStored = $stored !== null;
    @endphp
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">{{ $pageTitle }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.hotels.index') }}">Бюджет — Отели</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.hotels.show', $region->slug) }}">{{ $region->label }}</a></li>
                        <li class="breadcrumb-item active">Occupancy</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="mb-3">
                <a href="{{ route('admin.budget.hotels.show', $region->slug) }}" class="btn btn-sm btn-secondary">← {{ $region->label }}</a>
                <button type="button" class="btn btn-sm btn-primary" id="btn-load-missing">
                    Дозалить
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-load-occupancy">
                    Обновить все из DataForSEO
                </button>
            </div>

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <p class="mb-1"><strong>Кантон:</strong> {{ $region->label }}</p>
                    <p class="mb-1"><strong>В базе (≈2A):</strong> ${{ number_format((float) $hotel->price_usd, 0) }}</p>
                    <p class="mb-1"><strong>hotel_identifier:</strong>
                        @if ($hotel->hotel_identifier)
                            <code>{{ $hotel->hotel_identifier }}</code>
                        @else
                            <span class="text-danger">нет — сначала обновите кантон из API</span>
                        @endif
                    </p>
                    <p class="text-muted mb-0 small">
                        Сетка из <a href="{{ route('admin.budget.hotels.settings') }}">Отели — настройки</a>
                        (сейчас {{ count($grid) }} яч.).
                        Ответ сохраняется в БД (`swiss_hotel_occupancy_prices`).
                        Live hotel_info ≈ $0.004 за ячейку (~${{ number_format(count($grid) * 0.004, 3) }} за полный прогон).
                        API: <code>{{ $apiHint }}</code>
                    </p>
                    <p class="mb-0 mt-2 small" id="occupancy-meta">
                        @if ($hasStored)
                            Сохранено: {{ $stored['fetched_at'] ?? '—' }}
                            @if (! empty($stored['check_in']))
                                | даты: {{ $stored['check_in'] }} → {{ $stored['check_out'] }}
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            <div id="occupancy-alert" class="alert {{ $hasStored ? 'alert-success' : 'd-none' }}" role="alert">
                @if ($hasStored)
                    Показаны сохранённые цены из БД. Повторная загрузка спишет API снова и перезапишет.
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Сетка occupancy</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0" id="occupancy-table">
                            <thead>
                                <tr>
                                    <th>Ячейка</th>
                                    <th>Состав</th>
                                    <th>adults</th>
                                    <th>children</th>
                                    <th>Цена / ночь (USD)</th>
                                    <th>vs база 2A</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grid as $cell)
                                    @php
                                        $saved = $storedCells->get($cell['key']);
                                        $price = $saved['price'] ?? null;
                                        $error = $saved['error'] ?? null;
                                        $cost = $saved['cost'] ?? null;
                                        $ratio = ($price !== null && (float) $hotel->price_usd > 0)
                                            ? ($price / (float) $hotel->price_usd)
                                            : null;
                                    @endphp
                                    <tr data-key="{{ $cell['key'] }}" data-filled="{{ $price !== null ? '1' : '0' }}">
                                        <td><strong>{{ $cell['key'] }}</strong></td>
                                        <td>{{ $cell['label'] }}</td>
                                        <td>{{ $cell['adults'] }}</td>
                                        <td>{{ $cell['children'] === [] ? '—' : implode(', ', $cell['children']) }}</td>
                                        <td class="js-price {{ $price !== null ? '' : 'text-muted' }}">
                                            {{ $price !== null ? '$'.number_format($price, 0) : '—' }}
                                        </td>
                                        <td class="js-diff {{ $ratio !== null ? '' : 'text-muted' }}">
                                            {{ $ratio !== null ? round($ratio * 100).'% от базы 2A' : '—' }}
                                        </td>
                                        <td class="js-status {{ $price !== null ? 'text-success' : ($error ? 'text-danger' : 'text-muted') }}">
                                            @if ($price !== null)
                                                сохранено{{ $cost !== null ? ' · $'.$cost : '' }}
                                            @elseif ($error)
                                                {{ \Illuminate\Support\Str::limit($error, 80) }}
                                            @else
                                                ожидание
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function ($) {
    var url = @json(route('admin.budget.hotels.hotel.occupancy', ['slug' => $region->slug, 'hotel' => $hotel->id]));
    var stored2a = {{ (float) $hotel->price_usd }};
    var hasId = @json((bool) $hotel->hotel_identifier);
    var running = false;
    var csrf = $('meta[name="csrf-token"]').attr('content');

    function setAlert(type, text) {
        var box = $('#occupancy-alert');
        box.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).text(text);
    }

    function formatMoney(n) {
        if (n == null || isNaN(n)) return '—';
        return '$' + Math.round(n).toLocaleString('en-US');
    }

    function applyCell(cell, meta) {
        if (meta) {
            $('#occupancy-meta').text(meta);
        }
        if (!cell) return;
        var row = $('#occupancy-table tbody tr[data-key="' + cell.key + '"]');
        if (!row.length) return;

        if (cell.price != null) {
            row.attr('data-filled', '1');
            row.find('.js-price').text(formatMoney(cell.price)).removeClass('text-muted');
            var ratio = stored2a > 0 ? (cell.price / stored2a) : null;
            if (ratio != null) {
                row.find('.js-diff').text((ratio * 100).toFixed(0) + '% от базы 2A').removeClass('text-muted');
            }
            row.find('.js-status')
                .text('сохранено' + (cell.cost != null ? ' · $' + cell.cost : ''))
                .removeClass('text-muted text-danger')
                .addClass('text-success');
        } else {
            row.find('.js-price').text('—').addClass('text-muted');
            row.find('.js-diff').text('—').addClass('text-muted');
            row.find('.js-status')
                .text(cell.error || 'нет цены')
                .removeClass('text-muted text-success')
                .addClass('text-danger');
        }
    }

    function keysToLoad(onlyMissing) {
        var keys = [];
        $('#occupancy-table tbody tr').each(function () {
            var key = $(this).data('key');
            if (!key) return;
            if (onlyMissing && String($(this).attr('data-filled')) === '1') {
                return;
            }
            keys.push(String(key));
        });
        return keys;
    }

    function fetchOne(key) {
        return $.ajax({
            url: url,
            method: 'POST',
            timeout: 180000,
            headers: { 'X-CSRF-TOKEN': csrf },
            data: { key: key, skip_filled: onlyMissingFlag ? 1 : 0 },
            dataType: 'json'
        });
    }

    var onlyMissingFlag = false;

    function runQueue(onlyMissing) {
        onlyMissingFlag = !!onlyMissing;
        if (running) return;
        if (!hasId) {
            setAlert('warning', 'Нет hotel_identifier. Откройте список кантона и нажмите «Обновить из API».');
            return;
        }

        var keys = keysToLoad(onlyMissing);
        if (!keys.length) {
            setAlert('success', 'Все выбранные ячейки уже залиты. Новые не запрашивались.');
            return;
        }

        running = true;
        var buttons = $('#btn-load-occupancy, #btn-load-missing');
        buttons.prop('disabled', true);
        var i = 0;
        var ok = 0;
        var fail = 0;

        function next() {
            if (i >= keys.length) {
                running = false;
                buttons.prop('disabled', false);
                setAlert('success', 'Готово. Сохранено ячеек: ' + ok + (fail ? ', без цены/ошибка: ' + fail : '') + '.');
                return;
            }

            var key = keys[i];
            var row = $('#occupancy-table tbody tr[data-key="' + key + '"]');
            row.find('.js-status').text((i + 1) + '/' + keys.length + '…').removeClass('text-success text-danger').addClass('text-muted');
            setAlert('warning', 'Запрос ' + (i + 1) + ' из ' + keys.length + ': ' + key + ' (каждая ячейка сохраняется сразу)');

            fetchOne(key).done(function (res) {
                if (!res || !res.ok) {
                    fail++;
                    applyCell({ key: key, price: null, error: (res && res.message) ? res.message : 'ошибка' });
                } else {
                    if (res.cell && res.cell.price != null) ok++;
                    else fail++;
                    applyCell(res.cell, 'Сохранено: ' + (res.fetched_at || '—') +
                        ' | даты: ' + res.check_in + ' → ' + res.check_out +
                        ' | база 2A: ' + formatMoney(res.stored_2a_price));
                }
            }).fail(function (xhr) {
                fail++;
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'ошибка запроса';
                applyCell({ key: key, price: null, error: msg });
            }).always(function () {
                i++;
                next();
            });
        }

        next();
    }

    $('#btn-load-occupancy').on('click', function () { runQueue(false); });
    $('#btn-load-missing').on('click', function () { runQueue(true); });
})(jQuery);
</script>
@endsection
