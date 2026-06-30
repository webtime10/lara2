# Budget calculator: как работает выборка из WordPress

Нотация по бюджетному калькулятору: какие данные приходят из WordPress, где они сохраняются в Laravel, как запускается фоновый расчёт, как WordPress узнаёт статус и какие сервисы считают отдельные части бюджета.

## Общий поток

1. Пользователь проходит Vue-калькулятор на WordPress (`wp2.loc`).
2. На последнем шаге WordPress отправляет POST-запрос в Laravel на `POST /api/plugins/budget`.
3. В Laravel запрос принимает `App\Http\Controllers\Api\Plugins\BudgetController`.
4. `BudgetIncomingRequest` валидирует структуру `language`, `session_token`, `answers.catalog`.
5. `QuizAnswerMapper` превращает ответы WordPress в плоские поля таблицы `quiz_answers`.
6. `BudgetController` сразу сохраняет новую запись `QuizAnswer`.
7. Сразу после сохранения Laravel ставит этой записи статус `pending`.
8. Laravel запускает фоновую задачу `ProcessBudgetCalculationJob`.
9. Laravel сразу отвечает WordPress, не ожидая полный расчёт:

```json
{
  "ok": true,
  "status": "processing",
  "message": "Budget calculation queued.",
  "quiz_answer_id": 123
}
```

10. WordPress не переходит на результат сразу. Он показывает окно процесса.
11. WordPress запускает polling: отдельным AJAX-запросом периодически спрашивает статус расчёта.
12. Worker Laravel в фоне берёт задачу из очереди и считает бюджет.
13. Когда расчёт готов, статус записи становится `completed`.
14. WordPress получает готовые суммы через статус-запрос и только после этого показывает экран результата.

## Почему расчёт сделан через очередь

Раньше весь бюджет считался внутри одного HTTP-запроса:

```text
WordPress -> Laravel -> долгий расчёт бюджета -> итог -> WordPress
```

Это долго. На хостинге сервер может оборвать ожидание через 30 секунд и вернуть `Gateway Timeout`.

Теперь первый запрос короткий:

```text
WordPress -> Laravel -> сохранить заявку -> поставить job -> вернуть quiz_answer_id
```

А расчёт выполняет worker отдельно:

```text
queue worker -> ProcessBudgetCalculationJob -> BudgetCalculationService -> prices from DB -> quiz_answers
```

Так пользователь не упирается в timeout первого запроса.

## Что такое pending / processing / completed

В таблице `quiz_answers` есть поля статуса:

```text
calculation_status
calculation_error
calculation_started_at
calculation_completed_at
```

Статусы:

```text
pending
  заявка создана, задача поставлена в очередь, worker ещё не начал расчёт

processing
  worker взял задачу и сейчас считает бюджет

completed
  расчёт завершён, все суммы готовы

failed
  расчёт упал с ошибкой, текст ошибки лежит в calculation_error
```

## Как WordPress узнаёт, что расчёт готов

Первый AJAX WordPress отправляет ответы пользователя:

```text
action = ai_calculator_budget_submit
```

WordPress получает:

```text
quiz_answer_id
status = processing
```

После этого WordPress запускает второй AJAX:

```text
action = ai_calculator_budget_status
quiz_answer_id = 123
```

Этот AJAX внутри WordPress обращается в Laravel:

```text
GET /api/plugins/budget/status/123
```

Если Laravel отвечает:

```text
status = pending
или
status = processing
```

WordPress ждёт и спрашивает ещё раз через несколько секунд.

Если Laravel отвечает:

```text
status = completed
```

WordPress забирает:

```text
budget.rows
budget_base_total
priority_adjustment
item_total
quiz_answer_id
```

и показывает экран результата.

Если Laravel отвечает:

```text
status = failed
```

WordPress показывает ошибку расчёта.

## Что должно быть запущено на сервере

Для фонового расчёта обязательно нужен worker очереди:

```bash
php artisan queue:work --tries=1 --timeout=180
```

В `.env`:

```env
QUEUE_CONNECTION=database
```

Таблица `jobs` должна существовать. Она создаётся миграцией.

Если worker не запущен, то заявка сохранится, но будет висеть в `pending` или `processing`, а WordPress будет ждать готовый статус.

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
calculation_status         <- pending / processing / completed / failed
calculation_error          <- ошибка фонового расчёта, если он упал
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

Примеры количества апартаментов:

```text
1 человек -> 1 апартамент
2 человека -> 1 апартамент
3 человека -> 1 апартамент
4 человека -> 1 апартамент

5 человек -> 2 апартамента
6 человек -> 2 апартамента
7 человек -> 2 апартамента
8 человек -> 2 апартамента

9 человек -> 3 апартамента
10 человек -> 3 апартамента
11 человек -> 3 апартамента
12 человек -> 3 апартамента
```

То есть считается не по отдельным людям, а блоками по 4 человека. Если людей больше 4 хотя бы на одного человека, Laravel берет следующий апартамент: 5 человек = 2 апартамента, 9 человек = 3 апартамента.

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

Текущая рабочая логика расчёта: Laravel берёт выбранный пользователем регион и уровень развлечений, находит ручные цены развлечений в базе и считает сумму внутри Laravel. Gemini в пользовательском расчёте не вызывается.

Ручные цены хранятся в `entertainment_visit_prices`:

```text
region_id
category
adult_avg_price
child_avg_price
currency
places_count
```

В админке цены заполняются в разделе `Бюджет — Цены развлечений`.

Если для региона и категории есть ручная цена, Laravel считает:

```text
(adults * adult_avg_price + children * child_avg_price) * количество выбранных посещений
```

Если `child_avg_price` не заполнен, Laravel берёт `adult_avg_price * 0.6`.

Если ручных цен для региона ещё нет, используется fallback:

```text
visits * total_people * 35
```

Gemini/AI можно использовать отдельно как помощник для подготовки или обновления цен, но не в основном пользовательском расчёте.

Сырьё развлечений остаётся в базе Laravel:

Это сырьё можно использовать для ручного заполнения цен и контроля категорий:

```text
зоопарки
музеи
кино
escape room
boat tour
theme park
amusement park
aquarium
ski resort
и другие категории, если они есть по региону
```

Для расчёта количества посещений Laravel использует:

```text
entertainment_level  -> какой режим развлечений выбрал пользователь
total_days           -> сколько дней поездка
total_people         -> сколько всего людей
```

## Как выбирается частота развлечений

Частота зависит от `entertainment_level`, который пришёл из WordPress:

```text
если выбрано "развлечения каждый день"
или значение не распознано
-> развлечения каждый день

если выбрано "развлечения раз в 2 дня"
или значение содержит 2 / dva / two
-> развлечения раз в 2 дня

если выбрано "развлечения раз в несколько дней"
или значение содержит 3 / tri / three / neskolko / few
-> развлечения примерно раз в 3 дня
```

Простыми словами:

```text
каждый день       -> считаем, что развлечения будут каждый день
раз в 2 дня       -> считаем посещения примерно через день
раз в 3 дня       -> считаем посещения примерно раз в три дня
```

## Старый Gemini-сценарий развлечений

Старый сценарий с отправкой развлечений в Gemini оставлен как справочная/вспомогательная логика, но не используется в основном пользовательском расчёте.

Раньше сервис брал регион из `quiz_answers.region`, находил его в `swiss_regions`, затем через `SwissEntertainmentsService` собирал структурированный список развлечений.

Пример payload:

```text
region = lucerne
total_days = 6
total_people = 3
entertainment_level = razvlechenia_raz_v_neskolko_dnay
entertainment_prompt_name = entertainment_prompt_every_three_days

attractions:
  zoo:
    - AmaZOOnas
    - Toni's Zoo
  museum:
    - Gameorama Spielmuseum
    - Aeschbach Chocolatier
  cinema:
    - Kino Cinebar
  boat_tour:
    - Schifflände SGV Luzern
```

В старом сценарии Gemini должен был вернуть только число или сумму, например:

```text
229
$229
```

Laravel вытаскивал из ответа число и сохранял его в:

```text
quiz_answers.entertainment_budget_total
```

## Если ручных цен развлечений нет

Если ручные цены развлечений для региона ещё не заполнены, Laravel не останавливает весь калькулятор. Он использует fallback-формулу.

```text
visits * total_people * 35
```

Где:

```text
visits = количество посещений развлечений
total_people = взрослые + дети
35 = условная средняя цена одного развлечения на человека
```

Как считается `visits`:

```text
каждый день:
visits = total_days

раз в 2 дня:
visits = ceil(total_days / 2)

раз в 3 дня:
visits = ceil(total_days / 3)
```

Пример fallback:

```text
total_days = 6
total_people = 3
режим = раз в 3 дня

visits = ceil(6 / 3) = 2
итого развлечения = 2 * 3 * 35 = $210
```

Если ручных цен развлечений нет, используется fallback, чтобы калькулятор всё равно мог вернуть итог.

## Питание

Файл: `App\Services\FoodBudgetGeminiService`.

Текущая рабочая логика расчёта: питание считается внутри Laravel по ручным ценам из базы. Gemini в пользовательском расчёте не вызывается.

Выбор режима питания зависит от `dining_level`.

Внутренние режимы:

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

Для расчёта Laravel собирает данные:

```text
region
month
year
adults
children
days
language
dining_level
food_sources
food_places
```

Эти данные используются для расчёта внутри Laravel и для контроля сырья по региону.

`food_sources` — это ручные цены продуктовой корзины из админки `Бюджет — Цены питания`. В расчёт корзины уходят только записи типа `home_cooking / Продуктовая корзина`, чтобы кафе и рестораны не смешивались с продуктовой корзиной.

Для `korzina_magazina` Laravel считает питание по продуктовой корзине. Кафе и рестораны в этот режим не подмешиваются.

В текущей рабочей логике `korzina_magazina` считается без Gemini:

```text
avg(sum(grocery prices from food_sources)) * days * adultEquivalent
```

Для кафе и ресторанов Laravel берёт ручные средние чеки из `food_visit_prices`:

```text
cafe_prompt        -> food_visit_prices.food_type = cafe
restaurants_prompt -> food_visit_prices.food_type = restaurant
```

Формула:

```text
days * ((adults * adult_avg_price) + (children * child_avg_price))
```

`food_places` — это заведения по выбранному региону, но их нужно подбирать под выбранный `dining_level`, а не отправлять все кафе и рестораны одной кучей:

```text
cafe_prompt
-> передавать кафе / недорогие кафе / casual places

restaurants_prompt
-> передавать рестораны / restaurant / restaurant_candidate
```

Сначала Laravel берет подготовленную выборку из `food_samples`:

```text
cafe
restaurant
restaurant_candidate
```

Если `food_samples` по региону пустые, Laravel берет запасной список из `food_imports`. Импортированные кафе/рестораны остаются сырьём для контроля и ручного обновления цен, но в пользовательском расчёте сумма берётся из `food_visit_prices`.

Если ручная цена не заполнена, используется fallback:

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

Текущая рабочая логика расчёта: авто считается внутри Laravel по ручным ценам из `car_rental_prices`. Gemini в пользовательском расчёте не вызывается.

Сначала проверяется `car_rental`.

```text
da / yes / true / 1
-> авто нужно считать

любое другое значение
-> сумма авто = 0
```

Выбор класса цены зависит от `car_class`.

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

Для расчёта Laravel использует:

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

## Gemini и кэш

В текущей рабочей схеме пользовательский расчёт бюджета не вызывает Gemini для развлечений, питания и авто.

Основной расчёт берёт цены из таблиц:

```text
entertainment_visit_prices
food_sources
food_visit_prices
car_rental_prices
```

Поэтому кэш Gemini больше не участвует в пользовательском расчёте этих блоков.

Gemini/AI можно оставить как ручной помощник в админке: разово подготовить или обновить цены, проверить сырьё, затем сохранить цены в базе. Автоматическую ежемесячную подгрузку пока не делаем.

Если пользователь не берёт авто, сумма авто сразу `0`.

### Почему цены не привязаны к точной дате

Для бюджета важнее не конкретная дата `30.06.2026`, а практические параметры:

```text
месяц
регион
количество дней
количество людей
выбранный уровень
```

Например две поездки в июне на 6 дней в Zurich с одинаковым уровнем питания можно считать одинаковыми для примерного бюджета. Поэтому ручные цены достаточно обновлять периодически, когда они реально меняются.

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
