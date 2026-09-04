<p align="center">
  <h1 align="center">Мониторинг сигнализации Starline</h1>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://thumb.cloud.mail.ru/weblink/thumb/xw1/W6iB/sgfcSLwR8">
    <img alt="ZtarLine" src="https://thumb.cloud.mail.ru/weblink/thumb/xw1/W6iB/sgfcSLwR8" width="600">
  </picture>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.2.5_or_later-blue" />
  <img src="https://img.shields.io/badge/Ubuntu_Server-18.04_or_later-blue" />
  <img src="https://img.shields.io/badge/Zabbix-6.x_+-blue" />
  <img src="https://img.shields.io/badge/Apache-2.4_or_later-blue" />
  <img src="https://img.shields.io/badge/MySQL-8.0.X-blue" />
</p>

<p align="center">
  <em><strong>Скрипт ZstarLine</strong> предназначен для интеграции автомобильной сигнализации Starline в систему мониторинга Zabbix – как для личного, так и для коммерческого использования.</em>
</p>

<p align="center">
  <small>Тестируется на <a href="https://releases.ubuntu.com/focal/">Ubuntu Server 22.04.6 LTS</a>, <a href="https://www.php.net/downloads.php#v8.2.12">PHP 8.2.12</a>, <a href="http://archive.apache.org/dist/httpd/">Apache 2.4.58</a>, <a href="https://downloads.mysql.com/archives/community/?tpl=files&os=22&version=8.2.12&osva=Ubuntu Linux 22.04 (x86, 64-bit)">MySQL 8.2.12</a>, <a href="https://store.starline.ru/catalog/avtosignalizatsii/starline_s96_bt_2can_4lin_2sim_gsm/">Starline s96v2</a></small>
</p>

---

<!-- МЕНЮ -->
<details>
  <summary><b>📋 МЕНЮ</b></summary>
  <ol>
    <li><a href="#системные-требования">Системные требования</a></li>
    <li><a href="#установка">Установка</a></li>
    <li><a href="#настройка">Настройка</a>
      <ul>
        <li><a href="#appid-и-secret-starline">AppId и Secret Starline</a></li>
        <li><a href="#настройка-zabbix">Настройка Zabbix</a></li>
      </ul>
    </li>
  </ol>
</details>

---

## Системные требования

Скрипт работает на системе мониторинга **Zabbix версии 6 и выше**.  
Ознакомьтесь с официальными требованиями к оборудованию:  
➡️ [Требования Zabbix](https://www.zabbix.com/documentation/6.0/ru/manual/installation/requirements#примеры-конфигураций-оборудования)

<p align="right">(<a href="#readme-top">Вверх</a>)</p>

---

## Установка

> Руководство для Ubuntu / Debian.

1. **Скачайте скрипт** в домашнюю директорию:
   ```bash
   sudo git clone https://github.com/iFraso-dev/ZtarLine.git
