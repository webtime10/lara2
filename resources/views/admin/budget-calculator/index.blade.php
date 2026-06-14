@extends('admin.layouts.layout')

@php
    $formatBlock = static function (array $catalog, string $key): string {
        $block = is_array($catalog[$key] ?? null) ? $catalog[$key] : [];

        return match ($key) {
            'trip_dates' => trim(implode(' ', array_filter([
                $block['dateMode'] ?? '',
                ($block['dateFrom'] ?? '') !== '' || ($block['dateTo'] ?? '') !== ''
                    ? trim(($block['dateFrom'] ?? '') . ' — ' . ($block['dateTo'] ?? ''))
                    : '',
                ($block['durationDays'] ?? '') !== '' ? ($block['durationDays'] . ' дн.') : '',
            ]))) ?: '—',
            'travelers' => ($block['quantity'] ?? '') !== '' ? (string) $block['quantity'] : '—',
            'children' => trim(implode(', ', array_filter([
                ($block['quantity'] ?? '') !== '' ? 'кол-во: ' . $block['quantity'] : '',
                ! empty($block['ages']) && is_array($block['ages'])
                    ? 'возраст: ' . implode(', ', $block['ages'])
                    : '',
            ]))) ?: '—',
            'region' => ($block['region'] ?? '') !== '' ? (string) $block['region'] : '—',
            'housing' => ($block['housingType'] ?? '') !== '' ? (string) $block['housingType'] : '—',
            'comfort' => ($block['comfortLevel'] ?? '') !== '' ? (string) $block['comfortLevel'] : '—',
            'entertainment' => ($block['entertainmentLevel'] ?? '') !== '' ? (string) $block['entertainmentLevel'] : '—',
            'dining' => ($block['diningLevel'] ?? '') !== '' ? (string) $block['diningLevel'] : '—',
            'car_rental' => ($block['carRental'] ?? '') !== '' ? (string) $block['carRental'] : '—',
            'car_class' => ($block['carClass'] ?? '') !== '' ? (string) $block['carClass'] : '—',
            'budget_priority' => ($block['budgetPriority'] ?? '') !== '' ? (string) $block['budgetPriority'] : '—',
            default => '—',
        };
    };

    $blocks = [
        'trip_dates' => '1. Даты',
        'travelers' => '2. Путешественники',
        'children' => '3. Дети',
        'region' => '4. Регион',
        'housing' => '5. Жильё',
        'comfort' => '6. Комфорт',
        'entertainment' => '7. Развлечения',
        'dining' => '8. Питание',
        'car_rental' => '9. Аренда авто',
        'car_class' => '10. Класс авто',
        'budget_priority' => '11. Приоритет',
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
                    <form method="get" class="form-inline">
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
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Дата</th>
                                <th>Язык</th>
                                <th>Session</th>
                                @foreach ($blocks as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($answers as $row)
                                @php
                                    $payload = is_array($row->payload) ? $row->payload : [];
                                    $catalog = is_array($payload['catalog'] ?? null) ? $payload['catalog'] : [];
                                @endphp
                                <tr>
                                    <td>{{ $row->id }}</td>
                                    <td>{{ $row->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>{{ $payload['language'] ?? '—' }}</td>
                                    <td><small>{{ $row->session_token ?: '—' }}</small></td>
                                    @foreach (array_keys($blocks) as $key)
                                        <td><small>{{ $formatBlock($catalog, $key) }}</small></td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 4 + count($blocks) }}" class="text-center">Пока нет ответов с WordPress</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $answers->links() }}</div>
            </div>
        </div>
    </section>
@endsection
