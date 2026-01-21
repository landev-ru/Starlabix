<?php
declare(strict_types=1);
/**
 * ZtarLine config
 * Created by iFraso-dev
 * https://github.com/iFraso-dev/ZtarLine
 * Contacts: fraso1989@gmail.com / Fraso@mail.ru
 *
 * Рекомендуется не хранить учетные данные в коде.
 * Используйте переменные окружения или отдельный закрытый файл .env
 */
$user_login = getenv('STARLINE_LOGIN') ?: 'Your@email.com'; //Логин от личного кабинета https://my.starline.ru/site/login 
$user_pass_plain = getenv('STARLINE_PASS') ?: 'Your_password'; //Пароль от личного кабинета https://my.starline.ru/site/login

/**
 */
$user_pass_sha1 = sha1($user_pass_plain);
/**
 * 2) App credentials (developer.starline.ru)
 */
$user_AppId   = getenv('STARLINE_APP_ID') ?: 'AppId';
$user_Secret  = getenv('STARLINE_SECRET') ?: 'Your_generated_Secret';
$user_Secret_md5 = md5($user_Secret);
