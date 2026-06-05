@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>{{ $pageTitle }}</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Товары</a></li>
                        <li class="breadcrumb-item active">Редактирование</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <form action="{{ route('admin.products.update', $product->id) }}" method="post">
                    @csrf @method('PUT')
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif

                        @php $sel = old('category_ids', $product->categories->pluck('id')->all()); @endphp
                        <div class="form-group">
                            <label>Категории <span class="text-danger">*</span></label>
                            <div style="max-height:200px;overflow:auto;border:1px solid #ddd;padding:10px;border-radius:4px;">
                                @foreach($categories as $cat)
                                    @php $d = $defaultLanguage ? $cat->descriptions->firstWhere('language_id', $defaultLanguage->id) : null; @endphp
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $cat->id }}" id="c{{ $cat->id }}"
                                            {{ in_array($cat->id, $sel) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="c{{ $cat->id }}">{{ $d->name ?? '#'.$cat->id }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="model">Model <span class="text-danger">*</span></label>
                                    <input type="text" name="model" id="model" class="form-control" value="{{ old('model', $product->model) }}" required maxlength="64">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sku">SKU</label>
                                    <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku', $product->sku) }}" maxlength="64">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="manufacturer_id">Производитель</label>
                                    <select name="manufacturer_id" id="manufacturer_id" class="form-control">
                                        <option value="">—</option>
                                        @foreach($manufacturers as $m)
                                            <option value="{{ $m->id }}" {{ old('manufacturer_id', $product->manufacturer_id) == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        {{-- Цена / Количество / Порядок скрыты из формы. Колонки сохранены в БД. --}}
                        {{-- Картинка (путь) скрыта из формы. Колонка сохранена в БД. --}}

                        <h5>Описания</h5>
                        <ul class="nav nav-tabs" role="tablist">
                            @foreach($languages as $i => $language)
                                <li class="nav-item">
                                    <a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-toggle="tab" href="#lang{{ $language->id }}">{{ $language->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content border p-3">
                            @foreach($languages as $i => $language)
                                @php
                                    $c = $language->code;
                                    $desc = $product->descriptions->firstWhere('language_id', $language->id);
                                @endphp
                                <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="lang{{ $language->id }}">
                                    <div class="form-group">
                                        <label for="name_{{ $c }}">Название</label>
                                        <input type="text" name="name_{{ $c }}" id="name_{{ $c }}" class="form-control" value="{{ old('name_'.$c, $desc->name ?? '') }}" {{ $language->is_default ? 'required' : '' }}>
                                    </div>
                                    <div class="form-group">
                                        <label>Описание</label>
                                        <textarea name="description_{{ $c }}" class="form-control" rows="4">{{ old('description_'.$c, $desc->description ?? '') }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-group mt-3">
                            <input type="hidden" name="status" value="0">
                            <label><input type="checkbox" name="status" value="1" {{ old('status', $product->status) ? 'checked' : '' }}> Активен</label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-default">Назад</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
