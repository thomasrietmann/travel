# Staging-Vorbereitung – lokaler Nachweis, 24.10.2026

**Metanet-Abnahme noch offen. Kein Deployment, keine echten SMTP-/IMAP-Verbindungen, keine produktiven Daten oder Dateien verwendet.** Zielkonfiguration: `travel-at.aufbollen.ch`, DB `travel-at`, eigenes Testpostfach `travel.at@aufbollen.ch`.

| Lokale Prüfung | Ergebnis | Protokoll |
|---|---|---|
| PHP 8.3.36 / MySQL 8.0.46, einschliesslich echter Dump-/Restore-Probe | 56 Tests, 409 Assertions, alle bestanden, keine übersprungenen Tests | [Ausgabe](mysql.txt), [JUnit](mysql.xml) |
| PHP 8.3.36 / SQLite | 56 Tests, 393 Assertions, 54 bestanden; MySQL-Sperre und MySQL-Restore erwartungsgemäss übersprungen | [Ausgabe](sqlite.txt), [JUnit](sqlite.xml) |
| Syntaxprüfung | 126 PHP-Dateien ohne Fehler | [Protokoll](php-lint.txt) |
| Pint für neue/geänderte PHP-Dateien dieses Schritts, git diff --check | Bestanden | Lokaler Werkzeuglauf |

Laufzeit: derselbe lokale macOS/ARM64-PHP-Build und isolierte MySQL-Server wie im [vorherigen Laufzeitnachweis](../php83-mysql-tests-2026-10-24/runtime.json). Der Server wurde ohne Netzwerklistener gestartet und nach Abschluss wieder beendet. Alle Testdaten wurden synthetisch erzeugt.

## Was zusätzlich geprüft wurde

- Ausgangsschema vor den zwei neuen Migrationen mit synthetischem Benutzer, Alias, Reise sowie privatem und öffentlichem Dokument befüllt.
- Echter `mysqldump` aus `tripcontrol_test`, Restore mit echtem `mysql`-Client in eine **andere, vorab leere** DB `tripcontrol_acceptance_restore_test`. Der Testbenutzer besitzt nur Rechte für lokale Testdatenbanken.
- Dokumentarchiv tatsächlich erzeugt und in separate temporäre Dateiwurzeln entpackt. Binäre Dateiinhalte und eine unreferenzierte Beispieldatei erhalten. Nach dem Restore keine Quell-Fallbacks verwendet.
- HMAC-Inventar vor/nach Restore und nach zweimaligem `migrate --force` identisch. Beide Dokumente über den autorisierten Download erreichbar. Restore-DB bleibt für Inspektion erhalten; temporäre Client-Konfiguration, Dump und Dateiarchive wurden entfernt.
- Neue Inventarprüfung erkennt veränderte Zeilen/APP_KEY, fehlende und widersprüchliche Dokumente; überschreibt kein vorhandenes Inventar und schreibt Dateien nur ausserhalb des Projektordners mit Modus 0600.
- Staging-Kommandos verweigern Produktion und fehlende explizite Freigabe. Verbindungsprüfung sendet ohne `--send` keine Mail. Testversand benötigt eine einzelne konfigurierte Zieladresse; Fehlerausgabe enthält keine Zugangsdaten.
- Staging-Mailversand blockiert abweichende Empfänger auch in CC/BCC. SMTP verlangt TLS auch im STARTTLS-Modus. Unsichere IMAP-Konfiguration wird abgelehnt.

Die SMTP-/IMAP-Netzfunktionen sind lokal nicht gegen Metanet ausgeführt worden. Der IMAP-Verbindungscheck öffnet serverseitig mit `OP_READONLY`; der echte Nachweis unveränderter Mailflags und die Zustellprüfung stehen im Metanet-Abnahmeprotokoll. Der PHP-Build enthält keine IMAP-Erweiterung. Externe Dienste werden in den Anwendungstests ersetzt; es gab keine echten AI-Aufrufe.

## Restore-Test erneut ausführen

Erfordert zwei **ausschliesslich für Tests** eingerichtete Datenbanken, deren Namen auf `_test` enden; das Restore-Ziel muss neu/leer und vom Quellziel verschieden sein. Die Suite leert die Quelldatenbank durch `migrate:fresh`. Niemals Staging mit Bestandskopie oder Produktion dafür verwenden. Für jede Wiederholung eine neue leere Restore-DB bereitstellen; der Test löscht diese nicht automatisch.

```bash
TC_MYSQLDUMP_BIN=/pfad/zu/mysqldump \
TC_MYSQL_BIN=/pfad/zu/mysql \
TC_RESTORE_TEST_DATABASE=neue_restore_test \
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=tripcontrol_test DB_USERNAME=TESTBENUTZER \
  /pfad/zu/php-8.3.36 vendor/bin/phpunit --filter MysqlRestoreTest
```

Passwort über isolierte Test-`.env` oder geschützte CI-Konfiguration. Für den lokalen Lauf wurde der private Unix-Socket `/private/tmp/tripcontrol-php83-mysql/mysql.sock` verwendet. GitHub-CI ist um diese Probe erweitert, wurde hier aber nicht ausgeführt. Die CI erwartet MySQL-Client und mysqldump auf dem Runner und bricht bei Fehlen ab.

Die neuen Tests deckten zunächst zwei Fehler im Testaufbau auf: Die Schema-Auflistung musste ausdrücklich auf die Restore-DB begrenzt werden, und die beiden synthetischen Dokumente benötigten verschiedene Pfade. Beides ist korrigiert; die obigen vollständigen Ergebnisse stammen aus dem anschliessenden erfolgreichen Lauf.

Nächster betrieblicher Schritt: [Staging-Abnahme auf Metanet](../../STAGING_ABNAHME_METANET.md). Der tatsächliche Host, SMTP-/IMAP-TLS-Name, die produktive MySQL-Version und das Headervertrauen sind damit noch nicht nachgewiesen.
