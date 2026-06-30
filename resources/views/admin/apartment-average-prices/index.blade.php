@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Бюджет</li>
                        <li class="breadcrumb-item active">Средние цены апартаментов</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-info mb-3">
                <div class="card-body">
                    <p class="mb-2">
                        Средние цены считаются автоматически в Laravel из таблицы <code>swiss_apartments</code>:
                        <code>AVG(price_usd)</code> по каждому кантону и классу 1 / 2 / 3.
                    </p>
                    <p class="mb-0 text-muted">
                        Эти же значения используются в <code>ApartmentBudgetCalculator</code> при расчёте проживания.
                        Чтобы обновить данные, сначала залейте апартаменты:
                        <a href="{{ route('admin.budget.apartments.index') }}">Бюджет → Апартаменты</a>.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Кантон</th>
                                @foreach ($levels as $level)
                                    <th style="width: 170px;">
                                        {{ $levelLabels[$level] ?? $level }}<br>
                                        <small class="text-muted">$/ночь</small>
                                    </th>
                                @endforeach
                                <th style="width: 110px;">В БД</th>
                                <th style="width: 150px;">Обновлено</th>
                                <th style="width: 160px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    /** @var \App\Models\SwissRegion $region */
                                    $region = $row['region'];
                                @endphp
                                <tr>
                                    <td><strong>{{ $region->label }}</strong></td>
                                    @foreach ($levels as $level)
                                        @php $stat = $row['levels'][$level] ?? null; @endphp
                                        <td>
                                            @if ($stat)
                                                <strong>${{ number_format($stat['avg'], 0, '.', ' ') }}</strong>
                                                <br>
                                                <small class="text-muted">
                                                    {{ $stat['count'] }} шт.,
                                                    ${{ number_format($stat['min'], 0) }}–${{ number_format($stat['max'], 0) }}
                                                </small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>{{ $row['total_count'] ?: '—' }}</td>
                                    <td>
                                        @if ($row['synced_at'])
                                            {{ $row['synced_at']->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">не заливали</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.budget.apartments.show', $region->slug) }}" class="btn btn-sm btn-outline-primary">
                                            Апартаменты
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
