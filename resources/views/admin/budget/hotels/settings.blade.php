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
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.hotels.index') }}">Отели</a></li>
                        <li class="breadcrumb-item active">Настройки</li>
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

            <div class="mb-3">
                <a href="{{ route('admin.budget.hotels.index') }}" class="btn btn-sm btn-secondary">← Отели</a>
            </div>

            <div class="card card-outline card-info mb-3">
                <div class="card-body">
                    <p class="mb-1">
                        Отметьте ячейки occupancy — они появятся в карточке <strong>каждого отеля</strong> во всех кантонах.
                    </p>
                    <p class="mb-1 text-muted small">
                        Возраст ребёнка в API — конкретное число (маркер полосы): ≤3 → 2, ~5 → 5, ~8 → 8, &gt;8 → 12.
                        Лимит запроса: до 6 человек (взрослые + дети).
                        Сейчас выбрано: <strong>{{ $selectedCount }}</strong> из {{ $catalogCount }}.
                        Полный прогон отеля ≈ ${{ number_format($selectedCount * 0.004, 3) }}.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.budget.hotels.settings.update') }}" id="occupancy-settings-form">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Сохранить набор</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-select-defaults">Базовый максимум</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-select-none">Снять все</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-select-all">Выбрать все</button>
                </div>

                <div class="card card-outline card-primary mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Только взрослые (1–4)</h3>
                        <button type="button" class="btn btn-xs btn-outline-primary js-toggle-group" data-group="adults">все / снять</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($groups['adults'] as $cell)
                                <div class="col-md-3 col-sm-4 col-6 mb-2">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox"
                                               class="custom-control-input js-occ-check"
                                               data-group="adults"
                                               id="occ-{{ $cell['key'] }}"
                                               name="keys[]"
                                               value="{{ $cell['key'] }}"
                                               @checked(isset($selected[$cell['key']]))>
                                        <label class="custom-control-label" for="occ-{{ $cell['key'] }}">
                                            <strong>{{ $cell['key'] }}</strong><br>
                                            <span class="small text-muted">{{ $cell['label'] }}</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @foreach ($ageBands as $band)
                    @php $groupKey = 'age_'.$band['age']; @endphp
                    <div class="card card-outline card-warning mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title mb-0">
                                С детьми: {{ $band['label'] }}
                            </h3>
                            <button type="button" class="btn btn-xs btn-outline-warning js-toggle-group" data-group="{{ $groupKey }}">все / снять</button>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($groups[$groupKey] ?? [] as $cell)
                                    <div class="col-md-4 col-sm-6 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input js-occ-check"
                                                   data-group="{{ $groupKey }}"
                                                   id="occ-{{ $cell['key'] }}"
                                                   name="keys[]"
                                                   value="{{ $cell['key'] }}"
                                                   @checked(isset($selected[$cell['key']]))>
                                            <label class="custom-control-label" for="occ-{{ $cell['key'] }}">
                                                <strong>{{ $cell['key'] }}</strong><br>
                                                <span class="small text-muted">{{ $cell['label'] }}</span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="mb-4">
                    <button type="submit" class="btn btn-primary">Сохранить набор</button>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function ($) {
    var defaults = @json(\App\Support\HotelOccupancyCatalog::defaultSelectedKeys());

    function setAll(on) {
        $('#occupancy-settings-form .js-occ-check').prop('checked', !!on);
    }

    function setKeys(keys) {
        var map = {};
        (keys || []).forEach(function (k) { map[k] = true; });
        $('#occupancy-settings-form .js-occ-check').each(function () {
            $(this).prop('checked', !!map[$(this).val()]);
        });
    }

    $('#btn-select-all').on('click', function () { setAll(true); });
    $('#btn-select-none').on('click', function () { setAll(false); });
    $('#btn-select-defaults').on('click', function () { setKeys(defaults); });

    $('.js-toggle-group').on('click', function () {
        var group = $(this).data('group');
        var boxes = $('#occupancy-settings-form .js-occ-check[data-group="' + group + '"]');
        var allOn = boxes.length && boxes.filter(':checked').length === boxes.length;
        boxes.prop('checked', !allOn);
    });
})(jQuery);
</script>
@endsection
