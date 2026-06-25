@extends('admin.layouts.layout')

@php
    $priceLabels = \App\Models\FoodSource::priceLabels();
@endphp

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Бюджет</li>
                        <li class="breadcrumb-item active">Питание</li>
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
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <form method="get" class="row align-items-end">
                        <div class="col-md-4">
                            <label>Регион</label>
                            <select name="region_id" class="form-control">
                                <option value="">Все регионы</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>
                                        {{ $region->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Тип питания</label>
                            <select name="food_type" class="form-control">
                                <option value="">Все типы</option>
                                @foreach ($foodTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(request('food_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-primary">Фильтр</button>
                            <a href="{{ route('admin.food-sources.index') }}" class="btn btn-default">Сброс</a>
                            <a href="{{ route('admin.food-sources.create') }}" class="btn btn-success float-right">Добавить</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Регион</th>
                                <th>Название</th>
                                <th style="width: 150px;">Тип</th>
                                <th>Цены</th>
                                <th style="width: 90px;">Валюта</th>
                                <th style="width: 150px;">Проверено</th>
                                <th style="width: 260px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sources as $source)
                                <tr>
                                    <td>{{ $source->id }}</td>
                                    <td>{{ $source->region?->label ?? '—' }}</td>
                                    <td>
                                        <strong>{{ $source->name }}</strong>
                                        @if ($source->website)
                                            <br><small><a href="{{ $source->website }}" target="_blank" rel="noopener">{{ $source->website }}</a></small>
                                        @endif
                                    </td>
                                    <td>{{ $foodTypes[$source->food_type] ?? $source->food_type }}</td>
                                    <td>
                                        @foreach ($source->activePriceFields() as $field)
                                            @if ($source->{$field} !== null)
                                                <span class="badge badge-light">{{ $priceLabels[$field] ?? $field }}: {{ $source->{$field} }}</span>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>{{ $source->currency }}</td>
                                    <td>{{ $source->last_checked ? $source->last_checked->format('d.m.Y H:i') : '—' }}</td>
                                    <td>
                                        <a href="{{ route('admin.food-sources.edit', $source) }}" class="btn btn-sm btn-info">Изм.</a>
                                        <form action="{{ route('admin.food-sources.refresh-ai', $source) }}" method="post" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Обновить цены через ChatGPT?');">ChatGPT</button>
                                        </form>
                                        <form action="{{ route('admin.food-sources.destroy', $source) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Удал.</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Нет записей</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $sources->links('pagination::bootstrap-4') }}</div>
            </div>
        </div>
    </section>
@endsection
