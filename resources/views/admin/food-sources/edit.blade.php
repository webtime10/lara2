@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.food-sources.index') }}">Цены питания</a></li>
                        <li class="breadcrumb-item active">Редактирование</li>
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

            <div class="card card-primary">
                <form action="{{ route('admin.food-sources.update', $source) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        @include('admin.food-sources._form')

                        <p class="text-muted mb-0">
                            Последняя AI-проверка:
                            <strong>{{ $source->last_checked ? $source->last_checked->format('d.m.Y H:i') : '—' }}</strong>
                        </p>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="{{ route('admin.food-sources.index') }}" class="btn btn-default">Назад</a>
                    </div>
                </form>
            </div>

            <form action="{{ route('admin.food-sources.refresh-ai', $source) }}" method="post" class="mt-2">
                @csrf
                <button type="submit" class="btn btn-warning" onclick="return confirm('Обновить цены через ChatGPT?');">
                    Обновить через ChatGPT
                </button>
            </form>
        </div>
    </section>
@endsection
