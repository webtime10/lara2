{{-- Ideal Region — шаги квиза (генерация из config/ideal_region_category_fields.php) --}}
@php
    $irSteps = (array) config('ideal_region_category_fields.steps', []);
    $irSlots = (array) config('ideal_region_category_fields.step_slots', []);
    $irLabels = (array) config('ideal_region_category_fields.labels', []);
    $irRules = (array) config('ideal_region_category_fields.selection_rules', []);
    $v = static fn (string $field) => old($field.'_'.$c, isset($desc) ? ($desc->{$field} ?? '') : '');
@endphp

<hr class="mt-4 mb-3">
<p class="mb-3"><strong>Ideal Region — шаги</strong></p>

@foreach ($irSlots as $stepKey => $slots)
    @php
        $stepTitle = $irSteps[$stepKey] ?? $stepKey;
        $stepNum = (int) str_replace('step', '', (string) $stepKey);
        $rule = $irRules[$stepKey] ?? [];
        $max = isset($rule['max']) ? (int) $rule['max'] : 1;
        $ruleHint = $max === 1
            ? 'Один вариант ответа'
            : ('До '.$max.' вариантов ответа');
        if (! empty($rule['exclusive_slot'])) {
            $ruleHint .= '; «Дополнительных пожеланий нет» блокирует остальные';
        }
        $slotList = array_values((array) $slots);
    @endphp

    <div class="border-top pt-3 mt-3 mb-2">
        <div class="d-flex align-items-center mb-2">
            <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг {{ $stepNum }}</span>
            <strong class="mr-2">{{ $stepTitle }}</strong>
            <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
        </div>
        <p class="text-muted small mb-3">{{ $ruleHint }}</p>

        @foreach ($slotList as $slotIndex => $slot)
            @php
                $field = $stepKey.'_'.$slot;
                $descField = $field.'_description';
                $label = $irLabels[$field] ?? $slot;
                $descLabel = $irLabels[$descField] ?? ($label.' — описание');
                $isLast = $slotIndex === array_key_last($slotList);
            @endphp

            <div class="form-group {{ $isLast ? 'mb-0' : '' }}">
                <label for="{{ $field }}_{{ $c }}">{{ $label }}</label>
                <input
                    type="text"
                    name="{{ $field }}_{{ $c }}"
                    id="{{ $field }}_{{ $c }}"
                    class="form-control"
                    value="{{ $v($field) }}"
                >
            </div>
            <div class="form-group {{ $isLast ? 'mb-0' : '' }}">
                <label for="{{ $descField }}_{{ $c }}">{{ $descLabel }}</label>
                <textarea
                    name="{{ $descField }}_{{ $c }}"
                    id="{{ $descField }}_{{ $c }}"
                    class="form-control"
                    rows="4"
                >{{ $v($descField) }}</textarea>
            </div>
        @endforeach
    </div>
@endforeach
