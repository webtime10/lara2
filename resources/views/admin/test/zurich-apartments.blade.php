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
                        <li class="breadcrumb-item active">Тест</li>
                        <li class="breadcrumb-item active">Апартаменты Цюриха</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO: <code>vacation rentals</code>, <code>search_param: hba=1</code>, location_code <code>20151</code> (Zurich).
                        Класс 1 / 2 / 3 — по цене внутри кантона (дешевле → дороже).
                    </p>

                    <button type="button" class="btn btn-warning mb-3" id="btnZurichLoad">
                        Загрузить апартаменты
                    </button>

                    <div id="zurichAlert" class="alert d-none" role="alert"></div>
                    @include('admin.test.partials.zurich-table')
                    <div id="zurichPagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    @include('admin.test.partials.zurich-script', ['fetchUrl' => route('admin.test.zurich.apartments.fetch')])
@endsection
