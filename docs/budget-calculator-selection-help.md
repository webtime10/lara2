# Budget calculator: как работает выборка из WordPress

Короткая нотация по бюджетному калькулятору: какие данные приходят из WordPress, где они сохраняются в Laravel и какие сервисы считают отдельные части бюджета.

## Общий поток

1. Пользователь проходит Vue-калькулятор на WordPress (`wp2.loc`).
2. WordPress отправляет POST-запрос в Laravel на `POST /api/plugins/budget`.
3. В Laravel запрос принимает `App\Http\Controllers\Api\Plugins\BudgetController`.
4. `BudgetIncomingRequest` валидирует структуру `language`, `session_token`, `answers.catalog`.
5. `BudgetIngestService` запускает основной AI-расчет бюджета.
6. `QuizAnswerMapper` превращает ответы WordPress в плоские поля таблицы `quiz_answers`.
7. `BudgetController` сохраняет запись `QuizAnswer`.
8. После сохранения отдельно считаются:
   - развлечения;
   - питание;
   - аренда авто;
   - проживание;
   - корректировка приоритета бюджета;
   - итоговая сумма.
9. Laravel возвращает WordPress JSON с разбивкой и итогами.

## Что приходит из WordPress

Основной объект приходит в `answers.catalog`.

Ключевые блоки:

```text
trip_dates.dateMode
trip_dates.dateFrom
trip_dates.dateTo
trip_dates.durationDays

travelers.quantity

children.hasChildren
children.quantity
children.ages

region.region

housing.housingType
comfort.comfortLevel
entertainment.entertainmentLevel
dining.diningLevel
car_rental.carRental
car_class.carClass
budget_priority.budgetPriority
```

## Сохранение в quiz_answers

Маппинг делает `App\Support\QuizAnswerMapper`.

Основные поля:

```text
language                  <- language из запроса
trip_date_mode             <- trip_dates.dateMode
trip_date_from             <- trip_dates.dateFrom
trip_date_to               <- trip_dates.dateTo
trip_duration_days         <- trip_dates.durationDays
total_days                 <- durationDays или расчет по dateFrom/dateTo
trip_months                <- месяцы поездки по датам
travelers_count            <- travelers.quantity
children_count             <- children.quantity
total_people               <- travelers_count + children_count
children_ages              <- children.ages
region                     <- region.region
housing_type               <- housing.housingType
comfort_level              <- comfort.comfortLevel
entertainment_level        <- entertainment.entertainmentLevel
dining_level               <- dining.diningLevel
car_rental                 <- car_rental.carRental
car_class                  <- car_class.carClass
budget_priority            <- budget_priority.budgetPriority
payload                    <- исходный catalog для отладки
```

## Проживание

Итог по проживанию считает `App\Services\Budget\TripBudgetTotalCalculator`.

Внутри он выбирает нужный калькулятор:

```text
housing_type = oteli
-> HotelBudgetCalculator

housing_type = apartamenty / apartamenti
-> ApartmentBudgetCalculator
```

### Отели

Файл: `App\Services\Budget\HotelBudgetCalculator`.

Смысл расчёта: Laravel не спрашивает Gemini про проживание. Он берет цены из своей базы `swiss_hotels` и считает проживание по выбранному региону, уровню комфорта, количеству людей и дням поездки.

Логика:

1. Берется `region` из `quiz_answers`.
2. По нему находится запись в `swiss_regions`.
3. `comfort_level` переводится в уровень:
   - `deshevle` -> `1`;
   - `sredniii` -> `2`;
   - `visokii` -> `3`.
4. В таблице `swiss_hotels` берется средняя цена `price_usd` по региону и уровню.
5. Считается количество номеров:
   - взрослые по 2 человека в номер;
   - первые 2 ребенка подселяются к взрослым;
   - каждые следующие 1-2 ребенка дают дополнительный номер.
6. Формула:

```text
средняя цена номера * количество номеров * total_days
```

Расшифровка:

```text
средняя цена номера = AVG(swiss_hotels.price_usd)
  где swiss_hotels.region_id = выбранный регион
  и swiss_hotels.level = уровень комфорта 1/2/3

количество номеров =
  ceil(взрослые / 2)
  + ceil(max(0, дети - 2) / 2)

total_days =
  количество дней поездки
```

Примеры количества номеров:

```text
1 взрослый -> 1 номер
2 взрослых -> 1 номер
3 взрослых -> 2 номера
4 взрослых -> 2 номера

2 взрослых + 1 ребенок -> 1 номер
2 взрослых + 2 ребенка -> 1 номер
2 взрослых + 3 ребенка -> 2 номера
2 взрослых + 4 ребенка -> 2 номера
```

Пример расчёта:

```text
region = bern
comfort_level = sredniii -> level 2
средняя цена номера = $180
взрослые = 3
дети = 1
total_days = 7

количество номеров = ceil(3 / 2) + ceil(max(0, 1 - 2) / 2)
количество номеров = 2 + 0 = 2

итого проживание = 180 * 2 * 7 = $2 520
```
data.switzerland-expert.com
calc.switzerland-expert.com
45.14.14.245
### Апартаменты

Файл: `App\Services\Budget\ApartmentBudgetCalculator`.

Смысл расчёта: для апартаментов Laravel также не спрашивает Gemini. Он берет среднюю цену из `swiss_apartments` по региону и уровню комфорта, затем умножает на количество нужных апартаментов и дней.

Логика:

1. Берется `region`.
2. По нему находится `swiss_regions`.
3. `comfort_level` переводится в уровень `1/2/3`.
4. В таблице `swiss_apartments` берется средняя цена `price_usd`.
5. Один апартамент условно покрывает до 4 человек.
6. Формула:

```text
средняя цена апартамента * ceil(total_people / 4) * total_days
```

Расшифровка:

```text
средняя цена апартамента = AVG(swiss_apartments.price_usd)
  где swiss_apartments.region_id = выбранный регион
  и swiss_apartments.level = уровень комфорта 1/2/3

количество апартаментов = ceil(total_people / 4)

total_people = adults + children
```

Пример расчёта:

```text
region = zurich
comfort_level = visokii -> level 3
средняя цена апартамента = $260
total_people = 5
total_days = 6

количество апартаментов = ceil(5 / 4) = 2

итого проживание = 260 * 2 * 6 = $3 120
```

## Развлечения

Файл: `App\Services\EntertainmentGeminiService`.

Выбор промта зависит от `entertainment_level`.

Промты:

```text
entertainment_prompt_daily
entertainment_prompt_every_two_days
entertainment_prompt_every_three_days
```

Логика выбора:

```text
если уровень содержит 3 / tri / three / neskolko / few
-> entertainment_prompt_every_three_days

если уровень содержит 2 / dva / two
-> entertainment_prompt_every_two_days

иначе
-> entertainment_prompt_daily
```

В Gemini отправляется структурированный список развлечений по региону + дополнительные данные:

```text
entertainment_level
total_days
total_people
```

Если Gemini не ответил или сумму не удалось распарсить, используется fallback:

```text
visits * total_people * 35
```

где `visits` зависит от выбранного уровня развлечений.

## Питание

Файл: `App\Services\FoodBudgetGeminiService`.

Выбор промта зависит от `dining_level`.

Промты:

```text
korzina_magazina
cafe_prompt
restaurants_prompt
```

Логика выбора:

```text
v_osnovnom / doma / home
-> korzina_magazina

nedorogie / kafe / cafe
-> cafe_prompt

иначе
-> restaurants_prompt
```

В промт подставляется payload:

```text
region
month
year
adults
children
days
language
dining_level
```

Если промт пустой, Gemini не ответил или сумму не удалось распарсить, используется fallback:

```text
days * adultEquivalent * dailyRate
```

Где:

```text
adultEquivalent = adults + children * 0.6
```

Ставки fallback:

```text
korzina_magazina  -> 35 в день
cafe_prompt       -> 55 в день
restaurants_prompt -> 95 в день
```

## Аренда авто

Файл: `App\Services\CarBudgetGeminiService`.

Сначала проверяется `car_rental`.

```text
da / yes / true / 1
-> авто нужно считать

любое другое значение
-> сумма авто = 0
```

Выбор промта зависит от `car_class`.

Промты:

```text
car_economy_prompt
car_medium_prompt
car_luxury_prompt
```

Логика выбора:

```text
deshov / econom / budget
-> car_economy_prompt

sredn / medium
-> car_medium_prompt

иначе
-> car_luxury_prompt
```

В промт подставляется payload:

```text
region
month
year
days
language
car_rental
car_class
```

Fallback:

```text
days * dailyRate
```

Ставки fallback:

```text
economy -> 65 в день
medium  -> 95 в день
luxury  -> 180 в день
```

## Корректировка приоритета бюджета

Файл: `App\Services\BudgetPriorityAdjustmentService`.

Процент зависит от `budget_priority`.

Настройки берутся из таблицы `budget_promt`.

Имена настроек:

```text
budget_priority_strict_percent
budget_priority_balance_percent
budget_priority_relax_percent
```

Значения по умолчанию:

```text
budget_priority_strict_percent  -> -20%
budget_priority_balance_percent -> 0
budget_priority_relax_percent   -> +20%
```

Логика:

```text
vashnee / vazhnee / strict
-> strict, минус к бюджету

ne_vagen / ne_vazhen / relax
-> relax, плюс к бюджету

иначе
-> balance, без изменения
```

Формула:

```text
adjustment = baseTotal * percent / 100
finalTotal = baseTotal + adjustment
```

Если корректировка уводит итог ниже нуля, итог ограничивается нулем.

## Итоговый бюджет

Файл: `App\Services\Budget\TripBudgetTotalCalculator`.

Сначала собирается базовая сумма:

```text
baseTotal =
  housing_budget_total
  + entertainment_budget_total
  + food_budget_total
  + car_budget_total
```

Потом применяется корректировка приоритета:

```text
total = baseTotal + budget_priority_adjustment_total
```

В `quiz_answers` сохраняется:

```text
housing_budget_total
budget_base_total
base_total
budget_priority_adjustment_total
total
budget_total
```

## Что Laravel возвращает WordPress

После расчёта `BudgetController` возвращает WordPress:

```text
budget.rows:
  Проживание
  Транспорт
  Развлечения
  Питание

budget.base_total
budget.final_total
budget.priority_adjustment

housing_budget_total
entertainment_budget_total
food_budget_total
car_budget_total
budget_base_total
budget_priority_adjustment_total
budget_total
item_total
quiz_answer_id
```

`item_total` на WordPress используется для вывода итоговой суммы на фронте.

## Где смотреть данные в админке

Основная таблица:

```text
/admin/budget-calculator
```

Контроллер:

```text
App\Http\Controllers\Admin\BudgetCalculatorController
```

Там видны сохранённые записи `quiz_answers`, отдельные суммы и итоговая сумма с корректировкой.
