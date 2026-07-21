{{-- Ideal Region step fields for category language tabs --}}
@php
    $irConfig = (array) config('ideal_region_category_fields', []);
    $irFields = array_values(array_filter(
        (array) ($irConfig['fields'] ?? $irConfig),
        fn ($f) => is_string($f) && str_starts_with($f, 'step')
    ));
    $irSteps = (array) ($irConfig['steps'] ?? []);
    $irLabels = (array) ($irConfig['labels'] ?? []);

    $irByStep = [];
    foreach ($irFields as $irField) {
        if (preg_match('/^(step\d+)/', $irField, $m)) {
            $irByStep[$m[1]][] = $irField;
        } else {
            $irByStep['other'][] = $irField;
        }
    }
@endphp
@if (!empty($irByStep))
    <hr class="mt-4 mb-3">
    <p class="mb-3"><strong>Ideal Region — шаги</strong></p>

    @foreach ($irByStep as $stepKey => $stepFields)
        @php
            $stepNum = preg_match('/^step(\d+)$/', $stepKey, $sm) ? (int) $sm[1] : null;
            $stepTitle = $irSteps[$stepKey] ?? ($stepNum ? 'Шаг '.$stepNum : $stepKey);
        @endphp

        <div class="border-top pt-3 mt-3 mb-2">
            <div class="d-flex align-items-center mb-3">
                @if ($stepNum)
                    <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг {{ $stepNum }}</span>
                @endif
                <strong class="mr-2">{{ $stepTitle }}</strong>
                <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
            </div>

            @foreach ($stepFields as $irField)
                @php
                    $irName = $irField.'_'.$c;
                    $irValue = old($irName, isset($desc) ? ($desc->{$irField} ?? '') : '');
                    $isTextarea = str_ends_with($irField, '_description');
                    $irLabel = $irLabels[$irField] ?? $irField;
                @endphp
                <div class="form-group {{ $loop->last ? 'mb-0' : '' }}">
                    <label for="{{ $irName }}">{{ $irLabel }}</label>
                    @if ($isTextarea)
                        <textarea name="{{ $irName }}" id="{{ $irName }}" class="form-control" rows="4">{{ $irValue }}</textarea>
                    @else
                        <input type="text" name="{{ $irName }}" id="{{ $irName }}" class="form-control" value="{{ $irValue }}">
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
@endif
