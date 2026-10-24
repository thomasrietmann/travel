# Release-/Upgrade-Prüfung – 24.10.2026

Der Betreiber hat die Metanet-Staging-Abnahme bestätigt. Der Sicherheitsrelease und das kontrollierte Bestandsupgrade wurden anschliessend lokal geprüft; **kein Produktionsdeployment und keine produktive Migration wurden ausgeführt**.

| Prüfung | Ergebnis | Nachweis |
|---|---|---|
| PHP 8.3.36 / MySQL 8.0.46 | 64 Tests, 459 Assertions; vollständig bestanden, keine übersprungenen Tests | [Testausgabe](mysql.txt), [JUnit](mysql.xml) |
| PHP 8.3.36 / SQLite | 64 Tests, 393 Assertions; 54 bestanden, 10 MySQL-Prüfungen erwartungsgemäss übersprungen | [Testausgabe](sqlite.txt), [JUnit](sqlite.xml) |
| PHP-Syntax inkl. Upgrade-Einstieg | 130 Dateien ohne Fehler | [Protokoll](php-lint.txt) |
| Composer validate / audit --locked | Gültiges Manifest, keine bekannten Sicherheits-Advisories | Lokaler Werkzeuglauf |
| Pint für Upgrade-Service/-Kommando/-Einstieg und Tests | Bestanden | Lokaler Werkzeuglauf |

Der MySQL-Lauf verwendet ausschliesslich synthetische Daten auf dem temporären lokalen Server mit Unix-Socket, InnoDB und striktem SQL-Modus. Die neue leere Restore-Datenbank war `tripcontrol_release_restore_test`. Dump und Archiv wurden wirklich zurückgespielt; anschliessend liefen die Bestandsvergleiche und additiven Migrationen. Der Server wurde nach dem Testlauf wieder sauber beendet. Laufzeitdetails entsprechen dem [PHP-/MySQL-Nachweis](../php83-mysql-tests-2026-10-24/runtime.json).

Zusätzliche acht Upgrade-Tests prüfen:

- Standardaufruf ohne Schreiboperationen.
- Ausführung und Wiederholung mit unveränderten Geschäftsdaten, Dokumentbytes und ursprünglichem Inventar; Kopien bleiben privat, öffentliche Quellen erhalten.
- Exakte Datenbank, Wartungsmodus, Backupbestätigung und gestoppte Schreiber als Ausführungsvoraussetzungen.
- Ablehnung unbekannter Migrationshistorie oder noch ausstehender älterer Migrationen.
- Abbruch bei nur teilweise ausgeführten MySQL-DDL-Schritten.
- Keine Neubasierung des Inventars nach zwischenzeitlichen Datenänderungen.
- Abbruch vor Migration bei fehlenden Dateien oder unzulässiger Evidenzablage.
- Gemeinsame Importsperre; Staging-Freigabeflag auf Produktion nicht erlaubt.

Einstieg und betriebliche Grenzen: [UPGRADE_METANET.md](../../UPGRADE_METANET.md). Backup-/Schreibstopp-Flags sind explizite Zusagen des Betreibers; das Skript selbst kann weder dessen Backup-Restore-Nachweis noch alle alten/externen Schreibprozesse verifizieren. Keine neuen echten Mail-/AI-Aufrufe in diesem Lauf. GitHub-CI wurde durch diesen lokalen Nachweis nicht vorweggenommen.
