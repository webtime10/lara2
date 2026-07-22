{{-- Ideal Region — поля шагов (статичный HTML) --}}
@php
    $v = static fn (string $field) => old($field.'_'.$c, isset($desc) ? ($desc->{$field} ?? '') : '');
@endphp

<hr class="mt-4 mb-3">
<p class="mb-3"><strong>Ideal Region — шаги</strong></p>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 1</span>
        <strong class="mr-2">Какой пейзаж вам нравится</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step1_gory_{{ $c }}">Горы</label>
        <input type="text" name="step1_gory_{{ $c }}" id="step1_gory_{{ $c }}" class="form-control" value="{{ $v('step1_gory') }}">
    </div>
    <div class="form-group">
        <label for="step1_gory_description_{{ $c }}">Горы — описание</label>
        <textarea name="step1_gory_description_{{ $c }}" id="step1_gory_description_{{ $c }}" class="form-control" rows="4">{{ $v('step1_gory_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step1_vodopady_{{ $c }}">Водопады</label>
        <input type="text" name="step1_vodopady_{{ $c }}" id="step1_vodopady_{{ $c }}" class="form-control" value="{{ $v('step1_vodopady') }}">
    </div>
    <div class="form-group">
        <label for="step1_vodopady_description_{{ $c }}">Водопады — описание</label>
        <textarea name="step1_vodopady_description_{{ $c }}" id="step1_vodopady_description_{{ $c }}" class="form-control" rows="4">{{ $v('step1_vodopady_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step1_ozera_{{ $c }}">Озёра</label>
        <input type="text" name="step1_ozera_{{ $c }}" id="step1_ozera_{{ $c }}" class="form-control" value="{{ $v('step1_ozera') }}">
    </div>
    <div class="form-group">
        <label for="step1_ozera_description_{{ $c }}">Озёра — описание</label>
        <textarea name="step1_ozera_description_{{ $c }}" id="step1_ozera_description_{{ $c }}" class="form-control" rows="4">{{ $v('step1_ozera_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step1_goroda_{{ $c }}">Города</label>
        <input type="text" name="step1_goroda_{{ $c }}" id="step1_goroda_{{ $c }}" class="form-control" value="{{ $v('step1_goroda') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step1_goroda_description_{{ $c }}">Города — описание</label>
        <textarea name="step1_goroda_description_{{ $c }}" id="step1_goroda_description_{{ $c }}" class="form-control" rows="4">{{ $v('step1_goroda_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 2</span>
        <strong class="mr-2">Что вам ближе в отдыхе</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step2_gulyat_{{ $c }}">Гулять</label>
        <input type="text" name="step2_gulyat_{{ $c }}" id="step2_gulyat_{{ $c }}" class="form-control" value="{{ $v('step2_gulyat') }}">
    </div>
    <div class="form-group">
        <label for="step2_gulyat_description_{{ $c }}">Гулять — описание</label>
        <textarea name="step2_gulyat_description_{{ $c }}" id="step2_gulyat_description_{{ $c }}" class="form-control" rows="4">{{ $v('step2_gulyat_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step2_otdyh_{{ $c }}">Отдых</label>
        <input type="text" name="step2_otdyh_{{ $c }}" id="step2_otdyh_{{ $c }}" class="form-control" value="{{ $v('step2_otdyh') }}">
    </div>
    <div class="form-group">
        <label for="step2_otdyh_description_{{ $c }}">Отдых — описание</label>
        <textarea name="step2_otdyh_description_{{ $c }}" id="step2_otdyh_description_{{ $c }}" class="form-control" rows="4">{{ $v('step2_otdyh_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step2_razvlecheniya_{{ $c }}">Развлечения</label>
        <input type="text" name="step2_razvlecheniya_{{ $c }}" id="step2_razvlecheniya_{{ $c }}" class="form-control" value="{{ $v('step2_razvlecheniya') }}">
    </div>
    <div class="form-group">
        <label for="step2_razvlecheniya_description_{{ $c }}">Развлечения — описание</label>
        <textarea name="step2_razvlecheniya_description_{{ $c }}" id="step2_razvlecheniya_description_{{ $c }}" class="form-control" rows="4">{{ $v('step2_razvlecheniya_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step2_restorany_{{ $c }}">Рестораны</label>
        <input type="text" name="step2_restorany_{{ $c }}" id="step2_restorany_{{ $c }}" class="form-control" value="{{ $v('step2_restorany') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step2_restorany_description_{{ $c }}">Рестораны — описание</label>
        <textarea name="step2_restorany_description_{{ $c }}" id="step2_restorany_description_{{ $c }}" class="form-control" rows="4">{{ $v('step2_restorany_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 3</span>
        <strong class="mr-2">Какой темп вам подходит</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step3_aktivnyi_{{ $c }}">Активный</label>
        <input type="text" name="step3_aktivnyi_{{ $c }}" id="step3_aktivnyi_{{ $c }}" class="form-control" value="{{ $v('step3_aktivnyi') }}">
    </div>
    <div class="form-group">
        <label for="step3_aktivnyi_description_{{ $c }}">Активный — описание</label>
        <textarea name="step3_aktivnyi_description_{{ $c }}" id="step3_aktivnyi_description_{{ $c }}" class="form-control" rows="4">{{ $v('step3_aktivnyi_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step3_srednii_{{ $c }}">Средний</label>
        <input type="text" name="step3_srednii_{{ $c }}" id="step3_srednii_{{ $c }}" class="form-control" value="{{ $v('step3_srednii') }}">
    </div>
    <div class="form-group">
        <label for="step3_srednii_description_{{ $c }}">Средний — описание</label>
        <textarea name="step3_srednii_description_{{ $c }}" id="step3_srednii_description_{{ $c }}" class="form-control" rows="4">{{ $v('step3_srednii_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step3_spokoinyi_{{ $c }}">Спокойный</label>
        <input type="text" name="step3_spokoinyi_{{ $c }}" id="step3_spokoinyi_{{ $c }}" class="form-control" value="{{ $v('step3_spokoinyi') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step3_spokoinyi_description_{{ $c }}">Спокойный — описание</label>
        <textarea name="step3_spokoinyi_description_{{ $c }}" id="step3_spokoinyi_description_{{ $c }}" class="form-control" rows="4">{{ $v('step3_spokoinyi_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 4</span>
        <strong class="mr-2">Что для вас важнее</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step4_kultura_{{ $c }}">Культура</label>
        <input type="text" name="step4_kultura_{{ $c }}" id="step4_kultura_{{ $c }}" class="form-control" value="{{ $v('step4_kultura') }}">
    </div>
    <div class="form-group">
        <label for="step4_kultura_description_{{ $c }}">Культура — описание</label>
        <textarea name="step4_kultura_description_{{ $c }}" id="step4_kultura_description_{{ $c }}" class="form-control" rows="4">{{ $v('step4_kultura_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step4_eda_{{ $c }}">Еда</label>
        <input type="text" name="step4_eda_{{ $c }}" id="step4_eda_{{ $c }}" class="form-control" value="{{ $v('step4_eda') }}">
    </div>
    <div class="form-group">
        <label for="step4_eda_description_{{ $c }}">Еда — описание</label>
        <textarea name="step4_eda_description_{{ $c }}" id="step4_eda_description_{{ $c }}" class="form-control" rows="4">{{ $v('step4_eda_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step4_komfort_{{ $c }}">Комфорт</label>
        <input type="text" name="step4_komfort_{{ $c }}" id="step4_komfort_{{ $c }}" class="form-control" value="{{ $v('step4_komfort') }}">
    </div>
    <div class="form-group">
        <label for="step4_komfort_description_{{ $c }}">Комфорт — описание</label>
        <textarea name="step4_komfort_description_{{ $c }}" id="step4_komfort_description_{{ $c }}" class="form-control" rows="4">{{ $v('step4_komfort_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step4_priroda_{{ $c }}">Природа</label>
        <input type="text" name="step4_priroda_{{ $c }}" id="step4_priroda_{{ $c }}" class="form-control" value="{{ $v('step4_priroda') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step4_priroda_description_{{ $c }}">Природа — описание</label>
        <textarea name="step4_priroda_description_{{ $c }}" id="step4_priroda_description_{{ $c }}" class="form-control" rows="4">{{ $v('step4_priroda_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 5</span>
        <strong class="mr-2">С кем вы путешествуете</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step5_odin_{{ $c }}">Один</label>
        <input type="text" name="step5_odin_{{ $c }}" id="step5_odin_{{ $c }}" class="form-control" value="{{ $v('step5_odin') }}">
    </div>
    <div class="form-group">
        <label for="step5_odin_description_{{ $c }}">Один — описание</label>
        <textarea name="step5_odin_description_{{ $c }}" id="step5_odin_description_{{ $c }}" class="form-control" rows="4">{{ $v('step5_odin_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step5_druzya_{{ $c }}">Друзья</label>
        <input type="text" name="step5_druzya_{{ $c }}" id="step5_druzya_{{ $c }}" class="form-control" value="{{ $v('step5_druzya') }}">
    </div>
    <div class="form-group">
        <label for="step5_druzya_description_{{ $c }}">Друзья — описание</label>
        <textarea name="step5_druzya_description_{{ $c }}" id="step5_druzya_description_{{ $c }}" class="form-control" rows="4">{{ $v('step5_druzya_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step5_semya_{{ $c }}">Семья</label>
        <input type="text" name="step5_semya_{{ $c }}" id="step5_semya_{{ $c }}" class="form-control" value="{{ $v('step5_semya') }}">
    </div>
    <div class="form-group">
        <label for="step5_semya_description_{{ $c }}">Семья — описание</label>
        <textarea name="step5_semya_description_{{ $c }}" id="step5_semya_description_{{ $c }}" class="form-control" rows="4">{{ $v('step5_semya_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step5_para_{{ $c }}">Пара</label>
        <input type="text" name="step5_para_{{ $c }}" id="step5_para_{{ $c }}" class="form-control" value="{{ $v('step5_para') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step5_para_description_{{ $c }}">Пара — описание</label>
        <textarea name="step5_para_description_{{ $c }}" id="step5_para_description_{{ $c }}" class="form-control" rows="4">{{ $v('step5_para_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 6</span>
        <strong class="mr-2">Что вы хотите от поездки</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step6_vkusnaya_eda_{{ $c }}">Вкусная еда</label>
        <input type="text" name="step6_vkusnaya_eda_{{ $c }}" id="step6_vkusnaya_eda_{{ $c }}" class="form-control" value="{{ $v('step6_vkusnaya_eda') }}">
    </div>
    <div class="form-group">
        <label for="step6_vkusnaya_eda_description_{{ $c }}">Вкусная еда — описание</label>
        <textarea name="step6_vkusnaya_eda_description_{{ $c }}" id="step6_vkusnaya_eda_description_{{ $c }}" class="form-control" rows="4">{{ $v('step6_vkusnaya_eda_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step6_krasivye_vidy_{{ $c }}">Красивые виды</label>
        <input type="text" name="step6_krasivye_vidy_{{ $c }}" id="step6_krasivye_vidy_{{ $c }}" class="form-control" value="{{ $v('step6_krasivye_vidy') }}">
    </div>
    <div class="form-group">
        <label for="step6_krasivye_vidy_description_{{ $c }}">Красивые виды — описание</label>
        <textarea name="step6_krasivye_vidy_description_{{ $c }}" id="step6_krasivye_vidy_description_{{ $c }}" class="form-control" rows="4">{{ $v('step6_krasivye_vidy_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step6_vpechatleniya_{{ $c }}">Впечатления</label>
        <input type="text" name="step6_vpechatleniya_{{ $c }}" id="step6_vpechatleniya_{{ $c }}" class="form-control" value="{{ $v('step6_vpechatleniya') }}">
    </div>
    <div class="form-group">
        <label for="step6_vpechatleniya_description_{{ $c }}">Впечатления — описание</label>
        <textarea name="step6_vpechatleniya_description_{{ $c }}" id="step6_vpechatleniya_description_{{ $c }}" class="form-control" rows="4">{{ $v('step6_vpechatleniya_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step6_otdohnut_{{ $c }}">Отдохнуть</label>
        <input type="text" name="step6_otdohnut_{{ $c }}" id="step6_otdohnut_{{ $c }}" class="form-control" value="{{ $v('step6_otdohnut') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step6_otdohnut_description_{{ $c }}">Отдохнуть — описание</label>
        <textarea name="step6_otdohnut_description_{{ $c }}" id="step6_otdohnut_description_{{ $c }}" class="form-control" rows="4">{{ $v('step6_otdohnut_description') }}</textarea>
    </div>
</div>

<div class="border-top pt-3 mt-3 mb-2">
    <div class="d-flex align-items-center mb-3">
        <span class="badge badge-primary mr-2" style="font-size: 0.85rem; padding: 0.4em 0.7em;">Шаг 7</span>
        <strong class="mr-2">Что вам интереснее</strong>
        <span class="flex-grow-1 border-top ml-2" style="height: 0; border-top-width: 2px !important;"></span>
    </div>
    <div class="form-group">
        <label for="step7_parki_{{ $c }}">Парки</label>
        <input type="text" name="step7_parki_{{ $c }}" id="step7_parki_{{ $c }}" class="form-control" value="{{ $v('step7_parki') }}">
    </div>
    <div class="form-group">
        <label for="step7_parki_description_{{ $c }}">Парки — описание</label>
        <textarea name="step7_parki_description_{{ $c }}" id="step7_parki_description_{{ $c }}" class="form-control" rows="4">{{ $v('step7_parki_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step7_muzei_{{ $c }}">Музеи</label>
        <input type="text" name="step7_muzei_{{ $c }}" id="step7_muzei_{{ $c }}" class="form-control" value="{{ $v('step7_muzei') }}">
    </div>
    <div class="form-group">
        <label for="step7_muzei_description_{{ $c }}">Музеи — описание</label>
        <textarea name="step7_muzei_description_{{ $c }}" id="step7_muzei_description_{{ $c }}" class="form-control" rows="4">{{ $v('step7_muzei_description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="step7_progulki_{{ $c }}">Прогулки</label>
        <input type="text" name="step7_progulki_{{ $c }}" id="step7_progulki_{{ $c }}" class="form-control" value="{{ $v('step7_progulki') }}">
    </div>
    <div class="form-group">
        <label for="step7_progulki_description_{{ $c }}">Прогулки — описание</label>
        <textarea name="step7_progulki_description_{{ $c }}" id="step7_progulki_description_{{ $c }}" class="form-control" rows="4">{{ $v('step7_progulki_description') }}</textarea>
    </div>
    <div class="form-group mb-0">
        <label for="step7_shopping_razvlecheniya_{{ $c }}">Шоппинг и развлечения</label>
        <input type="text" name="step7_shopping_razvlecheniya_{{ $c }}" id="step7_shopping_razvlecheniya_{{ $c }}" class="form-control" value="{{ $v('step7_shopping_razvlecheniya') }}">
    </div>
    <div class="form-group mb-0">
        <label for="step7_shopping_razvlecheniya_description_{{ $c }}">Шоппинг и развлечения — описание</label>
        <textarea name="step7_shopping_razvlecheniya_description_{{ $c }}" id="step7_shopping_razvlecheniya_description_{{ $c }}" class="form-control" rows="4">{{ $v('step7_shopping_razvlecheniya_description') }}</textarea>
    </div>
</div>
