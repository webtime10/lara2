@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Budget</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Главная</a></li>
                        <li class="breadcrumb-item">Промты WP</li>
                        <li class="breadcrumb-item active">Budget</li>
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
                            <h3 class="card-title mb-0">Промты WP / Budget — главный промт</h3>
                            <button type="button" id="saveBudgetPrompt" class="btn btn-primary float-right">
                                <i class="fa fa-save"></i> Сохранить
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="budgetPromptAlert" class="alert d-none" role="alert"></div>

                            <div class="form-group">
                                <label for="budget_ai_model">Модель AI для калькулятора</label>
                                <select id="budget_ai_model" name="budget_ai_model" class="form-control" style="max-width: 420px;">
                                    @foreach($aiModelChoices as $key => $label)
                                        <option value="{{ $key }}" {{ ($aiModel ?? '') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Плейсхолдер: <code>{language}</code>.
                                    Ответы опроса приходят в SOURCE TEXT как JSON (<code>answers</code>).
                                    Ожидаемый ответ AI: JSON с полями <code>total</code>, <code>per_person</code>, <code>summary</code>, <code>rows</code>.
                                </small>
                            </div>

                            <hr>

                            <h5 class="mb-3">Промты для питания</h5>

                            <p class="text-muted">
                                Продуктовая корзина считается напрямую из базы <code>food_sources</code>, поэтому промт для неё не нужен.
                            </p>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cafe_prompt">Кафе</label>
                                        <textarea
                                            id="cafe_prompt"
                                            name="cafe_prompt"
                                            class="form-control food-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для кафе..."
                                        >{{ old('cafe_prompt', $cafePrompt ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>cafe_prompt</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="restaurants_prompt">Рестораны</label>
                                        <textarea
                                            id="restaurants_prompt"
                                            name="restaurants_prompt"
                                            class="form-control food-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для ресторанов..."
                                        >{{ old('restaurants_prompt', $restaurantsPrompt ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>restaurants_prompt</code>.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Промты для аренды авто</h5>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="car_economy_prompt">Эконом</label>
                                        <textarea
                                            id="car_economy_prompt"
                                            name="car_economy_prompt"
                                            class="form-control car-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для аренды авто эконом-класса..."
                                        >{{ old('car_economy_prompt', $carEconomyPrompt ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>car_economy_prompt</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="car_medium_prompt">Среднее</label>
                                        <textarea
                                            id="car_medium_prompt"
                                            name="car_medium_prompt"
                                            class="form-control car-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для аренды авто среднего класса..."
                                        >{{ old('car_medium_prompt', $carMediumPrompt ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>car_medium_prompt</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="car_luxury_prompt">Люкс</label>
                                        <textarea
                                            id="car_luxury_prompt"
                                            name="car_luxury_prompt"
                                            class="form-control car-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для аренды авто люкс-класса..."
                                        >{{ old('car_luxury_prompt', $carLuxuryPrompt ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>car_luxury_prompt</code>.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Корректировка total по приоритету бюджета</h5>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="budget_priority_strict_percent">Бюджет важнее всего</label>
                                        <input
                                            type="text"
                                            id="budget_priority_strict_percent"
                                            name="budget_priority_strict_percent"
                                            class="form-control priority-adjustment-field"
                                            value="{{ old('budget_priority_strict_percent', $budgetPriorityStrictPercent ?? '-20%') }}"
                                            placeholder="-20%"
                                        >
                                        <small class="form-text text-muted">
                                            Для <code>budzet_vashnee_vsego</code>. Можно писать <code>-20%</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="budget_priority_balance_percent">Баланс бюджета и отдыха</label>
                                        <input
                                            type="text"
                                            id="budget_priority_balance_percent"
                                            name="budget_priority_balance_percent"
                                            class="form-control priority-adjustment-field"
                                            value="{{ old('budget_priority_balance_percent', $budgetPriorityBalancePercent ?? '0') }}"
                                            placeholder="0"
                                        >
                                        <small class="form-text text-muted">
                                            Для <code>bydget_vasgen</code>. Можно писать <code>0</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="budget_priority_relax_percent">Главное хорошо отдохнуть</label>
                                        <input
                                            type="text"
                                            id="budget_priority_relax_percent"
                                            name="budget_priority_relax_percent"
                                            class="form-control priority-adjustment-field"
                                            value="{{ old('budget_priority_relax_percent', $budgetPriorityRelaxPercent ?? '+20%') }}"
                                            placeholder="+20%"
                                        >
                                        <small class="form-text text-muted">
                                            Для <code>budget_ne_vagen</code>. Можно писать <code>+20%</code>.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Промты для развлечений</h5>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="entertainment_prompt_daily">Одно развлечение в день</label>
                                        <textarea
                                            id="entertainment_prompt_daily"
                                            name="entertainment_prompt_daily"
                                            class="form-control entertainment-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для варианта: одно развлечение в день..."
                                        >{{ old('entertainment_prompt_daily', $entertainmentPromptDaily ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>entertainment_prompt_daily</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="entertainment_prompt_every_two_days">Одно развлечение в 2 дня</label>
                                        <textarea
                                            id="entertainment_prompt_every_two_days"
                                            name="entertainment_prompt_every_two_days"
                                            class="form-control entertainment-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для варианта: одно развлечение в 2 дня..."
                                        >{{ old('entertainment_prompt_every_two_days', $entertainmentPromptEveryTwoDays ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>entertainment_prompt_every_two_days</code>.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="entertainment_prompt_every_three_days">Одно развлечение в 3 дня</label>
                                        <textarea
                                            id="entertainment_prompt_every_three_days"
                                            name="entertainment_prompt_every_three_days"
                                            class="form-control entertainment-prompt-textarea"
                                            rows="10"
                                            placeholder="Промт для варианта: одно развлечение в 3 дня..."
                                        >{{ old('entertainment_prompt_every_three_days', $entertainmentPromptEveryThreeDays ?? '') }}</textarea>
                                        <small class="form-text text-muted">
                                            Сохраняется в <code>budget_promt</code> как <code>entertainment_prompt_every_three_days</code>.
                                        </small>
                                    </div>
                                </div>
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
    const saveBtn = document.getElementById('saveBudgetPrompt');
    const alertBox = document.getElementById('budgetPromptAlert');
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
        const modelEl = document.getElementById('budget_ai_model');
        if (modelEl) {
            payload.budget_ai_model = modelEl.value;
        }
        document.querySelectorAll('.entertainment-prompt-textarea').forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });
        document.querySelectorAll('.food-prompt-textarea').forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });
        document.querySelectorAll('.car-prompt-textarea').forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });
        document.querySelectorAll('.priority-adjustment-field').forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });
        textareas.forEach(function (el) {
            if (el.name) {
                payload[el.name] = el.value;
            }
        });

        fetch(@json(route('admin.prompts-wp.budget.save', [], false)), {
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
                [
                    'cafe_prompt',
                    'restaurants_prompt',
                    'car_economy_prompt',
                    'car_medium_prompt',
                    'car_luxury_prompt',
                    'budget_priority_strict_percent',
                    'budget_priority_balance_percent',
                    'budget_priority_relax_percent',
                    'entertainment_prompt_daily',
                    'entertainment_prompt_every_two_days',
                    'entertainment_prompt_every_three_days'
                ].forEach(function (key) {
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
