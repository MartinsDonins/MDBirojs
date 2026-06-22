# "Auto" sadaļa — transporta uzskaite, izmaksas un atgādinājumi

Filament admin paneļa sadaļa transportlīdzekļu uzskaitei: nobraukums, degvielas
un gāzes uzpildes ar patēriņa aprēķinu, apkopes/remonti ar budžetu, apkopju
plāns ar atgādinājumiem, kā arī izmaksu un patēriņa grafiki. Atgādinājumi tiek
nosūtīti pa e-pastu un Telegram.

- **Stack:** Laravel 12, PHP 8.3+, Filament 3.3, PostgreSQL
- **Navigācijas grupa:** `Auto`
- **Valoda:** UI un dokumentācija latviski

---

## Saturs

1. [Apakšsadaļas](#apakšsadaļas)
2. [Aprēķini](#aprēķini)
3. [Grafiki](#grafiki)
4. [Atgādinājumi (e-pasts + Telegram)](#atgādinājumi)
5. [Konfigurācija un iestatīšana](#konfigurācija)
6. [Datu modelis](#datu-modelis)
7. [Faili](#faili)
8. [Testi](#testi)

---

## Apakšsadaļas

### 1. Transportlīdzekļi (`vehicles`)
Auto reģistrs (atbalsta vairākus auto). Lauki: nosaukums, marka, modelis, gads,
reģ. numurs, VIN, krāsa; degvielas veids un gāzes (LPG) aprīkojums ar tvertnes/
balona tilpumu; sākuma odometrs; **OCTA / KASKO / tehniskās apskates** derīguma
termiņi; aktivitātes karogs un piezīmes.

Sarakstā redzams pašreizējais nobraukums, vidējais patēriņš (benzīns/dīzelis/
gāze), nesamaksātā summa un tehniskās apskates termiņš. Augšā — kopsavilkuma
logrīks, apakšā — izmaksu un patēriņa grafiki.

### 2. Uzpildes (`fuel_logs`)
Degvielas un gāzes uzpildes. Lauki: auto, datums, **odometrs**, veids
(benzīns/dīzelis/gāze), litri, cena/L, kopā €, pilna tvertne, DUS/vieta,
piezīmes. Ievadot litrus un cenu, kopsumma tiek aprēķināta automātiski.
Sarakstā kolonna **Patēriņš** rāda L/100km posmam un kopsumma summē izmaksas.

### 3. Apkopes un remonti (`maintenance_logs` + `maintenance_items`)
Tehniskie darbi, apkopes un remonti ar **budžeta uzskaiti**. Lauki: auto, datums,
odometrs, tips (apkope/remonts/tehniskā apskate/riepu maiņa/cits), nosaukums,
apraksts, serviss; **darbu pozīcijas** (atsevišķi darbi/detaļas ar izmaksām);
**foto/dokumentu pielikumi**; budžeta bloks — kopējā summa, samaksāts un
aprēķinātais **atlikums** ar apmaksas statusu (apmaksāts/daļēji/nesamaksāts).
Ātrā darbība "Apmaksāts" iestata samaksāto vienādu ar kopsummu.

### 4. Apkopju plāns (`maintenance_plans`)
Plānotās/atkārtojošās apkopes ar atgādinājumiem. Lauki: auto, nosaukums,
apraksts, intervāls **pēc km un/vai pēc mēnešiem**, pēdējoreiz veikts
(odometrs + datums). Aprēķina nākamo termiņu (km/datums) un krāsainu statusu
(nokavēts/drīz/kārtībā). Darbība "Veikta tagad" atjauno pēdējo izpildi uz šodienu
un pašreizējo nobraukumu.

---

## Aprēķini

Visa loģika dzīvo modeļos (`app/Models`), tāpēc to izmanto gan tabulas, gan
grafiki, gan atgādinājumi.

**Pašreizējais nobraukums** (`Vehicle::current_odometer`) — lielākais zināmais
odometrs no uzpildēm, apkopēm un sākuma odometra.

**Vidējais patēriņš** (`Vehicle::averageConsumption($fuelType)`) — aprēķina
**atsevišķi katram degvielas veidam** no pilnās tvertnes uzpildēm:

```
L/100km = (litri starp pirmo un pēdējo pilno uzpildi) / (nobraukums) × 100
```

Pirmā uzpilde kalpo tikai par atskaites punktu (tās litri netiek skaitīti).
Posma patēriņš atsevišķai uzpildei — `FuelLog::consumption`.

**Atlikums** (`MaintenanceLog::outstanding`) = `total_cost − amount_paid` (≥ 0).
Apmaksas statuss (`payment_status`): `paid` / `partial` / `unpaid`.
Auto kopējais atlikums — `Vehicle::outstanding_amount`.

**Apkopes termiņš** (`MaintenancePlan::due_status`) — sliktākais no km un laika
kritērijiem: `overdue` (nokavēts), `soon` (≤ 1000 km vai ≤ 30 dienām), `ok`.
Nākamais termiņš: `next_due_odometer`, `next_due_date`; atlikums: `km_remaining`,
`days_remaining`.

---

## Grafiki

Redzami Transportlīdzekļu saraksta lapas apakšā (un galvenajā panelī).

- **VehicleCostChart** — izmaksas pa mēnešiem (degviela/gāze vs apkopes/remonti),
  pēdējie 12 mēneši, ar filtru pēc auto (vai visi).
- **VehicleConsumptionChart** — vidējais patēriņš L/100km pa mēnešiem, atsevišķas
  līnijas benzīnam, dīzelim un gāzei, ar filtru pēc auto.

---

## Atgādinājumi

Komanda `auto:reminders` apkopo un nosūta atgādinājumus par:
- apkopēm, kas **nokavētas vai drīz pienākas** (apkopju plāns),
- **OCTA / KASKO / tehniskās apskates** termiņiem, kas drīz beidzas vai jau
  beigušies.

Ziņa tiek nosūtīta **pa e-pastu** un **Telegram** (katrs kanāls atsevišķi —
nekonfigurēts kanāls tiek klusi izlaists). Ja nav neviena atgādinājuma, ziņa
netiek sūtīta.

```bash
# Pārbaude bez sūtīšanas (izdrukā ziņu):
php artisan auto:reminders --dry-run

# Reāla sūtīšana:
php artisan auto:reminders
```

**Plānošana:** komanda jau ieplānota reizi dienā plkst. 08:00
(`routes/console.php`). Produkcijā darbojas `scheduler` konteiners
(`php artisan schedule:work`, skat. `docker-compose.yml`).

### Telegram bota iestatīšana
1. Izveido botu pie [@BotFather](https://t.me/BotFather) → iegūsti **bot token**.
2. Uzraksti botam ziņu (vai pievieno grupai), tad iegūsti **chat_id**
   (piem. caur [@userinfobot](https://t.me/userinfobot) vai
   `https://api.telegram.org/bot<token>/getUpdates`).
3. Ieliec `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_CHAT_ID=987654321
   ```

---

## Konfigurācija

`.env` (skat. arī `.env.example`):

| Mainīgais | Noklusējums | Apraksts |
|---|---|---|
| `AUTO_REMINDERS_ENABLED` | `true` | Ieslēgt/izslēgt atgādinājumus |
| `AUTO_REMINDER_EMAIL` | _(tukšs)_ | Saņēmēja e-pasts; tukšs = `MAIL_FROM_ADDRESS` |
| `AUTO_REMINDER_SOON_DAYS` | `30` | Cik dienas iepriekš brīdināt par termiņiem |
| `AUTO_REMINDER_SOON_KM` | `1000` | Km slieksnis "drīz" statusam |
| `TELEGRAM_BOT_TOKEN` | _(tukšs)_ | Telegram bota žetons |
| `TELEGRAM_CHAT_ID` | _(tukšs)_ | Telegram chat_id |

Atbilstošā konfigurācija: `config/services.php` (`telegram`, `auto`).

---

## Datu modelis

| Tabula | Apraksts |
|---|---|
| `vehicles` | Transportlīdzekļi + dokumentu termiņi |
| `fuel_logs` | Degvielas/gāzes uzpildes |
| `maintenance_logs` | Apkopes/remonti + budžets |
| `maintenance_items` | Apkopes darbu pozīcijas (1:N pret `maintenance_logs`) |
| `maintenance_plans` | Apkopju plāns ar intervāliem |

Visas `*_logs`/`*_plans`/`*_items` tabulas tiek dzēstas kaskadēti, dzēšot auto.

---

## Faili

```
app/Models/Vehicle.php, FuelLog.php, MaintenanceLog.php,
          MaintenanceItem.php, MaintenancePlan.php
app/Filament/Resources/VehicleResource.php (+ Pages/)
app/Filament/Resources/FuelLogResource.php (+ Pages/)
app/Filament/Resources/MaintenanceLogResource.php (+ Pages/)
app/Filament/Resources/MaintenancePlanResource.php (+ Pages/)
app/Filament/Widgets/VehicleStatsWidget.php
app/Filament/Widgets/VehicleCostChart.php
app/Filament/Widgets/VehicleConsumptionChart.php
app/Services/TelegramService.php
app/Console/Commands/SendAutoReminders.php
routes/console.php                      (dienas plānojums)
config/services.php                     (telegram, auto)
database/migrations/2026_06_22_0000010..05_*.php
tests/Feature/VehicleSectionTest.php
```

---

## Testi

```bash
php artisan test --filter=VehicleSectionTest
```

Sedz lapu renderēšanu, grafiku renderēšanu, patēriņa/atlikuma/termiņa aprēķinus,
atgādinājumu komandu un Telegram servisu.

> **Piezīme par testiem:** projekta migrācijās ir PostgreSQL specifisks DDL, kas
> nav saderīgs ar sqlite `:memory:`. Tāpēc `VehicleSectionTest` izmanto reālo
> pgsql datubāzi (skat. `.env`) un katru testu ietin transakcijā ar atriti.
