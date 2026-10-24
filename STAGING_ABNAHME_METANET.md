# TripControl – Staging-Abnahme auf Metanet

Stand: 24.10.2026. **Staging-Abnahme vom Betreiber am 24.10.2026 bestätigt. Kein Produktionsdeployment durch diesen Arbeitslauf.** Die konkreten Metanet-Einzelbelege liegen beim Betreiber; lokale Regression und lokale MySQL-Dump-/Datei-Restore-Probe bleiben davon getrennte Nachweise. Diese Anleitung dokumentiert den Abnahmeablauf und enthält keine Umschaltung der produktiven Domain, Datenbank oder Cronjobs. Nächster Schritt: [kontrolliertes Bestandsupgrade](UPGRADE_METANET.md).

## Festgelegte Ziele und noch offene Angaben

| Zweck | Ziel |
|---|---|
| Staging-Domain | `https://travel-at.aufbollen.ch` |
| Staging-Datenbank | `travel-at` |
| Eigenes SMTP-/IMAP-Testpostfach | `travel.at@aufbollen.ch` |
| Restore-Ziel | **Neu und leer anzulegen**, Vorschlag `travel-at-restore`; eigener DB-Benutzer nur dafür |
| Separate PHPUnit-Datenbank | Nur bei einem Testlauf auf dem Host: neue leere `tripcontrol_test`; niemals `travel-at` mit Bestandskopie verwenden |
| PHP | 8.3.36; Pfade für Web/SSH/Cron in Plesk bestätigen |
| Noch zu erfassen | Projekt-/Restore-/Storage-Pfade, DB-Benutzerrechte, tatsächliche MySQL-Version, SMTP-/IMAP-TLS-Hostname, Metanet-authserv-id und Nachweis der Headerbereinigung |

Passwörter und APP_KEY ausschliesslich serverseitig hinterlegen. [Konfigurationsvorlage](ops/metanet/staging.env.example) enthält die vereinbarten Ziele und keine Zugangsdaten. **Keine vollständige produktive `.env` als aktive Staging-Konfiguration kopieren.** Den zum Backup gehörenden APP_KEY gezielt übernehmen, damit Erhaltung und verschlüsselte Bestandswerte geprüft werden können. Dieser Schlüssel und die Bestandskopie verlangen denselben Zugriffsschutz wie Produktion.

## A. Staging isolieren, bevor Laravel startet

1. Plesk: separate Domain/Verzeichnis mit Document Root auf `public`, HTTPS und zusätzlichem Zugangsschutz für die Abnahme. Schutz auf Apache **und** vorgeschaltete statische Nginx-Auslieferung prüfen. Keine öffentlich zugängliche Kopie von Kundendokumenten erzeugen. Zugangsschutz deckt auch bestehende Countdown-Tokens der Kopie ab.
2. DB-Benutzer für `travel-at` darf ausschliesslich diese DB bearbeiten; Restore-Benutzer ausschliesslich die neue Restore-DB. Produktive Zugangsdaten dürfen in keiner aktiven Staging-Konfiguration stehen. DB-Namen allein beweisen diese Trennung nicht: `SHOW GRANTS` prüfen.
3. Keine produktiven Cronjobs übernehmen; Staging-Queue, Scheduler, Import und Dateibereinigung bleiben gestoppt. Wiederhergestellte `jobs` nicht abarbeiten. Sessions/Cache der Kopie sind keine Freigabeobjekte und dürfen keine Prozesse starten. Staging verwendet ein eigenes Cookie und keine gemeinsame Cookie-Domain.
4. Storage und **alle** Fallback-Wurzeln müssen eigene echte Kopien sein. Symlinks aus einem Projektarchiv vor dem Start auflisten und prüfen: kein Link darf in die produktive Installation oder Ablage führen. Nicht blind `public/storage` übernehmen. Alle bisherigen öffentlichen Dokument-URLs bleiben gesperrt.
5. Staging-`.env` aus der Vorlage erstellen, Geheimnisse gezielt einsetzen. Absolute Cache-/View-/Log-/Lock-/Storage-Pfade auf Staging abbilden. Alte kopierte PHP-Konfigurationscaches vor dem ersten Artisan-Aufruf aus `bootstrap/cache` in einen privaten Quarantäneordner verschieben. `config:clear` allein ist kein sicherer erster Aufruf bei einem kopierten produktiven Cache.
6. Kandidat mit Lockfile installieren, PHP-Erweiterungen prüfen; keine Seeder und kein `key:generate`. Alten Code/Lockfile/vendor separat archiviert lassen. Bei einer alten DB zunächst **noch nicht migrieren**, sondern das Ausgangsinventar erstellen.

```bash
TC_PHP=/opt/php83/bin/php          # tatsächlichen Plesk-Pfad bestätigen
TC_COMPOSER=/bin/composer2        # tatsächlichen Pfad bestätigen
"$TC_PHP" -v
"$TC_PHP" -m
"$TC_PHP" "$TC_COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$TC_PHP" "$TC_COMPOSER" check-platform-reqs --no-dev
```

Benötigt: PDO MySQL, OpenSSL, mbstring, fileinfo, DOM/XML, für echte Mailprüfung IMAP. CLI und Web-SAPI separat in Plesk prüfen; keine öffentliche `phpinfo.php` anlegen. Schreibrechte für `storage/framework/{cache/data,sessions,views,locks}` und Logs mit dem Hosting-Benutzer sicherstellen. Nur Verzeichnisse der Staging-Installation anlegen.

## B. Bestehendes Backup isoliert bereitstellen

Eine vorhandene konsistente Sicherung aus SQL, passendem Dokumentstand, altem Projekt samt `.env`, Lockfile, vendor und externen Storage-Wurzeln verwenden. Ursprung, Zeitpunkt, vollständige Archivliste und SHA-256 erfassen. Keine Geheimnisse oder Backup-Dateien ins Repository aufnehmen.

Falls es noch keine solche Sicherung gibt, ist deren Erstellung ein eigener betreuter Betriebsschritt nach Abschnitt 3 der [Deployment-Anleitung](DEPLOY_METANET.md). In dieser Vorbereitung werden weder Produktion in Wartung gesetzt noch produktive Cronjobs geändert. Ein Live-Datenbankdump allein beweist keinen zeitlich passenden Dokumentstand.

Backup **nur** in `travel-at` und separate Staging-Dateiordner zurückspielen. SQL-Dump muss aus einem vertrauenswürdigen Backup stammen, eine einzelne DB ohne `CREATE DATABASE`/`USE` enthalten und darf keine fremden Datenbanken adressieren. Beschränkte DB-Rechte bleiben die technische Grenze. Archive vor dem Entpacken auf absolute Pfade, `..` und Symlinks prüfen; ausschliesslich in neue private Verzeichnisse entpacken. Kein Plesk-Restore der produktiven Subscription auslösen.

## C. Backup-/Restore-Probe aus Staging

Alle Schreibprozesse der **Staging-Kopie** bleiben angehalten, Zugangsschutz bleibt aktiv. Aus der unveränderten Kopie zuerst einen Vergleichsstand erfassen. Das Kommando liest neun Geschäftstabellen inklusive aller ursprünglichen Spalten, bildet keyed SHA-256 über die Zeilen und prüft die SHA-256 aller referenzierten Dokumente in sämtlichen Lesewurzeln. Fehlende oder widersprüchliche Dateien führen zu Exitcode 1. Klare Mailadressen, Passwörter, Tokens und Dokumentnamen stehen nicht im Inventar.

```bash
umask 077
TC_EVIDENCE=/PRIVATER-PFAD-AUSSERHALB-WEBROOT/abnahme-YYYYMMDD-HHMM
mkdir -m 700 "$TC_EVIDENCE"         # neuer Ordner, keinen bestehenden überschreiben
"$TC_PHP" artisan staging:inventory "$TC_EVIDENCE/before.json"
```

Inventar ist nur bei Schreibpause aussagekräftig. Es ersetzt kein Vollbackup: Sessions, Cache, Jobs, technische Tabellen und unreferenzierte Dateien werden nicht darin verglichen. Diese werden im vollständigen Dump/Dateiarchiv mitgesichert und zusätzlich über Archiv-Prüfsummen kontrolliert.

Nun die **Staging-Datenbank** und sämtliche Staging-Dateiwurzeln sichern. Jeder Befehl muss erfolgreich sein, bevor der nächste beginnt:

```bash
mysqldump --host=127.0.0.1 --user=STAGING_DB_USER --password \
  --single-transaction --quick --hex-blob --no-tablespaces \
  --set-gtid-purged=OFF --skip-add-drop-table \
  travel-at > "$TC_EVIDENCE/database.sql"
test -s "$TC_EVIDENCE/database.sql"
tar -czf "$TC_EVIDENCE/project.tar.gz" -C /ABSOLUTER-STAGING-PROJEKTPFAD .
# Je externer Staging-Dokumentwurzel ein eigenes Archiv; Symlink-Ziele separat sichern.
tar -tzf "$TC_EVIDENCE/project.tar.gz" > "$TC_EVIDENCE/archive-list.txt"
sha256sum "$TC_EVIDENCE/database.sql" "$TC_EVIDENCE/project.tar.gz" > "$TC_EVIDENCE/SHA256SUMS"
sha256sum -c "$TC_EVIDENCE/SHA256SUMS"
"$TC_PHP" artisan staging:inventory "$TC_EVIDENCE/before.json" --verify
```

Kein `--force` beim MySQL-Client und kein Übergehen von Exportfehlern. Die Optionen beziehen sich auf MySQL; bei abweichenden Clientversionen vorab klären. Nur InnoDB ist für `--single-transaction` ausreichend; DDL und Dateischreibprozesse während der Sicherung verhindern. Auch Archive externer Wurzeln in `SHA256SUMS` aufnehmen. Passwort wird interaktiv abgefragt.

Restore-Ziel in Plesk neu anlegen, z. B. `travel-at-restore`, mit eigenem Benutzer. **Vor** Import über genau dessen Zugang prüfen:

```bash
mysql --host=127.0.0.1 --user=RESTORE_DB_USER --password travel-at-restore \
  --execute='SELECT DATABASE(), CURRENT_USER(), VERSION(); SHOW GRANTS; SHOW FULL TABLES;'
```

Erwartet: ausschliesslich Rechte für die separate Restore-DB und **keine Tabellen/Views**. Bei Abweichung abbrechen; keine vorhandene DB leeren. Dann:

```bash
mysql --host=127.0.0.1 --user=RESTORE_DB_USER --password travel-at-restore \
  < "$TC_EVIDENCE/database.sql"
```

Projekt-/Dokumentarchive in neue private Restore-Verzeichnisse entpacken. Den unveränderten Sicherungssatz behalten. In der Restore-Kopie **vor dem ersten Laravel-Start** Schritt A wiederholen: Konfigurationscaches isolieren, eigene `.env` mit Restore-DB und ausschliesslich Restore-Dateipfaden erstellen, APP_KEY erhalten, `APP_ENV=staging`, Mail/AI/Jobs aus. Niemals die `.env` aus dem Archiv unverändert aktivieren. Es darf kein Fallback auf Staging-Quellen oder Produktion bestehen, sonst könnte ein unvollständiger Restore unbemerkt Dateien aus der Quelle lesen.

Im Restore-Projekt:

```bash
"$TC_PHP" artisan staging:inventory "$TC_EVIDENCE/before.json" --verify
"$TC_PHP" artisan migrate:status
"$TC_PHP" artisan migrate --pretend
# Nur die beiden geplanten neuen Migrationen dürfen anstehen.
"$TC_PHP" artisan migrate --force
"$TC_PHP" artisan migrate --force      # muss beim zweiten Lauf nichts ändern
"$TC_PHP" artisan staging:inventory "$TC_EVIDENCE/before.json" --verify
"$TC_PHP" artisan documents:migrate-private
```

Das alte Inventar vergleicht gezielt die ursprünglichen Spalten; neue additive Spalten ändern dessen Vergleich nicht. Neue Tabellen/Defaults werden durch Migrationen und Tests separat geprüft. Danach vorhandenen Login, Mitbenutzerzugriff und bekannte private/öffentliche Altdokumente über die abgeschirmte Restore-Webumgebung prüfen. Heruntergeladene Bytes mit dem Inventar abgleichen. Start/Ende und Dauer der Wiederherstellung notieren. Erfolgreiches Importkommando allein genügt nicht.

Für eine Wiederholung eine weitere **neue leere** Restore-DB verwenden. Keine Rückwärtsmigration und kein automatisches Löschen einer vorhandenen Kopie. Erst nach bestandener Probe denselben Kandidaten auf der Staging-Kopie migrieren und gegen das Ausgangsinventar prüfen. Produktion bleibt unangetastet.

## D. Web-/CLI-Abnahme auf travel-at.aufbollen.ch

```bash
"$TC_PHP" artisan deployment:check --staging --probe-storage
"$TC_PHP" artisan config:cache
"$TC_PHP" artisan route:cache
"$TC_PHP" artisan view:cache
```

- CLI-Dateiprobe plus Upload eines ausdrücklich neuen Testdokuments im Web: über CLI und Web dieselben SHA-256/Bytes nachweisen. Nicht nur Pfadzeichenketten vergleichen. Chroot-Sicht und Prozesssperrverzeichnis müssen dieselbe Ablage erreichen.
- Bestehende normale/Admin-Logins, Reisen, Buchungen, Aufgaben, Geburtstage und Freigaben prüfen; Passwort-Hashes und Bestandsdaten zuvor im Inventar abnehmen. Teständerungen erst danach nur an eigenen Testobjekten durchführen.
- Fremder Benutzer erhält 403; Mitbenutzer kann erlaubte Bearbeitungen durchführen, jedoch weder die Reise löschen noch Freigaben verwalten.
- Bekannte alte private/öffentliche Dokumente über autorisierten Download prüfen. Direkte URLs müssen auch **nach Passieren des zusätzlichen Staging-Zugangsschutzes** 403/404 liefern. Ein vorgeschaltetes 401 allein beweist keine Dokument-Sperre.
- `.env`, Logs, Dumps, vendor-Dateien, weitere statische Aliase und ausgelagerte Dokumentwurzeln ebenfalls prüfen. Backup- und Evidenzordner niemals als Domain/Document Root exponieren.
- Lokale Assets, Mobilansicht, Formulare, CSP und Countdown-Bestandslinks innerhalb des Zugangsschutzes abnehmen. Neue Cookie-Einstellungen für Staging prüfen.

## E. SMTP und IMAP getrennt prüfen

In Plesk die tatsächlichen TLS-Hostnamen für `travel.at@aufbollen.ch` feststellen, statt die Staging-Webdomain als Mailhost zu raten. Metanet dokumentiert SMTPS auf 465, STARTTLS auf 587 und IMAPS auf 993; Zertifikatsname und Host müssen passen ([Mail-TLS-Dokumentation](https://www.metanet.ch/de/support/webhosting-e-mail/mail/sicherer-e-mailverkehr-ssl)). Die Anwendung erzwingt jetzt auch bei STARTTLS eine verschlüsselte Verbindung vor Authentifizierung.

1. SMTP-/IMAP-Zugangsdaten ausschliesslich in der Staging-`.env` eintragen, SMTP-Host/Scheme/Port laut Plesk; IMAP exakt `{TLS-HOST:993/imap/ssl}INBOX`. Keine Zertifikatsausnahmen. Vor dem Check `config:clear` ausführen, danach Cache neu erzeugen. `MAIL_MAILER=array`, Import und AI zunächst aus lassen. Explizite SMTP-Prüfung verwendet trotzdem den benannten SMTP-Transport.
2. Nur Verbindungsprüfung, kein Mailversand/Import:

   ```bash
   "$TC_PHP" artisan staging:mail-check --smtp --imap
   ```

   SMTP prüft TLS und Anmeldung. IMAP öffnet mit `OP_READONLY`, liest nur Mailboxstatus und schliesst ohne Expunge; keine Mail wird gelesen markiert, verschoben oder gelöscht. Vorher/Nachher im Testpostfach kontrollieren. Der Check benutzt weder AI noch die Importtabelle und behauptet keine Zustellung oder DMARC-Freigabe.
3. Bewusst genau eine Testmail ausschliesslich an das eigene Postfach senden:

   ```bash
   "$TC_PHP" artisan staging:mail-check --smtp --send
   ```

   `STAGING_MAIL_RECIPIENT=travel.at@aufbollen.ch` ist die einzige erlaubte Zieladresse. Das gilt in `APP_ENV=staging` auch für normale Anwendungsmails inklusive CC/BCC; fehlende oder andere Adressen werden blockiert. SMTP-Annahme und tatsächlichen Eingang getrennt protokollieren. Referenz-ID, Empfangszeit und Header im privaten Protokoll erfassen, keine Inhalte ins Repository kopieren.
4. Für Adressbestätigung vorübergehend `MAIL_MAILER=smtp` setzen, Cache neu erzeugen. Ausschliesslich ein eigenes Staging-Konto mit `travel.at@aufbollen.ch` verwenden. Neuen Bestätigungslink anfordern, Anmeldung mit richtigem Konto, falschem Konto und abgelaufener Signatur prüfen. Link muss auf `https://travel-at.aufbollen.ch` zeigen. Keine kopierten Kundennutzer oder Aliase umschreiben. Nach dem Test normale Mail wieder auf `array` setzen.
5. Eine kontrollierte Mail von einem **eigenen externen Testkonto** an `travel.at@aufbollen.ch` senden, damit auch der externe Empfangsweg geprüft wird. Ein interner SMTP-Rundlauf allein prüft diesen Weg nicht. Empfang und ungelesenen Zustand über Webmail kontrollieren. EML nur privat ausserhalb Webroot aufbewahren.

## F. Headervertrauen und Ende-zu-Ende-Import

`MAIL_IMPORT_AUTH_RESULTS_TRUSTED=false` und `MAIL_IMPORT_ENABLED=false` bleiben gesetzt, bis folgende Belege vorhanden sind:

| Fall | Erwartung |
|---|---|
| Echte externe Mail, vertrauenswürdige authserv-id, DMARC pass passend zu From | Authentifizierung akzeptiert |
| DMARC fail, falsche authserv-id, abweichendes header.from oder mehrere From-Adressen | Abgelehnt |
| Eingeschleuster Authentication-Results-Header mit Metanets eigener authserv-id | Empfangsserver entfernt/neutralisiert ihn; kein Import aufgrund des eingeschleusten Ergebnisses |
| Fremde Reply-To-Adresse | Keine Änderung des ausgewählten Besitzers |
| Echte, aber im Staging noch unbestätigte From-Adresse | Kein Import; Webkonto bleibt nutzbar |

Die dritte Zeile muss durch den echten externen Empfangsweg und eine Metanet-Bestätigung belegt werden. Eine lokal editierte EML beweist keine serverseitige Headerbereinigung. Keine echten fremden Benutzeridentitäten verwenden; nur eigene Testadressen. Exakte authserv-id und ein bereinigtes Headerbeispiel dokumentieren, ohne generische Platzhalter als Freigabe einzutragen.

Nach diesem Nachweis nur im Staging die exakte authserv-id und `MAIL_IMPORT_AUTH_RESULTS_TRUSTED=true` setzen. Reale private EML-Beispiele prüfen:

```bash
"$TC_PHP" artisan mail:check-sender /PRIVATER-PFAD/positive.eml
# Negativfall MUSS Exitcode 1 liefern; nicht als technischen Testfehler übergehen.
"$TC_PHP" artisan mail:check-sender /PRIVATER-PFAD/negative.eml
```

Für den vollständigen Import zusätzlich Besitz der eigenen From-Adresse in Staging per SMTP bestätigen. Bei einer zweiten eigenen externen Adresse das erlaubte Mailziel kurzzeitig ausdrücklich auf genau diese Adresse umstellen, bestätigen und danach wieder `travel.at@aufbollen.ch` setzen. Keine Kundendaten oder kopierten Dokumente an AI schicken: eine minimale synthetische Buchungsmail und ausdrücklich angelegte Testreise verwenden. Eigenen begrenzten AI-Schlüssel erst für diesen Schritt einsetzen, `AI_DAILY_LIMIT` passend zu den geplanten Versuchen begrenzen. Diesen echten API-Aufruf separat dokumentieren.

```bash
# Nur nach bestandenem Header-/Adressbesitztest: MAIL_IMPORT_ENABLED=true, Cache aktualisieren.
"$TC_PHP" artisan deployment:check --staging --mail
"$TC_PHP" artisan mail:import --limit=1
```

Eigentümer, Reise, Buchung, privates Dokument und Dateihash prüfen. Dieselbe erfolgreiche Testmail gezielt wieder ungelesen setzen, erneut `--limit=1` ausführen: keine zweite Buchung/Datei. Im Testpostfach darf keine andere wartende Mail liegen. Einen kontrollierten Fehler mit einer eigenen Testmail auslösen: Nachricht bleibt ungelesen, keine halben Buchungen/Dokumente; Retry erst nach Korrektur. Alte produktive Import-IDs oder Postfachflags niemals ändern. Keine fremden Mails kopieren/importieren.

Nach Abnahme: Staging-Import wieder deaktivieren, AI-Schlüssel entfernen, normale Mail auf `array`, Test-Cronjobs weiterhin aus. Private EMLs und Testdaten gemäss vereinbarter Frist entfernen; Backup/Evidenz bis zur Entscheidung geschützt erhalten. Ohne Headervertrauen bleibt die Mailabnahme offen, auch wenn SMTP/IMAP-Verbindungen funktionieren.

## G. Abnahme dokumentieren – keine automatische Produktionsfreigabe

Die [Protokollvorlage](ops/metanet/ABNAHMEPROTOKOLL.md) privat ausfüllen. Kein Punkt wird ohne beobachteten Nachweis auf bestanden gesetzt. Bei Inventardifferenz, fehlenden Dateien, unerwarteten Migrationen oder fehlerhafter Absenderprüfung abbrechen, Kopien erhalten und Ursache klären. Keine Rücksicherung über eine Datenbank mit neuen Geschäftsdaten.

Vor einem späteren Produktionsdeployment bleiben aktuelles konsistentes Backup, Paketvergleich und ausdrückliche Freigabe erforderlich. Diese Staging-Vorbereitung ändert weder Produktion noch deren Wartungs-/Cronstatus.

Weitere Grundlagen: [Metanet SSH/Chroot](https://www.metanet.ch/de/support/webhosting-e-mail/plesk/plesk-ssh-zugriff), [MySQL Import/Export](https://www.metanet.ch/de/support/webhosting-e-mail/backup-restore/plesk-mysql-datenbank-import-export), [bestehende Deployment-Anleitung](DEPLOY_METANET.md).
