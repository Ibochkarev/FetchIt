# FetchIt

Компонент для MODX Revolution: отправка форм через Fetch API.

![Логотип FetchIt](https://github.com/GulomovCreative/FetchIt/blob/next/fetchit-logo.svg?raw=true&v=3)

В MODX Revolution [FormIt](https://github.com/Sterc/FormIt) шлёт формы обычным POST с перезагрузкой страницы. FetchIt берёт FormIt (или ваш сниппет) и обрабатывает ту же форму без перезагрузки, через Fetch API.

По серверной части близок к [AjaxForm](https://github.com/modx-pro/AjaxForm). Отличия в основном на фронте.

## Никаких зависимостей

FetchIt не подключает внешние JS-библиотеки. У AjaxForm их три: [jQuery](https://github.com/jquery/jquery), [jquery-form](https://github.com/jquery-form/form/) и [jGrowl](https://github.com/stanlemon/jGrowl).

Уведомления в AjaxForm можно заменить. jQuery и jquery-form выкинуть уже сложнее.

## Современный код

Минифицированный скрипт около 4 КБ. Сниппет вешает его с `defer`, чтобы не тормозить загрузку. На клиенте работают нативный Fetch API и `FormData`, в том числе с файлами.

## Удобство

Чанк формы и атрибуты `data-error` обычно хватает, вёрстку не ломаете. Тосты и модалки цепляете через события и `FetchIt.Message`. В документации есть примеры под Bootstrap, Bulma, UIKit, Notyf, SweetAlert2.

## Возможности

- FormIt без обёрток: `&hooks`, `&validate`, `&emailTo` и остальные параметры уходят в FormIt как есть.
- Свой обработчик в `&snippet`: сниппет возвращает JSON с `success`, `message`, `data`.
- Ошибки полей: текст в элементах с `data-error="fieldName"`, CSS-классы для полей задаются системными настройками.
- Сообщения формы: после AJAX обновляются блоки `[data-success]` и `[data-validation-error]`.
- События: `fetchit:before` (можно отменить отправку и дописать `FormData`), `fetchit:after`, `fetchit:success`, `fetchit:error`, `fetchit:reset`.
- Уведомления: свой `FetchIt.Message` или встроенный [Notyf](https://carlosroso.com/notyf/) через `fetchit.frontend.default.notifier`.
- Несколько форм на странице: у каждой свой `data-fetchit` и свой экземпляр.
- После успеха поля можно очистить параметром `&clearFieldsOnSuccess` (по умолчанию включён).
- Fenom: вызов через pdoTools / `{'!FetchIt' | snippet}`.

Минимальный вызов:

```modx
[[!FetchIt?
  &snippet=`FormIt`
  &form=`myForm.tpl`
  &hooks=`email`
  &emailTo=`info@domain.com`
  &validate=`name:required,email:required`
  &successMessage=`Сообщение отправлено`
]]
```

## Ветки и версии

| Ветка | Пакет | MODX |
|-------|-------|------|
| `master` | 1.x | 2.x |
| `next` | 3.x | 3.x |

На [extras.modx.com](https://extras.modx.com/package/fetchit) сейчас **3.1.2-pl** (MODX 3) и **1.1.3-pl** (MODX 2). В ветке `next` идёт **3.1.4**.

## Документация

[Документация](https://docs.modx.pro/components/fetchit/): разметка, уведомления, модалки, клиентская валидация, JS API.

# Установка

Бесплатно через Менеджер пакетов:

- [modstore.pro](https://modstore.pro/packages/utilities/fetchit) ([как подключить репозиторий](https://modstore.pro/faq))
- [modx.com](https://modx.com/extras/package/fetchit)

Или соберите transport-пакет из `_build/` в этом репозитории.

---

Угостить автора: [cloudtips.ru](https://pay.cloudtips.ru/p/d4668b6e)
