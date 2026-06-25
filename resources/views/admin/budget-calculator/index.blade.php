@extends('admin.layouts.layout')

@php
    $formatTripDates = static function ($row): string {
        $parts = array_filter([
            $row->trip_date_mode,
            ($row->trip_date_from || $row->trip_date_to)
                ? trim(($row->trip_date_from ?? '') . ' — ' . ($row->trip_date_to ?? ''))
                : null,
            $row->trip_duration_days ? $row->trip_duration_days . ' дн.' : null,
        ]);

        return $parts !== [] ? trim(implode(' ', $parts)) : '—';
    };

    $formatChildren = static function ($row): string {
        $parts = [];
        if ($row->children_count) {
            $parts[] = 'кол-во: ' . $row->children_count;
        }
        if (is_array($row->children_ages) && $row->children_ages !== []) {
            $parts[] = 'возраст: ' . implode(', ', $row->children_ages);
        }

        return $parts !== [] ? implode(', ', $parts) : '—';
    };

    $columns = [
        ['label' => '1. Даты', 'value' => $formatTripDates],
        ['label' => 'Общее дней', 'value' => static fn ($row) => $row->total_days ?: '—'],
        ['label' => 'Месяцы', 'value' => static fn ($row) => $row->trip_months ?: '—'],
        ['label' => '2. Путешественники', 'value' => static fn ($row) => $row->travelers_count ?: '—'],
        ['label' => '3. Дети', 'value' => $formatChildren],
        ['label' => 'Всего людей', 'value' => static fn ($row) => $row->total_people ?: '—'],
        ['label' => '4. Регион', 'value' => static fn ($row) => $row->region ?: '—'],
        ['label' => '5. Жильё', 'value' => static fn ($row) => $row->housing_type ?: '—'],
        ['label' => '6. Комфорт', 'value' => static fn ($row) => $row->comfort_level ?: '—'],
        ['label' => '7. Развлечения', 'value' => static fn ($row) => $row->entertainment_level ?: '—'],
        ['label' => '8. Питание', 'value' => static fn ($row) => $row->dining_level ?: '—'],
        ['label' => '9. Аренда авто', 'value' => static fn ($row) => $row->car_rental ?: '—'],
        ['label' => '10. Класс авто', 'value' => static fn ($row) => $row->car_class ?: '—'],
        ['label' => '11. Приоритет', 'value' => static fn ($row) => $row->budget_priority ?: '—'],
        ['label' => 'Сумма жильё', 'value' => static fn ($row) => $row->housing_budget_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Сумма развлечения', 'value' => static fn ($row) => $row->entertainment_budget_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Сумма питание', 'value' => static fn ($row) => $row->food_budget_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Сумма авто', 'value' => static fn ($row) => $row->car_budget_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Сумма без корректировки', 'value' => static fn ($row) => $row->budget_base_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Коррекция приоритета', 'value' => static fn ($row) => $row->budget_priority_adjustment_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Итого с корректировкой', 'value' => static fn ($row) => $row->budget_total ?: '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'Total в базе', 'value' => static fn ($row) => $row->total !== null ? number_format((float) $row->total, 2, '.', ' ') : '—', 'class' => 'text-danger font-weight-bold'],
        ['label' => 'На человека', 'value' => static fn ($row) => $row->budget_per_person ?: '—'],
    ];
@endphp

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Budget калькулятор</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    @if (session('success'))
                        <div class="alert alert-success py-2">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger py-2">{{ session('error') }}</div>
                    @endif
                    <form method="get" class="form-inline d-inline-block">
                        <label class="mr-2" for="region">Регион</label>
                        <input
                            type="text"
                            name="region"
                            id="region"
                            class="form-control form-control-sm mr-2"
                            value="{{ request('region') }}"
                            placeholder="zurich"
                        >
                        <button type="submit" class="btn btn-sm btn-primary">Фильтр</button>
                        @if (request()->filled('region'))
                            <a href="{{ route('admin.budget-calculator.index') }}" class="btn btn-sm btn-link ml-2">Сброс</a>
                        @endif
                    </form>
                    <button
                        type="submit"
                        form="budgetBulkDeleteForm"
                        class="btn btn-sm btn-danger float-right"
                        onclick="return confirm('Удалить выбранные записи?');"
                    >
                        Удалить выбранные
                    </button>
                </div>
                <form id="budgetBulkDeleteForm" method="post" action="{{ route('admin.budget-calculator.bulk-delete', request()->query()) }}">
                    @csrf
                <div class="card-body table-responsive p-0">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 32px;">
                                    <input type="checkbox" id="budgetSelectAll">
                                </th>
                                <th>ID</th>
                                <th>Дата</th>
                                <th>Язык</th>
                                <th>Session</th>
                                <th>AI</th>
                                @foreach ($columns as $column)
                                    <th>{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($answers as $row)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected[]" value="{{ $row->id }}" class="budget-row-checkbox">
                                    </td>
                                    <td>{{ $row->id }}</td>
                                    <td>{{ $row->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>{{ $row->language ?: '—' }}</td>
                                    <td><small>{{ $row->session_token ?: '—' }}</small></td>
                                    <td>
                                        @if ($row->ai_ok)
                                            <span class="badge badge-success">ok</span>
                                        @else
                                            <span class="badge badge-danger">fail</span>
                                        @endif
                                    </td>
                                    @foreach ($columns as $column)
                                        <td><small class="{{ $column['class'] ?? '' }}">{{ $column['value']($row) }}</small></td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 6 + count($columns) }}" class="text-center">Пока нет ответов с WordPress</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </form>
                <div class="card-footer">{{ $answers->links() }}</div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var selectAll = document.getElementById('budgetSelectAll');
            var checkboxes = document.querySelectorAll('.budget-row-checkbox');

            if (!selectAll) {
                return;
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
    </script>
@endsection
