@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Категории — Список</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item active">Категории</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <form id="categoriesBulkDeleteForm" method="post" action="{{ route('admin.categories.bulk-delete') }}" class="d-inline">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-danger"
                                    onclick="return confirm('Удалить выбранные категории?');"
                                >
                                    <i class="fas fa-trash-alt"></i> Удалить
                                </button>
                            </form>
                            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary float-right">
                                <i class="fa fa-plus"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px">
                                                <input type="checkbox" id="categoriesSelectAll" title="Выбрать все">
                                            </th>
                                            <th style="width: 50px">#</th>
                                            <th>Название</th>
                                            <th>Производитель</th>
                                            <th>Порядок</th>
                                            <th>Краткое описание</th>
                                            <th>Родитель</th>
                                            <th style="width: 150px">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($categories as $item)
                                            @php
                                                $defId = $defaultLanguage?->id;
                                                $d = $defId ? $item->descriptions->firstWhere('language_id', $defId) : null;
                                                $pd = $item->parent && $defId
                                                    ? $item->parent->descriptions->firstWhere('language_id', $defId)
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="selected[]"
                                                        value="{{ $item->id }}"
                                                        class="category-row-checkbox"
                                                        form="categoriesBulkDeleteForm"
                                                    >
                                                </td>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $d->name ?? '—' }}</td>
                                                <td>{{ $item->manufacturer->name ?? '—' }}</td>
                                                <td>{{ $item->sort_order }}</td>
                                                <td>{{ $d->short_description ?? '—' }}</td>
                                                <td>{{ $pd->name ?? '—' }}</td>
                                                <td>
                                                    <a href="{{ route('admin.categories.edit', $item->id) }}" class="btn btn-info btn-sm">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </a>
                                                    <form action="{{ route('admin.categories.destroy', $item->id) }}" method="post" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('Подтвердите удаление')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center">Нет данных</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var selectAll = document.getElementById('categoriesSelectAll');
            var checkboxes = document.querySelectorAll('.category-row-checkbox');

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
