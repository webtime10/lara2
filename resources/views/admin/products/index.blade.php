@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Home</a></li>
                        <li class="breadcrumb-item active">Товары</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <a href="{{ route('admin.products.create') }}" class="btn btn-primary float-right">Добавить товар</a>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Model</th>
                                <th>Название</th>
                                <th>Статус</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                @php $pd = $defaultLanguage ? $p->descriptions->firstWhere('language_id', $defaultLanguage->id) : null; @endphp
                                <tr>
                                    <td>{{ $p->id }}</td>
                                    <td>{{ $p->model }}</td>
                                    <td>{{ $pd->name ?? '—' }}</td>
                                    <td>{{ $p->status ? 'Да' : 'Нет' }}</td>
                                    <td>
                                        <a href="{{ route('admin.products.edit', $p->id) }}" class="btn btn-sm btn-info">Изм.</a>
                                        <form action="{{ route('admin.products.destroy', $p->id) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Удал.</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center">Нет товаров</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $products->links() }}</div>
            </div>
        </div>
    </section>
@endsection
