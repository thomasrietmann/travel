# Metanet Staging-Abnahme – Vorlage

Status: **unausgefüllte Vorlage**. Der Betreiber hat die Staging-Abnahme am 24.10.2026 bestätigt; die konkreten Einzelbelege liegen beim Betreiber. Eine ausgefüllte Kopie ausserhalb Webroot/Repository speichern. Keine Passwörter, APP_KEY, Tokens, EML-Inhalte oder Kundendaten eintragen.

- Verantwortliche Person / Datum / Wartungsfreie Staging-Zeitspanne:
- Staging: `https://travel-at.aufbollen.ch`, DB `travel-at`, Testpostfach `travel.at@aufbollen.ch`
- Release-Commit und Prüfsummen von Lockfiles/Releasepaket:
- PHP-Version/Pfad Web, CLI und geplanter Cron / Erweiterungen:
- MySQL-Version, InnoDB, SQL-Modus / DB-Rechte kontrolliert:
- Bestätigte Staging-/Restore-/Storage-/Chroot-Pfade, private Evidenzablage:
- Separate leere Restore-DB und ausschliesslich berechtigter Restore-Benutzer:
- Backup-Ursprung/Zeitpunkt / vollständige Archive und SHA-256 geprüft:
- Restore gestartet / abgeschlossen / Dauer / Bezug auf private Logdateien:

| ID | Prüfung | Status (offen/bestanden/fehlgeschlagen) | Nachweis, Zeitpunkt, Person |
|---|---|---|---|
| I01 | Staging-Domain geschützt; kein Zugriff auf produktive DB/Pfade/Cronjobs | offen | |
| I02 | Ausgangsinventar, vollständiger SQL-/Dateibackup, externe Wurzeln und Symlinks geprüft | offen | |
| R01 | Restore in neue leere DB und separate Dateiwurzeln ohne Quell-Fallback | offen | |
| R02 | Inventar vor/nach Restore identisch, APP_KEY korrekt, alle Dokumentbytes vorhanden | offen | |
| R03 | Nur erwartete additive Migrationen, zweiter Lauf ohne Änderung, Bestandsvergleich identisch | offen | |
| W01 | Bestehende Logins, Admin, Freigaben, Zugriffssperren, Countdown geprüft | offen | |
| W02 | Alte Downloads/hashgleich, privater Web-Upload und CLI erreichen gleiche Bytes | offen | |
| W03 | Direkte Dokument-URLs/Backups/Logs/.env auch hinter Zugangsschutz gesperrt | offen | |
| W04 | Formulare, Mobilansicht, lokale Assets, CSP und Caches geprüft | offen | |
| M01 | SMTP-TLS/Login erfolgreich, Zertifikatsname korrekt | offen | |
| M02 | IMAP-TLS/Login/status nur lesend; Flags/Nachrichten unverändert | offen | |
| M03 | Testmail angenommen UND angekommen, Referenz-ID/Empfangszeit notiert | offen | |
| M04 | Signierte Bestätigung, korrektes Konto/URL, fremdes Konto/abgelaufener Link abgelehnt | offen | |
| M05 | Externer Empfangsweg, exakte authserv-id, Metanet-Nachweis für Headerbereinigung | offen | |
| M06 | Echte positive/negative EML-Prüfung inkl. eingeschleustem Header | offen | |
| M07 | Synthetischer Ende-zu-Ende-Import, Eigentümer/Reise/Datei korrekt, Wiederholung ohne Duplikat | offen | |
| M08 | Fehler bleibt ungelesen, kein Teilimport, kontrollierter Retry funktioniert | offen | |
| A01 | Staging-Import/AI/normaler Mailversand nach Abnahme wieder deaktiviert | offen | |

Offene Punkte / Abweichungen / zugehörige private Belege:

Staging-Webabnahme: offen. Staging-Mailabnahme: offen. Produktionsfreigabe: **nicht erteilt**.

Nächste Entscheidung / Verantwortliche Person / Datum:
