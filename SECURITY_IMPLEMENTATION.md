# Sicherheitskorrekturen und Refactoring – 24.10.2026

Arbeitszweig: `codex/security-refactor`, Ausgangsstand `d666874`. Die Änderungen sind für die bestehende Metanet-Anwendung mit PHP 8.3.36/MySQL vorbereitet. Es wurden keine produktiven Daten, Konten oder Dateien verändert und kein Deployment durchgeführt.

## Schritte und Abdeckung des Bestandsaudits

| Befund | Umsetzung | Verbleibender Betriebsnachweis |
|---|---|---|
| F01 Mailidentitäten | Normalisierung, transaktionale Sperre über Benutzer/Aliase, signierte Besitzbestätigung; widersprüchliche Altzuordnungen werden nicht ausgewählt | Historische Kollisionen prüfen; SMTP-Zustellung und Bestätigungen durchführen |
| F02 Absenderspoofing | Nur einzelne From-Adresse; kein Reply-To/Sender-Fallback; konservative DMARC-Prüfung hinter expliziter Vertrauensfreigabe | Metanet muss fremde gefälschte Authentication-Results entfernen; echtes Headerformat noch unbekannt |
| F03 Private Migration | Standardmässig Inventar, optional geprüfte Kopie, Quellen bleiben immer erhalten, Fehlercode bei Konflikten/Schreibfehlern | Vollständiges produktives Inventar und Speicherplatz prüfen |
| F04 Dokumentaustausch | Neue Datei vor DB-Austausch prüfen; alter Pfad bleibt bei Fehler erhalten; spätere Bereinigung | Web-/CLI-Schreibrechte im realen Hosting testen |
| F05 Öffentliche Altdateien | Apache-Sperre, autorisierte Downloads und private neue Dateien | Nginx/static Aliase separat sperren und bekannte reale URLs testen |
| F06 Reproduzierbarkeit/Deployment | Composer-/pnpm-Lockfiles, Testbasis, PHP-8.3-/MySQL-CI, neue Backup-/Restore-Anleitung | Produktions-Lockfile mit Kandidat vergleichen; CI und Staging auf realer MySQL-Version ausführen |
| F07 Demo-Admin | Produktions-Seeder gesperrt; Admin-Erstellung überschreibt keine Konten; Preflight erkennt bekannte Demo-Passwörter; separates Passwortkommando erhält Konten/Daten | Bestehende Konten durch Betreiber prüfen, gefährdete Passwörter kontrolliert ändern |
| F08 Limits | Login-, Registrierungs-, Bestätigungs- und Schreibraten; AI-Tagesbudget; kleinere Web-AI-Batches | Gemeinsamer Datenbank-Cache; Hosting-Limits und Laufzeiten prüfen |
| F09 Importsicherheit | Eine prozessgebundene Sperre für Web/Artisan/Cron; begrenzte Wiederholungen; fehlgeschlagene Mails ungelesen; Commit und Lesebestätigung getrennt; Fehler-Exitcodes | Alte bereits gelesene Fehlversuche separat prüfen; Rückstand/Blockierungen überwachen |
| F10 Dateilebenszyklus | Eine Schreibablage; alle alten Lesewurzeln erhalten; kompensierende Bereinigung nach DB-Rollback und nach gewollten Löschungen | Historische unreferenzierte Dateien nicht automatisch löschen; kanonische Ablage für beide Chroots testen |
| F11 Mailinhalte | Größen-/MIME-/Anzahl-/Tiefenbegrenzungen; echte Inhaltserkennung; Mail-PDF aus escaped Text statt fremdem HTML | Neue Mail-PDFs haben vereinfachtes Layout; reale Beispiele testen |
| F12 AI/Protokolle | Responses `store=false`, Dateiexpiry 1 Stunde, sofortiger Löschversuch mit Wiederholung; generische Fehler; weniger Summary-Daten; rotierte/beschränkte Logs; minimiertes Web-Aktionsprotokoll | API-Projekteinstellungen, Logrechte und Aufbewahrung prüfen; keine Behauptung vollständiger revisionssicherer Auditierung |
| F13 Browser/Freigaben | Lokale CSS/JS-Assets, CSP und weitere Header, kanonischer Host, sichere Produktionscookies; Countdown aktivieren/rotieren/widerrufen | HTTPS-Weiterleitung in Plesk; bestehende öffentliche Tokens bleiben absichtlich gültig |
| F14 Konfiguration | Leere optionale Pfadwerte fallen auf gültige Standardpfade zurück | Bestehende Metanet-Overrides erhalten und beide Laufzeitkontexte testen |

## Bewusst erhaltene Kompatibilität

Die beiden neuen Migrationen erweitern das Schema. Sie ändern keine bisherigen Benutzer-, Reise-, Buchungs-, Dokument- oder Freigabezeilen und keine vorhandenen Passwort-Hashes/Tokens. Kein Wechsel zu Argon2, keine neue APP_KEY, kein Datenbankwechsel. Alle bestehenden Dokumentpfade bleiben lesbar, auch in privaten/öffentlichen Fallback-Wurzeln. Eigentümer und Mitbenutzer behalten die bisherigen Berechtigungen. Alte öffentliche Countdown-Tokens bleiben bis zum ausdrücklichen Widerruf aktiv.

Die gemeinsame Buchungsvalidierung, Dokumentablage und Reise-Sichtbarkeit reduzieren duplizierten Code. Mailprotokoll, Absenderprüfung, MIME-Verarbeitung und PDF-Erstellung sind getrennt testbar. Der LEG-Ansatz wurde übernommen, ohne dessen abweichendes Datenmodell, Passwortverfahren oder Build-Majorversion pauschal zu übertragen.

## Prüfungen

- Lokale Suite mit **PHP 8.3.36 und MySQL 8.0.46: 45 Tests, 337 Assertions, vollständig bestanden, keine übersprungenen Tests**. InnoDB, `utf8mb4_unicode_ci` und strikter SQL-Modus; ausschliesslich eine temporäre lokale Testdatenbank mit eingeschränktem Testbenutzer und ohne Netzwerklistener verwendet.
- Vergleich mit demselben **PHP 8.3.36 und SQLite: 45 Tests, 335 Assertions; 44 bestanden, nur der MySQL-spezifische Test erwartungsgemäss übersprungen**. Composer-Plattformanforderungen und Syntaxprüfung aller 119 PHP-Dateien erfolgreich. Es waren keine Anwendungskorrekturen aufgrund dieser Testläufe erforderlich. Protokolle, JUnit-Ergebnisse, genaue Laufzeit und Grenzen: [Testnachweis](outputs/php83-mysql-tests-2026-10-24/README.md).
- Der MySQL-Test prüft die gegenseitige Sperre zweier echter Verbindungen. CI ist für PHP **8.3.36**, MySQL 8.0 und SQLite vorbereitet, wurde in dieser lokalen Sitzung nicht auf GitHub ausgeführt. Der lokale Nachweis stammt von macOS/ARM64 und ersetzt keinen Metanet-Staging-Lauf.
- Dokumenttests: Fremdzugriff, Mitbenutzerzugriff, Altpfade, Ersatzdatei-Schreibfehler, DB-Fehler nach Upload, Buchungsrollback, Löschreihenfolge, alle Kopien, fehlgeschlagene/wiederholte Migration, Prüfsummenkonflikte und Pfadtraversal.
- Identität/HTTP: Aliasübernahme, normalisierte Eindeutigkeit, Bestätigungslinks, bestehender bcrypt-Login, Rate Limits, CSRF, falscher Host, Countdown-Widerruf und Passwortwechsel ohne Verlust von Eigentümerschaft/Dokumenten.
- Mail/AI: Spoofing/Reply-To, unbestätigte Adresse, gefälschter PDF-Inhalt, Größen-/Tiefenlimit, idempotenter Import, Fehler nach Commit einschließlich verlorener Commit-Bestätigung, maskierte Header-Injektionen, Retry, Prozesssperre, API-Aufbewahrung/Löschung, Tagesbudget und reduzierte Summary-Daten. Externe Dienste sind dabei gefälscht; es gab keine echten AI-Aufrufe und keinen Test am produktiven Postfach.
- Upgrade-Test: alte Beispieldaten vor den neuen Migrationen anlegen, zweimal migrieren, anschließend bisherige Zeilen bytegleich vergleichen und alte Datei über geteilten Zugriff herunterladen.
- Composer-Validierung und Composer-Audit: erfolgreich, keine gemeldeten Advisories/abandoned packages im neuen Lockfile.
- pnpm-Audit nach Aktualisierung von `postcss-selector-parser` auf 7.1.7: keine bekannten Schwachstellen gemeldet. Der Override liegt wegen pnpm 11 in `pnpm-workspace.yaml`; Tailwind bleibt 3.4.19.
- Lokaler CSS-Build und JavaScript-Syntaxprüfung erfolgreich. PHP-Syntaxcheck: 119 Dateien ohne Fehler. Formatierung und `git diff --check` erfolgreich. Konfigurations-, Routen- und View-Caches wurden in einer isolierten Umgebung erfolgreich erzeugt. Ein visueller Test auf dem realen Metanet-Host bleibt Bestandteil der Abnahme.

## Freigabestatus

Staging-Abnahme für `travel-at.aufbollen.ch`, DB `travel-at` und Testpostfach `travel.at@aufbollen.ch` vorbereitet: [Ablauf](STAGING_ABNAHME_METANET.md), [offenes Protokoll](ops/metanet/ABNAHMEPROTOKOLL.md), [Konfigurationsvorlage](ops/metanet/staging.env.example). Neue explizite Staging-Kommandos erstellen/vergleichen private Inventare und prüfen SMTP/IMAP getrennt. Staging blockiert Mails an andere Empfänger; SMTP erfordert nun auch bei STARTTLS zwingend TLS.

Nach diesen Ergänzungen: **PHP 8.3.36/MySQL 8.0.46: 56 Tests, 409 Assertions, vollständig bestanden**, inklusive echter lokaler SQL-Dump-/Datei-Restore-Probe in einer zweiten leeren Testdatenbank und anschliessendem additivem Upgrade. SQLite: 56 Tests/393 Assertions, zwei MySQL-Prüfungen erwartungsgemäss übersprungen; Syntaxprüfung 126 PHP-Dateien erfolgreich. [Aktueller lokaler Testnachweis](outputs/staging-preparation-2026-10-24/README.md). Die weiter oben dokumentierten 45 Tests beschreiben den vorherigen Stand. Es wurden keine Metanet-Verbindungen, echte Mailzustellung oder produktiven Restores ausgeführt.

**Staging-Abnahme vom Betreiber am 24.10.2026 bestätigt.** Die Metanet-Einzelprotokolle wurden hier nicht selbst erhoben und liegen beim Betreiber. Das kontrollierte [Bestandsupgrade](UPGRADE_METANET.md) ist vorbereitet; es begrenzt die Migrationen, verlangt Wartung/Backupbestätigung/Schreibpause und vergleicht Daten und Dokumente mit einem dauerhaft gespeicherten Ausgangsinventar. Für das spätere Produktionsfenster bleiben aktuelles konsistentes Backup, Prüfung des tatsächlich eingespielten Release und HTTP-/Betriebsnachkontrolle erforderlich. Es wurde kein Produktionsdeployment ausgeführt.

Abschliessender lokaler Release-Lauf mit Upgrade-Skript: **64 Tests / 459 Assertions unter PHP 8.3.36 und MySQL 8.0.46 vollständig bestanden**, inklusive echter Restore-Probe. SQLite: 64 Tests / 393 Assertions, zehn MySQL-Prüfungen übersprungen. PHP-Syntax: 130 Dateien ohne Fehler. [Aktueller Release-Nachweis](outputs/release-upgrade-2026-10-24/README.md).

Der Mailimport bleibt ohne nachgewiesene Absenderprüfung gesperrt. Das ist eine ausdrückliche Freigabebedingung; vorhandene Mails/Dokumente werden deshalb nicht verworfen. Soll der Mailimport ohne Unterbrechung weiterlaufen, erst nach bestandener Mailabnahme ausrollen. Schrittfolge und Rückkehrverfahren: [DEPLOY_METANET.md](DEPLOY_METANET.md).

Der historische Auditbericht [SECURITY_REVIEW_2026-10-24.md](SECURITY_REVIEW_2026-10-24.md) und seine Reproduktionsartefakte beschreiben den Ausgangsstand; sie wurden nicht als neue Testergebnisse umgedeutet.
