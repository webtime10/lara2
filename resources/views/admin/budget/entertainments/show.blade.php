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
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.entertainments.index') }}">Бюджет — Развлечения</a></li>
                        <li class="breadcrumb-item active">{{ $region->label }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (! empty($error))
                <div class="alert alert-danger">{{ $error }}</div>
            @endif

            <div class="mb-3">
                <a href="{{ route('admin.budget.entertainments.index') }}" class="btn btn-sm btn-secondary">← Все кантоны</a>
                <a href="{{ route('admin.budget.entertainments.show', ['slug' => $region->slug, 'refresh' => 1]) }}" class="btn btn-sm btn-primary">Обновить из API</a>
                <select id="entertainmentsGeminiLevel" class="form-control form-control-sm d-inline-block ml-2" style="width: 240px;">
                    <option value="razvlechenia_odin_raz_v_den">Одно развлечение в день</option>
                    <option value="razvlechenia_odin_raz_v_dva_dnya">Одно развлечение в 2 дня</option>
                    <option value="razvlechenia_odin_raz_v_tri_dnya">Одно развлечение в 3 дня</option>
                </select>
                <button
                    type="button"
                    id="sendEntertainmentsToGemini"
                    class="btn btn-sm btn-success"
                    data-url="{{ route('admin.budget.entertainments.gemini', $region->slug) }}"
                >
                    Отправить в Gemini
                </button>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">JSON для Gemini</h3>
                </div>
                <div class="card-body">
                    <div id="entertainmentsGeminiAlert" class="alert d-none"></div>
                    <div class="form-group">
                        <label for="entertainmentsGeminiPayload">Данные развлечений</label>
                        <textarea id="entertainmentsGeminiPayload" class="form-control" rows="12" readonly>{{ json_encode($structuredPayload ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label for="entertainmentsGeminiAnswer">Ответ Gemini</label>
                        <textarea id="entertainmentsGeminiAnswer" class="form-control" rows="10" readonly></textarea>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        DataForSEO: <code>{{ $apiHint }}</code>.
                        В БД сохраняется категория развлечения, стоимость не рассчитывается.
                        @if ($region->entertainments_synced_at)
                            <br>Сохранено в БД: <strong>{{ $syncedCount ?? $items->total() }}</strong> развлечений,
                            обновлено {{ $region->entertainments_synced_at->format('d.m.Y H:i') }}.
                        @endif
                    </p>

                    @if (! empty($summary))
                        <p class="text-muted mb-3">
                            Объектов: <strong>{{ $summary['total_objects'] }}</strong>,
                            сетей/брендов: <strong>{{ $summary['total_brands'] }}</strong>.
                            @foreach ($summary['categories'] as $category => $row)
                                @if ($row['objects'] > 0)
                                    <br>{{ $category }}: {{ $row['objects'] }} объектов / {{ $row['brands'] }} сетей
                                @endif
                            @endforeach
                        </p>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th style="width: 150px;">Категория</th>
                                    <th style="width: 220px;">Сайт</th>
                                    <th style="width: 90px;">Рейтинг</th>
                                    <th style="width: 90px;">Отзывы</th>
                                    <th>Адрес</th>
                                    <th style="width: 220px;">place_id</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td><strong>{{ $item->category }}</strong></td>
                                        <td>
                                            @if ($item->website)
                                                <a href="{{ $item->website }}" target="_blank" rel="noopener">{{ $item->website }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $item->rating !== null ? number_format((float) $item->rating, 1) : '—' }}</td>
                                        <td>{{ $item->reviews ?? '—' }}</td>
                                        <td><small>{{ $item->address ?: '—' }}</small></td>
                                        <td><small style="word-break: break-all;">{{ $item->place_id ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Нет данных</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $items->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('sendEntertainmentsToGemini');
    var alertBox = document.getElementById('entertainmentsGeminiAlert');
    var payloadEl = document.getElementById('entertainmentsGeminiPayload');
    var answerEl = document.getElementById('entertainmentsGeminiAnswer');
    var levelEl = document.getElementById('entertainmentsGeminiLevel');
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    button?.addEventListener('click', function () {
        if (button.disabled) return;

        button.disabled = true;
        button.textContent = 'Отправляем...';
        showAlert('info', 'Отправляем развлечения в Gemini...');
        answerEl.value = '';

        fetch(button.getAttribute('data-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                entertainment_level: levelEl ? levelEl.value : null,
            }),
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
                if (data.payload) {
                    payloadEl.value = JSON.stringify(data.payload, null, 2);
                }
                answerEl.value = data.answer || '';
                showAlert('success', 'Gemini вернул ответ.');
            })
            .catch(function (err) {
                showAlert('danger', (err && err.message) ? err.message : 'Не удалось отправить в Gemini');
            })
            .finally(function () {
                button.disabled = false;
                button.textContent = 'Отправить в Gemini';
            });
    });
});
</script>
@endsection
