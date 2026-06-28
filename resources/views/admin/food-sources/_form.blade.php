@php
    $priceLabels = \App\Models\FoodSource::priceLabels();
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Регион <span class="text-danger">*</span></label>
            <select name="region_id" class="form-control" required>
                <option value="">— выберите —</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((int) old('region_id', $source->region_id) === (int) $region->id)>
                        {{ $region->label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Валюта</label>
            <input type="text" name="currency" class="form-control" value="{{ old('currency', $source->currency ?: 'CHF') }}" maxlength="10">
        </div>
    </div>
</div>

<input type="hidden" name="food_type" value="{{ \App\Models\FoodSource::TYPE_HOME_COOKING }}">

<div class="form-group">
    <label>Название <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $source->name) }}" required maxlength="255">
</div>

<div class="form-group">
    <label>Сайт</label>
    <textarea name="website" class="form-control" rows="2">{{ old('website', $source->website) }}</textarea>
    <small class="form-text text-muted">Можно оставить пустым. Используется кнопкой «Обновить через ChatGPT» для проверки цен продуктовой корзины.</small>
</div>

<div class="card card-outline card-success">
    <div class="card-header">
        <h3 class="card-title">Цены продуктовой корзины</h3>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach ($groceryFields as $field)
                <div class="col-md-4 price-field price-field-grocery">
                    <div class="form-group">
                        <label>{{ $priceLabels[$field] ?? $field }}</label>
                        <input type="number" step="0.01" min="0" name="{{ $field }}" class="form-control" value="{{ old($field, $source->{$field}) }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
