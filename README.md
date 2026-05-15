# TripControl

TripControl ist eine persoenliche Reise-Uebersichts-App fuer Buchungen, Zahlungen, Aufgaben und Dokumente. Der Fokus liegt nicht auf Planung, sondern auf Klarheit: Was existiert, was ist gebucht, was ist bezahlt, was fehlt noch?

## 1. Installation

Voraussetzungen:

- PHP 8.3 oder neuer
- Composer
- SQLite-Erweiterung fuer PHP

Projekt vorbereiten:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan storage:link
```

TripControl nutzt Blade und Tailwind CSS ueber den offiziellen CDN-Modus fuer dieses schlanke MVP. Es ist deshalb kein Node/NPM-Build notwendig.

## 2. Migration + Seeding

```bash
php artisan migrate:fresh --seed
```

Der Seeder erstellt einen Demo-User und folgende Beispielreisen:

- Florida Coastertrip 2025
- Phantasialand September 2025
- Schwedenrundreise Sommer
- Norwegen Camperreise 2026
- Schweiz-Rundreise

## 3. Start der App

```bash
php artisan serve
```

Danach ist die App unter `http://127.0.0.1:8000` erreichbar.

## 4. Test-Login

```text
E-Mail: demo@tripcontrol.test
Passwort: password
```

## 5. Naechste moegliche Features

- Tagesaktuelle Waehrungskurse statt lokaler Fixkurse
- Filter und Suche im Dashboard
- Task-Erinnerungen per E-Mail
- Dokumentenvorschau fuer PDF und Bilder
- Export einer Reiseuebersicht als PDF
- Kalenderansicht fuer Deadlines und Reisedaten
- Mehrere Reisende oder Familienmitglieder pro Reise

## Waehrungskurse

Die App rechnet Buchungen mit festen Kursen in CHF um. Standardkurse liegen in `config/exchange.php`.

Fuer lokale oder serverseitige Anpassungen eine nicht versionierte Datei anlegen:

```bash
cp config/exchange.local.example.php config/exchange.local.php
```

Danach in `config/exchange.local.php` die Werte unter `rates_to_chf` anpassen. Beispiel: `EUR => 0.9150` bedeutet `1 EUR = 0.9150 CHF`.

Nach Aenderungen auf einem gecachten Server:

```bash
php artisan optimize:clear
php artisan config:cache
```
