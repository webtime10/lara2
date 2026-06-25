<?php

namespace App\Services\Budget;

use App\Models\QuizAnswer;
use App\Models\SwissHotel;
use App\Models\SwissRegion;

class HotelBudgetCalculator
{

/*
2 взрослых + 1 ребёнок
rooms = 1
extra = +10%

2 взрослых + 2 ребёнка
rooms = 1
extra = +20%

2 взрослых + 3 ребёнка
rooms = 2
extra = +20%

2 взрослых + 4 ребёнка
rooms = 2
extra = +20%

2 взрослых + 7 детей
rooms = 4
extra = +20%
1 взрослый = 1 номер
2 взрослых = 1 номер
3 взрослых = 2 номера
4 взрослых = 2 номера
5 взрослых = 3 номера
1 взрослый → 1 номер
2 взрослых → 1 номер
3 взрослых → 2 номера
4 взрослых → 2 номера
5 взрослых → 3 номера
6 взрослых → 3 номера
*/

    /**
     * Главный метод для применения расчёта.
     *
     * Он не просто считает сумму, а сразу записывает результат в quiz_answers.budget_total,
     * чтобы эта сумма появилась в админской таблице Budget калькулятора.
     */
    public function applyTo(QuizAnswer $answer): ?float
    {
        // Сначала считаем стоимость проживания по отелям.
        $total = $this->calculate($answer);

        // Если посчитать нельзя (не отели, нет региона, нет дней или нет цен), ничего не записываем.
        if ($total === null) {
            return null;
        }

        // Записываем готовую сумму в существующую колонку "Бюджет total".
        // Формат делаем человекочитаемый: $5 018, $10 629 и т.д.
        $answer->forceFill([
            'budget_total' => '$'.number_format($total, 0, '.', ' '),
        ])->save();

        return $total;
    }

    public function calculate(QuizAnswer $answer): ?float
    {
        // Сейчас считаем только вариант "отели".
        // Если пользователь выбрал другой тип жилья, этот сервис его не трогает.
        if ($answer->housing_type !== 'oteli') {
            return null;
        }

        // Берём общее количество дней поездки, которое уже посчитано в QuizAnswerMapper.
        $days = (int) ($answer->total_days ?? 0);
        if ($days <= 0) {
            return null;
        }

        // Находим кантон/регион из swiss_regions по тому значению, которое пришло из WordPress.
        $region = $this->region($answer);
        if ($region === null) {
            return null;
        }

        // comfort_level из WordPress переводим в наш hotel level:
        // deshevle = 1, sredniii = 2, visokii = 3.
        $level = $this->comfortLevel((string) $answer->comfort_level);

        // Берём среднюю цену 2-местного номера в выбранном регионе и выбранном уровне.
        // Например: только Bern + только level 3.
        $roomPrice = SwissHotel::query()
            ->where('region_id', $region->id)
            ->where('level', $level)
            ->avg('price_usd');

        // Если цен по региону/уровню нет, расчёт невозможен.
        if ($roomPrice === null || (float) $roomPrice <= 0.0) {
            return null;
        }

        // Итог проживания:
        // средняя цена номера за ночь * количество нужных номеров * количество дней.
        return round((float) $roomPrice * $this->roomsCount($answer) * $days, 2);
    }

    private function region(QuizAnswer $answer): ?SwissRegion
    {
        // В quiz_answers.region хранится slug вроде bern, schwyz, basel-stadt.
        $region = trim((string) $answer->region);
        if ($region === '') {
            return null;
        }

        // Ищем регион по slug, а на всякий случай ещё и по label.
        return SwissRegion::query()
            ->where('slug', $region)
            ->orWhere('label', $region)
            ->first();
    }

    private function comfortLevel(string $comfortLevel): int
    {
        // Значения приходят из WordPress.
        // Всё неизвестное считаем средним уровнем, чтобы расчёт не падал.
        return match ($comfortLevel) {
            'deshevle' => 1,
            'visokii' => 3,
            default => 2,
        };
    }

    private function roomsCount(QuizAnswer $answer): int
    {
        // travelers_count — сколько всего путешественников выбрали в форме.
        $travelers = max(1, (int) $answer->travelers_count);

        // children_count — сколько детей выбрали отдельно.
        $children = max(0, (int) $answer->children_count);

        // В текущих данных travelers_count уже означает взрослых/путешественников,
        // а children_count приходит отдельным полем. Поэтому взрослых берём напрямую.
        $adults = $travelers;

        // Взрослые живут по 2 человека в двухместном номере:
        // 1-2 взрослых = 1 номер, 3-4 взрослых = 2 номера и т.д.
        $adultRooms = max(1, (int) ceil($adults / 2));

        // Семейная схема:
        // до 2 детей можно подселить к взрослым без отдельного номера.
        // Если детей больше 2, каждые следующие 2 ребёнка дают ещё 1 номер.
        $extraChildren = max(0, $children - 2);
        $childRooms = (int) ceil($extraChildren / 2);

        return $adultRooms + $childRooms;
    }
}
/*
Логика простая: взрослые селятся по 2 человека в номер.Функция ceil()
 округляет дробь в большую сторону.Если взрослых 1 или 2 $\rightarrow$ $ceil(1/2)$ 
 или $ceil(2/2) = 1$ номер.Если взрослых 3 $\rightarrow$ $ceil(3/2) = 2$ 
 номера.Функция max(1, ...) здесь избыточна, так как $adults всегда $\ge 1$,
  но она служит дополнительной страховкой, чтобы всегда возвращался хотя бы 1 номер.
$extraChildren = max(0, $children - 2);
$childRooms = (int) ceil($extraChildren / 2);
Здесь заложена бизнес-логика: первых 2-х детей можно подселить в номера к взрослым 
(считается, что они спят на дополнительных кроватях или с родителями, 
поэтому отдельный номер им не нужен).$extraChildren — это количество детей, 
которые «не поместились» к взрослым. Мы вычитаем 2 из общего числа детей.
Если детей 1 или 2 $\rightarrow$ $extraChildren станет 0,
 и дополнительные номера не потребуются ($childRooms = 0).Если детей больше 2, 
 то каждые следующие 1–2 ребенка требуют еще один отдельный номер
  (опять же с округлением ceil в большую сторону).
*/