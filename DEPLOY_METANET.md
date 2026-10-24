# TripControl: sicheres Update auf Metanet

Zielsystem laut Betreiber: **PHP 8.3.36, MySQL**, bestehende Benutzer, Reisen und Dokumente. Diese Anleitung ersetzt die frühere Anleitung zur Neuinstallation. Sie beschreibt ein Update des vorhandenen Projekts. Der Betreiber hat die Staging-Abnahme am **24.10.2026 bestätigt**; ein Produktionsdeployment wurde in dieser Arbeit nicht ausgeführt.

**Upgrade nach bestätigter Abnahme:** [Aufrufanleitung für das Bestandsupgrade](UPGRADE_METANET.md) und `ops/metanet/upgrade.php` ausführen, zunächst ohne `--apply`. Das Skript begrenzt Migrationen auf diesen Release und vergleicht Bestandsdaten/Dokumente vor und nach der Änderung. Die [Staging-Abnahme](STAGING_ABNAHME_METANET.md) für `travel-at.aufbollen.ch` bleibt als Ablauf dokumentiert; ihre Einzelbelege werden vom Betreiber verwahrt.

## Unveränderliche Bestandsdaten

- Vorhandene `.env`, `APP_KEY`, MySQL-Verbindung, Benutzer-IDs, Passwort-Hashes, Session-Konfiguration, Freigaben und `documents.file_path` erhalten. `HASH_DRIVER=bcrypt` beibehalten.
- Ausschliesslich `php artisan migrate --force` für neue Migrationen verwenden. **Niemals** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed` oder `key:generate` auf dem bestehenden System ausführen.
- `storage`, konfigurierte Fallback-Verzeichnisse, `.env` und `config/exchange.local.php` weder durch ein leeres Release ersetzen noch mit `rsync --delete`, `git clean` oder `git reset --hard` bereinigen.
- Keine Benutzer oder Aliase automatisch löschen oder als bestätigt markieren. Bestehende Countdown-Tokens bleiben gültig, bis ihr Besitzer sie erneuert oder deaktiviert.
- Tests niemals im Produktivverzeichnis oder gegen die Produktivdatenbank starten: Die Tests leeren ihre ausdrücklich separate Testdatenbank.

## 1. Vorbereiten und Abbruchkriterien

Eine verantwortliche Person begleitet das Wartungsfenster. In Plesk den tatsächlichen PHP-Pfad für **Web, SSH und geplante Aufgaben** prüfen. `/opt/php83/bin/php` ist unten ein Beispiel, das auf diesem Hosting zuerst mit `-v` bestätigt werden muss. Den Composer-2-Pfad ebenfalls ermitteln.

```bash
TC_PHP=/opt/php83/bin/php
TC_COMPOSER=/bin/composer2
"$TC_PHP" -v
"$TC_PHP" "$TC_COMPOSER" --version
"$TC_PHP" -m
```

Benötigt werden insbesondere PDO MySQL, mbstring, fileinfo, DOM/XML; für Mailimport zusätzlich IMAP. TLS-Verifikation muss funktionieren. Der Document Root muss auf `public` zeigen. `APP_URL` muss die tatsächlich verwendete HTTPS-Domain sein; andere Hosts werden abgelehnt. Domain-Aliase vorher in Plesk auf die kanonische Domain umleiten.

Vorher dokumentieren, ohne Geheimnisse ins Repository zu kopieren:

- Aktueller Git-Commit, lokale Änderungen, produktives `composer.lock` (falls vorhanden), tatsächlich installierte Pakete, PHP-Erweiterungen und MySQL-Version.
- MySQL-Datenbankname und Tabellen-Engines; die Anwendungstabellen müssen InnoDB verwenden. Eine Engine-Umstellung ist ein separates, getestetes Vorhaben.
- Alle tatsächlichen privaten und öffentlichen Dokumentwurzeln, Fallback-Pfade, `public/storage`-Verknüpfung, Cache-, Session-, Log- und View-Pfade. Web und CLI können unter Metanet verschiedene Chroot-Sichten haben. Identische Zeichenketten beweisen keine identische Ablage.
- Benutzer-, Reise-, Buchungs-, Aufgaben-, Dokument-, Alias-, Geburtstags- und Freigabeanzahlen. Zusätzlich Dokumentinventar mit Dateipfad und SHA-256 aus einer isolierten Kopie erstellen.
- Vorhandene Cronjobs, deren PHP-Version und den letzten erfolgreichen Import.

Das neue Lockfile wurde mangels eingechecktem altem Lockfile neu aufgelöst (Laravel 13.38.0, Dompdf 3.1.6). **Vor Freigabe mit den wirklich produktiven Versionen vergleichen.** Das ist kein Nachweis, dass Produktion bisher dieselben Versionen verwendet. Bei Versionskonflikten zuerst im Testsystem auflösen, niemals spontan `composer update` auf Produktion ausführen.

**Abbrechen**, wenn Backup/Restore nicht nachgewiesen ist, Dokumente fehlen oder Kopien verschiedene Prüfsummen haben, unbekannte lokale Änderungen bestehen, Storage-Pfade nicht in Web und CLI übereinstimmen, Migrationen ausserhalb dieser Änderung anstehen oder der MySQL-Testlauf scheitert.

## 2. Release isoliert testen

In einem separaten Verzeichnis mit eigener Test-`.env` und einer ausschliesslich dafür angelegten MySQL-Datenbank arbeiten. Bei einer Kopie produktiver Daten deren Zugriff beschränken; ausgehende Mails und echte AI-Aufrufe deaktivieren. Keine produktiven Storage-Pfade eintragen.

```bash
"$TC_PHP" "$TC_COMPOSER" install --prefer-dist --no-interaction
"$TC_PHP" "$TC_COMPOSER" validate --strict --no-check-publish
"$TC_PHP" "$TC_COMPOSER" audit --locked
"$TC_PHP" vendor/bin/phpunit
```

Der Standardtestlauf verwendet SQLite im Speicher. Für den **zusätzlich erforderlichen MySQL-Test** in dieser isolierten Umgebung:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=tripcontrol_test DB_USERNAME=tripcontrol_test_user \
  "$TC_PHP" vendor/bin/phpunit
```

Das Passwort ausschliesslich in der isolierten Test-`.env` oder sicherer CI-Konfiguration hinterlegen. Der Testbenutzer darf nur die Testdatenbank bearbeiten. `.github/workflows/tests.yml` führt die Suite mit PHP 8.3.36 sowie SQLite und MySQL 8.0 aus. Die tatsächliche Metanet-MySQL-Version zusätzlich im Staging prüfen; die CI-Datei allein ist noch kein erfolgreicher CI-Lauf.

**Lokaler Nachweis vom 24.10.2026:** PHP **8.3.36** mit MySQL **8.0.46**, InnoDB und striktem SQL-Modus: **45 Tests / 337 Assertions bestanden, keine übersprungenen Tests**. Auch der Upgrade-Test zum Erhalt bestehender Zeilen, Passwort-Hashes, Tokens und Dokumentdateien sowie die Sperrprüfung über zwei MySQL-Verbindungen bestanden. Laufzeit war macOS/ARM64 mit temporärer Testdatenbank; kein Zugriff auf Produktion, kein Deployment. [Testprotokolle und Grenzen](outputs/php83-mysql-tests-2026-10-24/README.md). Dieser Nachweis ersetzt weder Restore-/Chroot-Prüfungen noch die Tests der tatsächlichen Metanet-Datenbankversion und Maildienste.

Für reproduzierbare Assets auf einem Build-Rechner mit Node und der in `package.json` angegebenen pnpm-Version:

```bash
pnpm install --frozen-lockfile --ignore-scripts
pnpm run build
```

`public/build/app.css` und `public/assets/app.js` gehören zum Release. Node wird auf Metanet nicht benötigt. Tailwind 3 wurde zur Kompatibilität mit den bestehenden Klassen beibehalten; der Architekturansatz lokaler Assets folgt LEG.

Die Migration ausserdem auf einer separaten Kopie der bestehenden MySQL-Datenbank mit `migrate --pretend` und anschliessend `migrate --force` erproben. Dokumentkopien für diese Probe verwenden. Vorher/Nachher-Inventare und Login/Downloads vergleichen.

## 3. Konsistentes Backup im Wartungsfenster

Alle Mailimport- und Bereinigungs-Cronjobs in Plesk pausieren. Laufende Importe und Schreibanfragen auslaufen lassen. Danach im **alten** produktiven Release:

```bash
"$TC_PHP" artisan down --retry=60
```

Ein erfolgreicher Wartungsmodus beendet keine bereits laufenden Requests. Vor dem Backup nochmals sicherstellen, dass keine Schreibprozesse mehr laufen. Backups ausserhalb des Webroots mit restriktiven Rechten speichern:

```bash
umask 077
TC_BACKUP=/pfad/ausserhalb-webroot/tripcontrol-backup-YYYYMMDD-HHMM
mkdir -p "$TC_BACKUP"
git rev-parse HEAD > "$TC_BACKUP/previous-commit.txt"
"$TC_PHP" "$TC_COMPOSER" show --format=json > "$TC_BACKUP/installed-packages.json"
mysqldump --host=127.0.0.1 --user=BESTEHENDER_DB_USER --password \
  --single-transaction --quick --hex-blob --no-tablespaces \
  BESTEHENDE_DB > "$TC_BACKUP/database.sql"
```

`--password` fragt interaktiv; kein Passwort in Kommandozeile oder Shell-History schreiben. Exitcode und nicht leere SQL-Datei prüfen. Fehlende Rechte/Optionen mit Metanet klären, Fehler nicht ignorieren. Für nicht transaktionale Tabellen reicht `--single-transaction` nicht.

Das komplette alte Projekt inklusive `.env`, `vendor`, Lockfile, `storage`, `.git` und `config/exchange.local.php` sichern; Symlink-Ziele und **alle extern konfigurierten** Dokumentwurzeln separat archivieren. Beispiel für einen echten, nicht verlinkten Projektordner:

```bash
tar -czf "$TC_BACKUP/project.tar.gz" -C /pfad/zum/projekt .
```

Weitere Storage-Archive mit ihren tatsächlichen Pfaden erstellen. Archivauflistung und SHA-256 prüfen, Kopie sicher extern aufbewahren. Vor dem produktiven Update muss ein Restore in ein **separates** Verzeichnis und eine **separate** Datenbank erfolgreich getestet worden sein. Das tägliche Hoster-Backup ersetzt diese konsistente Sicherung nicht.

## 4. Code und Konfiguration aktualisieren

Für dieses erste Update den bestehenden Projekt-/Storage-Standort beibehalten. Ein späterer Wechsel auf Release-Verzeichnisse ist separat zu testen, besonders wegen Chroot und persistenten Pfaden.

Den vorab getesteten, eindeutig bezeichneten Release-Commit übernehmen. Nur bei sauberem Arbeitsverzeichnis wechseln. Falls das bisherige `composer.lock` nur ungetrackt auf dem Server liegt: erst extern sichern und mit dem neuen vergleichen; einen Checkout-Konflikt niemals durch Löschen ohne Sicherung umgehen. Keine fremden lokalen Änderungen überschreiben.

```bash
"$TC_PHP" "$TC_COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$TC_PHP" "$TC_COMPOSER" check-platform-reqs --no-dev
"$TC_PHP" artisan config:clear
```

Die vorhandene `.env` **ergänzen**, niemals mit `.env.example` ersetzen:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://BESTEHENDE-KANONISCHE-DOMAIN
SESSION_SECURE_COOKIE=true
HASH_DRIVER=bcrypt
CACHE_STORE=database
FILESYSTEM_DISK=local
AI_DAILY_LIMIT=60

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=SMTP-HOST-LAUT-METANET
MAIL_PORT=465
MAIL_USERNAME=BESTEHENDER-SMTP-BENUTZER
MAIL_PASSWORD="GEHEIM"
MAIL_FROM_ADDRESS=FREIGEGEBENE-ABSENDERADRESSE
MAIL_FROM_NAME=TripControl

MAIL_IMPORT_AUTH_RESULTS_TRUSTED=false
MAIL_IMPORT_AUTHSERV_IDS=
```

Port/Scheme nach tatsächlichem SMTP-Zugang wählen (z. B. `smtp` mit STARTTLS auf 587, wenn so eingerichtet); Zertifikatsprüfung niemals abschalten. SMTP-Zugangsdaten sind laut Betreiber vorhanden, bleiben aber ausschliesslich serverseitig.

`APP_KEY`, DB-Zugangsdaten, `SESSION_DRIVER`, `SESSION_COOKIE`, `SESSION_DOMAIN`, `SESSION_ENCRYPT` und existierende Pfad-Overrides erhalten. Neue Dateien werden **nur** in `FILESYSTEM_LOCAL_ROOT` geschrieben; alte Fallback-Wurzeln bleiben lesbar. Deshalb muss die kanonische private Ablage aus Web und Cron dieselben Bytes erreichen. `PROCESS_LOCK_PATH` muss für beide auf dasselbe permanente Verzeichnis ausserhalb des Webroots zeigen. Verzeichnis anlegen und nur dem Hosting-Benutzer Zugriff geben. Keine pauschalen `chmod 777` verwenden.

Leere optionale Pfadvariablen verwenden nun sinnvolle Standardwerte. Trotzdem bestehende funktionierende Overrides erhalten. MySQL in SSH benötigt bei Metanet häufig TCP über `127.0.0.1`; funktionierende produktive Werte nur nach Web-/CLI-Test ändern.

## 5. Migration und Dokumentprüfung

Für dieses Release bevorzugt den kontrollierten Ablauf in [UPGRADE_METANET.md](UPGRADE_METANET.md) verwenden. Die folgenden Einzelkommandos erklären die zugrunde liegenden Schritte; nicht zusätzlich unkontrolliert andere ausstehende Migrationen ausführen.

```bash
"$TC_PHP" artisan migrate:status
"$TC_PHP" artisan migrate --pretend
"$TC_PHP" artisan migrate --force
"$TC_PHP" artisan documents:migrate-private > "$TC_BACKUP/documents-before.txt"
```

Die zwei neuen Migrationen ergänzen nur eine Dateibereinigungs-Tabelle, eine Mutex-Zeile für Mailidentitäten, `user_email_aliases.verified_at` und `imported_mails.attempts`. MySQL-DDL ist nicht als Ganzes transaktional. Bei einer Teilmigration stoppen und den Schema-/Migrationsstand prüfen; nicht blind neu starten oder zurückrollen.

`documents:migrate-private` ist standardmässig eine Inventarisierung. Nur nach erfolgreichem Inventar, genügend Speicherplatz und während der Schreibpause:

```bash
"$TC_PHP" artisan documents:migrate-private --apply > "$TC_BACKUP/documents-copy.txt"
"$TC_PHP" artisan documents:migrate-private > "$TC_BACKUP/documents-after.txt"
```

Jeden Exitcode prüfen. Alle Quellen bleiben erhalten, Kopien werden per SHA-256 kontrolliert. Unterschiedliche private/öffentliche Kopien oder fehlende Dateien führen zum Fehler. Keine Variante wird automatisch als „richtige“ gewählt. Die Migration beseitigt öffentlich erreichbare Quellen **nicht**.

### Öffentliche Dokumentzugriffe schliessen

Die mitgelieferte `public/.htaccess` sperrt `/storage/documents/` für Apache. **Nginx kann statische Dateien direkt ausliefern und `.htaccess` umgehen.** In Plesk die entsprechende zusätzliche Nginx-Regel über den verfügbaren Konfigurationsweg oder Metanet einrichten:

```nginx
location ^~ /storage/documents/ { return 404; }
location = /storage/documents { return 404; }
```

Zusätzliche öffentliche Aliase/Docroots ebenfalls sperren. Ausgelagerte Dokumentordner dürfen nicht als eigene Domain oder statischer Alias erreichbar sein. Keine neue `storage:link`-Verknüpfung anlegen. Eine bestehende Verknüpfung erst entfernen, wenn ihre sonstige Verwendung geprüft wurde; Quelldateien dabei erhalten.

Mit einer **bekannten tatsächlich vorhandenen** alten Datei testen: anonyme direkte URL liefert 403/404 ohne Dateiinhalt; der autorisierte Laravel-Download liefert weiterhin exakt dieselbe Prüfsumme. Auch `.env`, `vendor`, Datenbank-Backups und Logs dürfen öffentlich nicht erreichbar sein. Reine Tests mit erfundenen URLs beweisen keine Sperre.

## 6. Webfreigabe und Smoke-Test

```bash
"$TC_PHP" artisan deployment:check --probe-storage
"$TC_PHP" artisan config:cache
"$TC_PHP" artisan route:cache
"$TC_PHP" artisan view:cache
```

Bei Chroot-Abweichungen Cache-Dateien in der für die Webausführung korrekten Umgebung erzeugen; insbesondere kompilierte View- und absolute Storage-Pfade prüfen. `optimize:clear` ist kein pauschaler Deploy-Schritt: ein globales Cache-Leeren setzt auch Limits zurück. Nach dem Wechsel OPcache/PHP-FPM über den vorgesehenen Plesk-Weg neu laden, falls erforderlich.

Vor Öffnung über einen zeitlich begrenzten Wartungs-Bypass oder eine abgeschirmte Staging-Domain testen. Bypass-Token wie ein Passwort behandeln und anschliessend entfernen.

1. Bestehendes normales Konto und Administratorkonto anmelden, vorhandene Reisen, Buchungen, Aufgaben, Geburtstage und Freigaben vergleichen.
2. Alte private sowie alte öffentliche/Fallback-Dokumente über die Anwendung herunterladen und Hash vergleichen; fremdes Konto erhält 403.
3. Als Mitbenutzer erlaubte Bearbeitung testen; Löschen der Reise und Freigabeverwaltung bleiben dem Eigentümer vorbehalten.
4. Ein ausdrücklich angelegtes Testdokument hochladen, ersetzen, herunterladen und löschen. Originalbestand nicht als Testobjekt verwenden.
5. Mobilmenü, Datumseingaben, Dateiimport-Auswahl, lokale Assets und Browser-Konsole auf CSP-Fehler prüfen.
6. Countdown-Bestandslink prüfen; mit einem Testkonto Rotation/Widerruf testen. Kein automatischer Tokenwechsel beim Update.
7. SMTP-Bestätigung an eigenes Testpostfach senden, angemeldet bestätigen, falsches Konto/abgelaufenen Link ablehnen.
8. Benutzer-/Datensatzanzahlen und Dokumentinventare mit dem Backup vergleichen (nur ausdrücklich erzeugte Testdaten dürfen abweichen).

Erst danach:

```bash
"$TC_PHP" artisan up
```

Bereinigung gezielt aktivieren, z. B. alle zehn Minuten `PHP83 /absoluter/projektpfad/artisan documents:cleanup --limit=100`. Sie löscht nur vorgemerkte und nicht mehr referenzierte Dateien, inklusive vorhandener Fallback-Kopien. Während der ersten Abnahme deaktiviert lassen. Historische unreferenzierte Dateien werden nicht pauschal gelöscht; sie benötigen ein separat geprüftes Inventar.

## 7. Mailimport separat freigeben

Vorhandene Benutzer behalten Webzugang auch ohne Adressbestätigung. Für den Import bestätigen sie ihre Hauptadresse und Aliase unter Einstellungen. Widersprüchliche historische Zuordnungen werden zurückgehalten und müssen mit den betroffenen Besitzern geklärt werden. Die Migration übernimmt keine ungeprüfte Alias-Bestätigung.

Der bisherige Import vertraute auch `Reply-To`/`Sender`. Jetzt ist nur eine einzelne `From`-Adresse mit bestätigtem Besitz und vertrauenswürdigem, passendem DMARC-Ergebnis zugelassen. `Authentication-Results` ist für sich genommen fälschbar. **Vor Aktivierung muss Metanet bestätigen/testen, dass eingehende gefälschte Header mit der eigenen authserv-id entfernt werden.** SMTP-Zugang allein genügt dafür nicht.

1. Exakte authserv-id und tatsächlich gesetztes Headerformat aus dem Empfangspostfach feststellen. Die momentane konservative Prüfung erwartet z. B. `Authentication-Results: mx.example.test; dmarc=pass header.from=example.com`; andere Prüfmethoden dürfen in getrennten Semikolon-Abschnitten stehen. Kommentare/abweichende DMARC-Formate werden zurückgewiesen und benötigen eine gezielte, getestete Parseranpassung.
2. Echte positive und manipulierte negative Beispielmails verwenden. Die Beispiele ausserhalb des Webroots aufbewahren und danach entfernen. Keine Kundenmails ins Repository kopieren.
3. Erst in der Testkonfiguration `MAIL_IMPORT_AUTHSERV_IDS=EXAKTE-ID` und `MAIL_IMPORT_AUTH_RESULTS_TRUSTED=true` setzen, Cache aktualisieren und `php artisan mail:check-sender /privater/pfad/beispiel.eml` ausführen. Eine manipulierte Nachricht mit gefälschtem Header muss abgelehnt werden. Der lokale Dateitest ersetzt den Test der vorgeschalteten Mailserverbereinigung nicht.
4. IMAP nur mit `/ssl`, ohne `/novalidate-cert`; Empfänger und Zugangsdaten beibehalten. Eigene weitergeleitete Testmail eines bestätigten Benutzers importieren, Eigentümer, Reisezuordnung und Dokument prüfen. Erneut importieren: keine Duplikate.
5. Erst nach bestandenem Test die Flags produktiv setzen und `deployment:check --mail` ausführen. Dann Plesk-Cron wieder aktivieren, zunächst mit `PHP83 /absoluter/projektpfad/cron-mail-import.php --limit=1`.

Ohne belegte Header-Vertrauensgrenze bleibt der Mailimport gesperrt. Die Webanwendung und vorhandene Daten sind davon unabhängig. Ungelesene Nachrichten werden weder gelöscht noch bei Verarbeitungsfehlern als gelesen markiert. Ist ein unterbrechungsfreier Mailimport für das Wartungsfenster zwingend, erfolgt die gesamte Freigabe erst nach diesem Test.

Maximal drei Versuche mit 15 Minuten Abstand; blockierte Mails bleiben zur Klärung erhalten. Nach Fehlerkorrektur `php artisan mail:retry ID`; bereits erfolgreich importierte Mails können damit nicht nochmals freigegeben werden. Alte, vom Vorgänger als gelesen markierte Fehlversuche müssen nach Prüfung manuell wieder ungelesen gesetzt werden. Keine bereits erfolgreichen Mails pauschal zurücksetzen. Postfachrückstand überwachen und blockierte Nachrichten nach Klärung in einen separaten Ordner verschieben; pro Lauf werden höchstens 1000 ungelesene Einträge gesichtet.

Mailanhänge: höchstens 5, je 10 MiB, Gesamtnachricht höchstens 25 MiB. Web-AI-Import: höchstens 3 Dateien je 10 MiB; normales Dokumentformular bleibt bei 10 Dateien je 10 MiB. PHP `upload_max_filesize`, `post_max_size` und Timeouts passend einstellen. AI läuft synchron; lange Importe vorzugsweise über CLI-Cron. Prozesssperren lösen sich bei Prozessende; begonnene, nicht abgeschlossene DB-Transaktionen rollen zurück.

## 8. Rückkehr zum vorherigen Release

Bei fehlgeschlagenen Checks bleibt Wartung aktiv, Cron pausiert und Bereinigung deaktiviert.

- Solange keine neuen Geschäftsdaten geschrieben wurden: vorherigen Code **und das gesicherte passende `vendor`** sowie vorherige Konfiguration wiederherstellen. `APP_KEY` erhalten. Die neuen additiven Tabellen/Spalten können bestehen bleiben; **kein** `migrate:rollback` in Produktion.
- Dokumentmigration kopiert nur; alte Quellen bleiben für den vorherigen Code verfügbar. Öffentliche URL-Sperren auch beim Code-Rollback erhalten.
- Ein Code-Rollback würde die alten Sicherheitsfehler wieder aktivieren. Daher alten Mailimport und offene Registrierung gesperrt halten bzw. Wartung beibehalten, bis die fehlerhafte Änderung gezielt repariert ist.
- Falls nach Freigabe bereits Benutzer geschrieben haben: keinen alten SQL-Dump über die aktuelle Datenbank importieren. Zuerst aktuellen Zustand zusätzlich sichern; neue Daten/Dokumente in einer separaten Umgebung abgleichen und kontrollierte Vorwärtskorrektur bevorzugen.
- Ein vollständiger Restore von SQL **und dazugehörigem Dokumentstand** ist nur nach bewusster Entscheidung über den Verlust aller späteren Änderungen zulässig. Zuerst in einer separaten Datenbank und separaten Ordnern wiederherstellen, dort prüfen, danach koordiniert umschalten. Keine Datenbank allein auf einen älteren Stand setzen.

Nach jeder Rückkehr Caches zum tatsächlich aktiven Code neu erzeugen, OPcache prüfen und dieselben Smoke-Tests durchführen. Erst dann Wartung beenden. Backups bis nach fachlicher Abnahme aufbewahren.

## Betrieb und Quellen

Tägliche Laravel-/Import-Logs rotieren nach 14 Tagen, minimierte Web-Aktionsprotokolle nach 90 Tagen. Keine Passwörter, Tokens, Mailtexte oder AI-Antwortkörper in Tickets/Logs kopieren. HTTP-Status im Aktionsprotokoll bezeichnet die Antwort, nicht automatisch eine erfolgreiche fachliche Änderung. Logs bleiben ausserhalb des Webroots; Ablage und Platzverbrauch überwachen.

Bekannte Demo-/Admin-Passwörter werden durch `deployment:check` gemeldet, aber nie automatisch geändert. Betroffene Konten durch den Betreiber prüfen und mit `php artisan account:password EXAKTE-LOGIN-ADRESSE` absichern; das interaktive Kommando erhält alle Benutzerdaten und widerruft die Datenbank-Sessions dieses Kontos. Bei einem anderen Session-Treiber bricht es unverändert ab und verlangt einen angepassten Widerrufsablauf. Benutzer nicht löschen. `admin:create` legt nur neue Konten an, überschreibt keine bestehenden und fragt Passwörter verdeckt ab.

Die Abläufe berücksichtigen die [Metanet-SSH-Dokumentation](https://www.metanet.ch/de/support/webhosting-e-mail/plesk/plesk-ssh-zugriff) (Chroot, TCP-MySQL, explizite PHP-Binaries), [Metanet Backup/Restore](https://www.metanet.ch/de/support/webhosting-e-mail/backup-restore) und die Vertrauensgrenze aus [RFC 8601, Abschnitte 5 und 7](https://datatracker.ietf.org/doc/html/rfc8601). AI-Dateien erhalten eine einstündige Ablaufzeit zusätzlich zum sofortigen Löschversuch; Responses verwenden `store=false` gemäß [Files-API](https://developers.openai.com/api/reference/resources/files/methods/create) und [Responses-API](https://developers.openai.com/api/reference/resources/responses/methods/create). Das ersetzt nicht die Prüfung der OpenAI-Projekt-/Aufbewahrungseinstellungen durch den Betreiber.
