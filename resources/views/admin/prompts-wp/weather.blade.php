@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Weather</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Промты WP</li>
                        <li class="breadcrumb-item active">Weather</li>
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
                            <h3 class="card-title mb-0">Промты WP / Weather — главный промт</h3>
                            <button type="button" id="saveWeatherPrompt" class="btn btn-primary float-right">
                                <i class="fa fa-save"></i> Сохранить
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="weatherPromptAlert" class="alert d-none" role="alert"></div>

                            <div class="form-group">
                                <label for="weather_ai_model">Модель AI для калькулятора</label>
                                <select id="weather_ai_model" name="weather_ai_model" class="form-control" style="max-width: 420px;">
                                    @foreach($aiModelChoices as $key => $label)
                                        <option value="{{ $key }}" {{ ($aiModel ?? '') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    В промте можно использовать плейсхолдеры:
                                    <code>{month_name}</code>, <code>{region_name}</code>, <code>{language}</code>
                                </small>
                            </div>

                            <hr>

                            <h5 class="mb-3">Главный промт по языкам</h5>

                            <ul class="nav nav-tabs" role="tablist">
                                @foreach($promptLangCodes as $i => $code)
                                    @php
                                        $lang = $languages->firstWhere('code', $code);
                                        $tabTitle = $lang ? $lang->name : strtoupper($code);
                                    @endphp
                                    <li class="nav-item">
                                        <a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-toggle="tab" href="#prompt-lang-{{ $code }}">
                                            {{ $tabTitle }} <code>{{ $code }}</code>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="tab-content border border-top-0 p-3 mb-0">
                                @foreach($promptLangCodes as $i => $code)
                                    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="prompt-lang-{{ $code }}">
                                        <div class="form-group mb-0">
                                            <label for="glavnyy_prompt_{{ $code }}">Главный промт ({{ strtoupper($code) }})</label>
                                            <textarea
                                                id="glavnyy_prompt_{{ $code }}"
                                                name="glavnyy_prompt_{{ $code }}"
                                                class="form-control prompt-lang-textarea"
                                                data-prompt-lang="{{ $code }}"
                                                rows="14"
                                                placeholder="Промт для языка {{ $code }}..."
                                            >{{ old('glavnyy_prompt_'.$code, $promptsByCode[$code] ?? '') }}</textarea>
                                        </div>
                                    </div>
                                @endforeach
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
    const saveBtn = document.getElementById('saveWeatherPrompt');
    const alertBox = document.getElementById('weatherPromptAlert');
    const textareas = document.querySelectorAll('.prompt-lang-textarea');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const langCodes = @json($promptLangCodes);

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    saveBtn.addEventListener('click', function () {
        saveBtn.disabled = true;

        const payload = {};
        const modelEl = document.getElementById('weather_ai_model');
        if (modelEl) {
            payload.weather_ai_model = modelEl.value;
        }
        textareas.forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });

        fetch(@json(route('admin.prompts-wp.weather.save', [], false)), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
        .then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw data;
                }
                return data;
            });
        })
        .then(function (data) {
            if (data.prompts && typeof data.prompts === 'object') {
                langCodes.forEach(function (code) {
                    const key = 'glavnyy_prompt_' + code;
                    const el = document.getElementById(key);
                    if (el && typeof data.prompts[key] === 'string') {
                        el.value = data.prompts[key];
                    }
                });
            }
            showAlert('success', data.message || 'Сохранено');
        })
        .catch(function (err) {
            let message = 'Не удалось сохранить';
            if (err && err.errors) {
                message = Object.values(err.errors).flat().join(' ');
            } else if (err && err.message) {
                message = err.message;
                if (message === 'Unauthenticated.') {
                    message = 'Сессия не передана. Обновите страницу и войдите снова. В .env задайте APP_URL=https://lara2.loc (как в браузере).';
                }
            }
            showAlert('danger', message);
        })
        .finally(function () {
            saveBtn.disabled = false;
        });
    });
});
</script>
@endsection
