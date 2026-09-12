<?php

$_lang['area_fetchit_main'] = 'Основные настройки';

$_lang['setting_fetchit.frontend.js'] = 'Файл с JavaScript для подключения на фронтенде.';
$_lang['setting_fetchit.frontend.js.classname'] = 'Название JavaScript класса, чей экземпляр будет отвечать за обработку форм. По умолчанию "FetchIt".';
$_lang['setting_fetchit.frontend.input.invalid.class'] = 'CSS класс который будет добавлен элементу не прошедшему валидацию.';
$_lang['setting_fetchit.frontend.custom.invalid.class'] = 'CSS класс который будет добавлен кастомному элементу по ключу не прошедшему валидацию.';
$_lang['setting_fetchit.frontend.default.notifier'] = 'Подключить дефолтную библиотеку уведомлений.';
$_lang['setting_fetchit.frontend.default.notifier_desc'] = 'Если выбрать "Да", то при взаимодействии с вашими формами пользователю будут отображаться уведомления с помощью библиотеки <a href="https://carlosroso.com/notyf/">Notyf</a>.';
$_lang['setting_fetchit.protect.enabled'] = 'Защита коннектора submit-токеном.';
$_lang['setting_fetchit.protect.enabled_desc'] = 'Если "Да", AJAX-запросы к action.php требуют одноразовый токен из конфига формы (заголовок X-FetchIt-Token). Защищает от повторной отправки с сохранённой сессией. Плагины могут дополнительно подписаться на событие OnFetchItBeforeProcess.';
