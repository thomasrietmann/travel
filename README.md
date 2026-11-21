# TripControl

TripControl verwaltet Reisen, Buchungen, Zahlungen, Aufgaben, Dokumente und geteilte Reiseübersichten. Die Anwendung läuft mit Laravel 13 und Blade auf PHP 8.3 und MySQL. Auf Metanet wurden zuletzt PHP 8.3.36 für Web/FPM und 8.3.21 für SSH/CLI beobachtet. Technische Mindestversion ist PHP 8.3.21; PHP 8.4 ist nicht erforderlich.

Composer löst Abhängigkeiten mit `config.platform.php=8.3.21` auf. Ein erfolgreicher Kompatibilitätstest bescheinigt keine aktuellen Sicherheitskorrekturen des PHP-Binaries: Web, SSH und Cron sollen einen vom Hoster gepflegten PHP-8.3-Patchstand verwenden. `php -v` und `php -m` für den konkreten SSH-/Cron-Pfad separat von der Web-phpinfo prüfen. Es ist kein Wechsel der produktiven PHP-Webversion erforderlich.

**Bestehende Produktion aktualisieren:** ausschliesslich nach [DEPLOY_METANET.md](DEPLOY_METANET.md) vorgehen. Keine Neuinstallation über vorhandene Daten ausführen. [Umsetzungsstand und Prüfgrenzen](SECURITY_IMPLEMENTATION.md).

## Neue lokale Entwicklungsinstallation

Nur in einem neuen, getrennten Verzeichnis mit eigener Datenbank:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Die leere SQLite-Datei dient ausschliesslich der lokalen Entwicklung; alternativ eine eigene MySQL-Entwicklungsdatenbank konfigurieren. Für optionale lokale Beispieldaten `php artisan db:seed` verwenden. Der Demo-Seeder ist ausserhalb von `local`/`testing` gesperrt und darf niemals produktive Konten erzeugen. Die Demo-Konten verwenden ausschliesslich lokal `password`.

Dokumente liegen privat. Ein `storage:link` ist dafür nicht erforderlich. Vorhandene Fallback-Pfade werden beim Lesen weiterhin berücksichtigt.

## Tests und Assets

Die Staging-Abnahme wurde vom Betreiber am 24.10.2026 bestätigt. Für das bestehende Produktivsystem ist das [kontrollierte Upgrade-Skript](UPGRADE_METANET.md) vorbereitet: `php ops/metanet/upgrade.php` prüft standardmässig nur; Änderungen benötigen ausdrücklich `--apply` und die dokumentierten Schutzbedingungen.

Die [Metanet-Staging-Abnahme](STAGING_ABNAHME_METANET.md) enthält die vorbereitete Backup-/Restore-Probe, die SMTP-/IMAP-Abnahme und die Konfigurationsvorlage für `travel-at.aufbollen.ch`. `staging:inventory` und `staging:mail-check` laufen nur bei expliziter Freigabe in `APP_ENV=staging`; Staging-Mails dürfen ausschliesslich an `STAGING_MAIL_RECIPIENT` gehen. Auf Produktion bleiben die Staging-Kommandos gesperrt.

```bash
composer test
pnpm install --frozen-lockfile --ignore-scripts
pnpm run build
```

Die Tests verwenden standardmässig SQLite im Speicher und gefälschte HTTP-/Storage-Dienste. Es werden weder echte OpenAI-Aufrufe noch echte Mails gesendet. Für den separaten MySQL-Testlauf siehe Deploymentanleitung. Die Suite zerstört ihre Testdatenbank; sie verweigert Datenbanknamen ausser `:memory:` oder einem Namen mit `_test` am Ende und verweigert einen anderen Anwendungsmodus als `testing`.

Die CI-Matrix in `.github/workflows/tests.yml` prüft PHP **8.3.21 und 8.3.36**, jeweils mit MySQL 8.0 und SQLite, inklusive der tatsächlichen Composer-Plattformanforderungen. `ops/ci/build-php.sh` baut dafür die offiziellen PHP-Quellen mit festen SHA-256-Prüfsummen; eine abweichende Laufzeitversion oder fehlende Erweiterungen lassen den Job vor den Tests abbrechen. Die CI-Builds enthalten auch IMAP und mbregex, ohne echte Mailserver anzusprechen. Der [Oktober-Nachweis](outputs/release-upgrade-2026-10-24/README.md) mit 64 Tests / 459 Assertions dokumentiert den damaligen Release. Die MySQL-Sperr-/Upgrade-Prüfungen werden unter SQLite übersprungen. Der optionale Restore-Test benötigt zusätzlich MySQL-Clientwerkzeuge und eine separate leere Testdatenbank. Die gebauten Assets werden mit ausgeliefert; auf Metanet ist kein Node-Prozess nötig. Tailwind wird lokal gebaut, JavaScript von der eigenen Domain geladen.

**Lokaler Nachweis vom 21.11.2026:** Jeweils PHP **8.3.21 und 8.3.36** mit MySQL **8.0.46**: **78 Tests / 476 Assertions, keine übersprungenen Tests**, einschliesslich echter Dump-/Datei-Restore-Probe und wiederholtem Bestandsupgrade. Mit SQLite jeweils **78 Tests / 410 Assertions, zehn MySQL-Prüfungen übersprungen**. Composer-Plattformprüfung und PHP-Syntaxprüfung bestanden auf beiden Laufzeiten; Composer-Audit ohne bekannte Advisories. Isolierte macOS/ARM64-Builds aus offiziellen PHP-Quellen; keine Metanet-, SMTP-/IMAP- oder OpenAI-Verbindungen. IMAP und mbregex sind in diesen lokalen Builds nicht enthalten; Mailbox/HTTP werden in der Suite durch Testimplementierungen ersetzt. Zusätzlich ist der erfolgreiche GitHub-CI-Lauf für den jeweiligen Release-Commit erforderlich.

## Architektur

An LEG orientiert: kleine Controller, FormRequests, Policies, fachliche Dienste, lokale Assets und Cron statt dauerhaft laufender Worker. TripControl behält seine vorhandenen Tabellen, bcrypt-Passwörter und Freigaberegeln.

- `DocumentStorage` bündelt private Uploads, Prüfsummen, Transaktionskompensation, alte Lesepfade und vorgemerkte Bereinigung.
- `BookingData` vereinheitlicht Validierung und Normalisierung manueller und importierter Buchungen.
- `Trip::visibleTo()` bündelt die Sichtbarkeit eigener und geteilter Reisen.
- `EmailIdentity` serialisiert Registrierungen und Alias-Erstellung über beide Tabellen.
- `IncomingMailImporter` orchestriert `ImapMailbox`, `TrustedMailSender`, `MailContentReader` und `MailPdfRenderer`. Alle Aufrufwege teilen eine Prozesssperre.
- `UsesOpenAi` und `AiBudget` bündeln API-Verhalten und Verbrauchslimits. Sicherheitsheader und minimierte Aktionslogs liegen in Middleware.

## AI- und Mailimport

Ein gesetzter `OPENAI_API_KEY` aktiviert die bestehende AI-Auswertung. Der Webimport verarbeitet bis zu drei Dateien à 10 MiB. Ohne API-Schlüssel bleiben manuelle Buchungen verfügbar. Erfolgreiche Buchungen bleiben auch bei fehlgeschlagener Zusammenfassung erhalten.

Weitergeleitete Mails werden nur einem Benutzer mit bestätigter, eindeutiger Adresse und akzeptierter Absenderauthentifizierung zugeordnet. SMTP-Daten werden in `.env` hinterlegt; Bestätigungsmails werden unter Einstellungen angefordert. Bestehende Benutzer können sich auch ohne Bestätigung weiterhin anmelden.

Die Metanet-Header-Vertrauensgrenze muss vor Aktivierung geprüft werden. `MAIL_IMPORT_AUTH_RESULTS_TRUSTED` ist standardmässig `false`. Ohne diese Freigabe erfolgt kein Mailimport. Die konkrete Einrichtung und Tests stehen in der Deploymentanleitung.

```bash
php artisan mail:import --limit=1
php artisan mail:retry ID
```

Fehlerhafte Mails bleiben ungelesen, Wiederholungen sind begrenzt. Neue Importidentitäten enthalten Mailbox, UIDVALIDITY und UID. Bereits erfolgreiche alte Message-ID-Einträge bleiben bei der Duplikatprüfung berücksichtigt. Neue Mail-PDFs enthalten sicher escapeten Text; bereits gespeicherte Dokumente werden nicht neu gerendert.

## Betrieb

```bash
php artisan deployment:check --probe-storage
php artisan documents:migrate-private
php artisan documents:migrate-private --apply
php artisan documents:cleanup --limit=100
```

Die Dokumentmigration inventarisiert standardmässig; `--apply` kopiert nach Prüfsummenprüfung. **Sie löscht keine Quellen.** Öffentlich erreichbare Legacy-Dokumente müssen zusätzlich im Webserver gesperrt werden. Die Bereinigung löscht nur ausdrücklich vorgemerkte, nicht mehr referenzierte Dateien.

`FILESYSTEM_LOCAL_ROOT` bezeichnet die einzige Schreibablage. Private/public Fallback-Wurzeln dürfen als kommaseparierte Listen zum Lesen erhalten bleiben. Web und CLI müssen dieselbe echte private Ablage und denselben `PROCESS_LOCK_PATH` erreichen. Funktionierende Produktionspfade nicht durch Beispielpfade ersetzen.

`php artisan admin:create EMAIL` erstellt ausschliesslich ein neues Administratorkonto. `php artisan account:password EMAIL` ändert nach verdeckter Passwortabfrage ein bestehendes Konto ohne Datenverlust und widerruft dessen Datenbank-Sessions.

## Währungskurse

Die festen CHF-Umrechnungskurse liegen in `config/exchange.php`. Servereigene Anpassungen bleiben in der nicht versionierten Datei `config/exchange.local.php`; bei einer neuen lokalen Installation kann `config/exchange.local.example.php` als Vorlage dienen. `EUR => 0.9150` bedeutet `1 EUR = 0.9150 CHF`. Nach Änderungen gezielt `php artisan config:cache` im korrekten Ausführungskontext ausführen.
