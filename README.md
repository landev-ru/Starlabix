<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/logo.png" alt="Starlabix — StarLine Monitoring for Zabbix" width="480"/>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/php-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/Zabbix-7.4-red?style=for-the-badge&logo=zabbix&logoColor=white" alt="Zabbix"/>
  <img src="https://img.shields.io/badge/StarLine-API-1E88E5?style=for-the-badge" alt="StarLine"/>
  <img src="https://img.shields.io/badge/license-MIT-yellow?style=for-the-badge" alt="License"/>
</p>

<p align="center">
  <b>Мониторинг автомобильной сигнализации StarLine в Zabbix через официальный API.</b><br/>
  Скрипт собирает состояние устройства, уровень сигнала, напряжение АКБ, температуру,<br/>
  данные OBD-II и передаёт их в Zabbix через External Check.
</p>

---

## 📖 Содержание

- [О проекте](#-о-проекте)
- [Возможности](#-возможности)
- [Требования](#-требования)
- [Получение доступа к StarLine API](#-получение-доступа-к-starline-api)
- [Установка](#-установка)
- [Настройка](#-настройка)
- [Использование](#-использование)
- [Шаблон Zabbix](#-шаблон-zabbix)
- [Структура проекта](#-структура-проекта)
- [Важные замечания](#️-важные-замечания)
- [FAQ](#-faq)
- [Лицензия](#-лицензия)

---

## 🎯 О проекте

**Starlabix** — это PHP-скрипт для интеграции данных автомобильной сигнализации **StarLine** в систему мониторинга **Zabbix**.

Скрипт подключается к официальному API StarLine, проходит двухэтапную авторизацию, собирает телеметрию с устройства и отдаёт её в формате JSON. Zabbix забирает данные через **External Check** и раскладывает их на отдельные items через **Dependent items** и **JSONPATH**-препроцессинг.

> 🚗 **Один хост Zabbix = один автомобиль** — настраивается через отдельный файл `user_data.php`.

---

## ✨ Возможности

| Возможность | Описание |
|---|:---:|
| Двухэтапная авторизация StarLine API | ✅ |
| Поддержка нескольких устройств на аккаунт | ✅ |
| Полный набор телеметрии | ✅ |
| Данные OBD-II (пробег, ошибки) | ✅ |
| Координаты GPS (широта/долгота) | ✅ |
| Инвентаризация Zabbix (location_lat/lon) | ✅ |
| Готовый шаблон Zabbix 7.4 | ✅ |
| Работа из командной строки (CLI) | ✅ |
| Обработка ошибок API | ✅ |

### 📊 Что собирается

- 🚘 **Устройство** — alias, device_id, sn, imei, phone, fw_version
- 🛰️ **Сигналы** — уровень GPS (шт.), уровень GSM (%), статус (online/offline)
- 🔋 **Питание** — напряжение АКБ (V), баланс SIM-карты (₽)
- 🌡️ **Температура** — салон (°C), двигатель (°C)
- 🔧 **OBD-II** — пробег (км), количество ошибок, can_descr, can_version
- 📍 **Позиция** — широта, долгота (автоматически в инвентарь Zabbix)

---

## 📦 Требования

- **PHP** `7.4+` с включёнными расширениями:
  - `json`
  - `iconv` (опционально, для обработки кодировок)
  - `allow_url_fopen = On` (обязательно)
- **Zabbix Server** `6.0+` (шаблон протестирован на 7.4)
- **Zabbix Agent** для External Check (или скрипт на самом сервере Zabbix)
- Учётная запись на [my.starline.ru](https://my.starline.ru/site/login)
- Зарегистрированное приложение в разделе **«Разработчикам»** — `AppId` и `Secret`
  (см. [Получение доступа к StarLine API](#-получение-доступа-к-starline-api))

---

## 🔑 Получение доступа к StarLine API

Прежде чем приступить к установке, необходимо получить **AppId** и **Secret** в личном кабинете StarLine. Без них скрипт не сможет авторизоваться в API.

> ℹ️ **Что такое AppId и Secret?**
> Это идентификатор и секретный ключ вашего приложения. Открытое StarLine API позволяет владельцам охранных комплексов создавать приложения для управления своими автомобилями. **AppId** и **Secret** дают доступ исключительно к вашей машине.

---

### Шаг 1. Войдите в личный кабинет

Откройте [my.starline.ru](https://my.starline.ru) и авторизуйтесь под своей учётной записью.

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/step1-login.png" alt="Вход в my.starline.ru" width="720"/>
</p>

---

### Шаг 2. Перейдите в раздел «Разработчикам»

В верхнем меню личного кабинета найдите и откройте раздел **«Разработчикам»**.

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/step2-developers.png" alt="Раздел Разработчикам" width="720"/>
</p>

---

### Шаг 3. Нажмите «Создать приложение»

В открывшемся разделе нажмите кнопку **«Создать приложение»**.

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/step3-create-app.png" alt="Создать приложение" width="720"/>
</p>

---

### Шаг 4. Заполните форму «Новое приложение»

В окне **«Новое приложение»** укажите:

| Поле | Что указать |
|---|---|
| **Имя приложения** | Например, `Starlabix` или `Zabbix Monitoring` |
| **Описание приложения** | Например, `Мониторинг сигнализации в Zabbix` |
| **User IP** | IP-адрес вашего сервера Zabbix (обязательно!) |

Нажмите **«СОХРАНИТЬ»**.

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/step4-new-app.png" alt="Новое приложение" width="720"/>
</p>

---

### Шаг 5. Скопируйте AppId и Secret

После сохранения в разделе **«Разработчикам»** появится созданное приложение с парой **AppId** и **Secret**.

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/gh-pages/step5-app-credentials.png" alt="AppId и Secret" width="720"/>
</p>

Скопируйте оба значения — они понадобятся при настройке файла `user_data.php`.

---

### Шаг 6. Внесите значения в `user_data.php`

```php
<?php
$user_login = 'user@example.com';   // Логин от my.starline.ru
$user_pass  = sha1('ваш_пароль');   // Пароль, обёрнутый в sha1()

$user_AppId       = '1234';                                     // Ваш AppId
$user_Secret      = 'mvNWuRJadRzHlcIkIjadR9o0Tnf0iMzsCsd';      // Ваш Secret
$user_Secret_md5  = md5('mvNWuRJadRzHlcIkIjadR9o0Tnf0iMzsCsd'); // md5 от Secret
?>
```

### 🧪 Проверка

После заполнения `user_data.php` запустите скрипт вручную:

```bash
php starlabix.php | jq
```

Если всё настроено корректно — получите JSON с полями `user_info`, `mobile_devices`, `obd_params`, `obd_errors`.

Если получена ошибка — смотрите раздел [FAQ](#-faq).

---

## 🚀 Установка

### 1. Клонируем репозиторий

```bash
cd /etc/zabbix/externalscripts/
git clone https://github.com/landev-ru/Starlabix.git
cd Starlabix
```

### 2. Проверяем права

```bash
# Скрипт должен быть исполняемым для пользователя zabbix
chmod +x starlabix.php
chown zabbix:zabbix starlabix.php
```

### 3. Проверяем PHP CLI

```bash
php -v
# Должна быть версия 7.4 или выше
```

### 4. Проверяем `allow_url_fopen`

```bash
php -i | grep allow_url_fopen
# Должно быть: allow_url_fopen => On => On
```

Если выключено — включите в `php.ini`:

```ini
allow_url_fopen = On
```

### 5. Размещаем скрипт в ExternalScripts

В `zabbix_server.conf` убедитесь, что путь указан корректно:

```ini
ExternalScripts=/etc/zabbix/externalscripts
```

Перезапустите Zabbix Server:

```bash
sudo systemctl restart zabbix-server
```

---

## ⚙️ Настройка

### 1. Открываем `user_data.php`

```bash
nano user_data.php
```

### 2. Заполняем поля

```php
<?php
// Логин и пароль от my.starline.ru
$user_login = 'user@example.com';
$user_pass  = sha1('ваш_пароль');

// AppId и Secret из developer.starline.ru
$user_AppId       = '1234';
$user_Secret      = 'mvNWuRJadRzHlcIkIjadR9o0Tnf0iMzsCsd';
$user_Secret_md5  = md5('mvNWuRJadRzHlcIkIjadR9o0Tnf0iMzsCsd');
?>
```

### 3. Проверяем работу вручную

```bash
php starlabix.php | jq
```

Ожидаемый ответ — JSON с полями `user_info`, `mobile_devices`, `obd_params`, `obd_errors`.

Пример:

```json
{
  "user_info": {
    "devices": [
      {
        "alias": "Моя машина",
        "device_id": "1234567",
        "battery": 12.6,
        "ctemp": 22,
        "etemp": 88,
        "gsm_lvl": 24,
        "gps_lvl": 7,
        "status": 1,
        "balance": { "active": { "value": 150.50 } },
        "position": { "x": "44.078273", "y": "43.087036" }
      }
    ]
  },
  "obd_params": { "obd_params": { "mileage": { "val": 145000 }, "errors": { "val": 0 } } }
}
```

> ⚠️ Если в ответе `{"error": "..."}` — смотрите раздел [FAQ](#-faq).

---

## 📘 Использование

### Ручной запуск

```bash
php /etc/zabbix/externalscripts/Starlabix/starlabix.php
```

### Из Zabbix

Item с типом **External check**:

| Параметр | Значение |
|---|---|
| Name | `Starlabix` |
| Type | `External check` |
| Key | `starlabix.php` |
| Type of information | `Text` |
| Update interval | `5m` |
| Timeout | `20s` |

Все остальные items — **Dependent** от этого master item и используют **JSONPATH** для извлечения нужного поля.

---

## 📋 Шаблон Zabbix

Готовый шаблон лежит в файле **`zbx_export_hosts.yaml`** (Zabbix 7.4).

### Импорт

1. Zabbix → **Data collection** → **Hosts** → **Import**
2. Выберите `zbx_export_hosts.yaml`
3. Нажмите **Import**

### Состав шаблона

| Item | Тип | JSONPATH | Units |
|---|---|---|---|
| Имя устройства | DEPENDENT | `$.user_info.devices[0].alias` | — |
| Баланс счета SIM-карты | DEPENDENT | `$.user_info.devices[0].balance.active.value` | ₽ |
| Напряжение АКБ | DEPENDENT | `$.user_info.devices[0].battery` | V |
| Температура салона | DEPENDENT | `$.user_info.devices[0].ctemp` | C° |
| Температура двигателя | DEPENDENT | `$.user_info.devices[0].etemp` | C° |
| Уровень GPS | DEPENDENT | `$.user_info.devices[0].gps_lvl` | шт. |
| Уровень GSM | DEPENDENT | `$.user_info.devices[0].gsm_lvl` + JS-нормализация | % |
| Статус соединения | DEPENDENT | `$.user_info.devices[0].status` | valuemap |
| Пробег | DEPENDENT | `$.obd_params.obd_params.mileage.val` | — |
| Ошибки OBD | DEPENDENT | `$.obd_params.obd_params.errors.val` | — |
| IMEI / SN / phone / fw_version | DEPENDENT | `$.user_info.devices[0].*` | — |
| Координаты (широта/долгота) | DEPENDENT | `$.user_info.devices[0].position.*` | inventory |

> 📍 Широта и долгота автоматически попадают в **Inventory** хоста (`LOCATION_LAT`, `LOCATION_LON`) — можно смотреть на карте Zabbix.

---

## 📁 Структура проекта

```text
Starlabix/
├── starlabix.php                # Основной скрипт (External Check)
├── user_data.php                # Учётные данные (заполнить самостоятельно)
├── user_data.php.example        # Образец для публичного использования
├── zbx_export_hosts.yaml        # Шаблон Zabbix 7.4
├── logo.png                     # Логотип проекта
├── index.html                   # Страница проекта (GitHub Pages)
├── docs/                        # Скриншоты для документации
│   ├── step1-login.png
│   ├── step2-developers.png
│   ├── step3-create-app.png
│   ├── step4-new-app.png
│   └── step5-app-credentials.png
├── LICENSE                      # MIT License
├── .gitignore                   # Исключения (user_data.php и др.)
└── README.md
```

---

## ⚠️ Важные замечания

> 🔴 **Безопасность**
> - Файл `user_data.php` содержит пароль и Secret — **не коммитьте его в публичный репозиторий**.
> - Добавьте `user_data.php` в `.gitignore` и используйте `user_data.php.example` как образец.
> - Ограничьте права: `chmod 600 user_data.php`.

> 🟠 **Привязка к IP**
> - Приложение StarLine привязывается к IP-адресу сервера Zabbix. Если у вас динамический IP или NAT — настройте постоянный исходящий IP.

> 🟡 **Лимиты API**
> - StarLine API имеет лимиты. Интервал опроса `5m` — оптимально. Не ставьте `1m` — рискуете получить блокировку.

> 🔵 **Токен**
> - Токен приложения обновляется при каждом запросе. Если получаете `401` — проверьте `AppId`, `Secret`, `User IP`.

> 🟣 **Кодировка**
> - Если в `alias` или `can_descr` кракозябры — включите расширение `iconv` в PHP.

> ⚫ **Проверка синтаксиса**
> - Формат JSON может отличаться в зависимости от прошивки устройства и версии API. **Перед массовым применением протестируйте на одном устройстве.**

---

## ❓ FAQ

<details>
<summary><b>Почему скрипт возвращает ошибку "Ошибка получения кода приложения"?</b></summary>

Проверьте:
1. `AppId` и `Secret` корректны и активны на developer.starline.ru.
2. `$user_Secret_md5` действительно `md5($user_Secret)`.
3. Сервер Zabbix имеет доступ в интернет.
4. `allow_url_fopen = On` в PHP.

```bash
php -r "var_dump(allow_url_fopen());"
```
</details>

<details>
<summary><b>Скрипт возвращает {"error": "Не удалось получить slnet_token"}</b></summary>

Причина — неверный логин или пароль. Убедитесь, что:
- `$user_login` — точный email, указанный при регистрации на my.starline.ru
- `$user_pass = sha1('ваш_пароль')` — не забудьте про функцию `sha1`
- В аккаунте нет запроса подтверждения входа по SMS (зайдите вручную на my.starline.ru)
</details>

<details>
<summary><b>Как добавить второе устройство (второй автомобиль)?</b></summary>

Проще всего — создать второй хост Zabbix и развернуть вторую копию скрипта с отдельным `user_data.php`. Если у вас несколько устройств на **одном** аккаунте — используйте JSONPATH с другим индексом:

```
$.user_info.devices[1].alias
```

Но лучше сделать вторую копию каталога и в каждой указать свои credentials.
</details>

<details>
<summary><b>Можно ли запускать не на сервере Zabbix, а на отдельном хосте?</b></summary>

Да. Установите Zabbix Agent на отдельный хост, положите скрипт в `ExternalScripts` и настройте UserParameter:

```
UserParameter=starlabix.php,php /etc/zabbix/externalscripts/Starlabix/starlabix.php
```

На сервере Zabbix сделайте item с типом **Zabbix agent** и ключом `starlabix.php`.
</details>

<details>
<summary><b>JSONPATH не находит поле — что делать?</b></summary>

Запустите скрипт вручную и посмотрите точную структуру JSON:

```bash
php starlabix.php | jq .user_info.devices[0]
```

Некоторые прошивки кладут данные в другой узел (`obd_params` vs `obd_errors`). Скорректируйте JSONPATH в шаблоне.
</details>

<details>
<summary><b>Логотип не отображается в README.md</b></summary>

Используйте **абсолютный URL** через `raw.githubusercontent.com`:

```html
<img src="https://raw.githubusercontent.com/landev-ru/Starlabix/main/logo.png" alt="Starlabix" width="480"/>
```

Относительный путь `logo.png` в README на GitHub может не резолвиться в некоторых контекстах.
</details>

---

## 📄 Лицензия

Проект распространяется под лицензией **MIT**.

Скрипт предоставляется **«как есть»**. Автор не несёт ответственности за возможные сбои. Всегда тестируйте на тестовом устройстве перед применением в production.

---

<p align="center">
  <img src="https://raw.githubusercontent.com/landev-ru/Starlabix/main/logo.png" alt="Starlabix" width="200"/><br/>
  <i>Made with ❤️ for Zabbix engineers</i>
</p>
