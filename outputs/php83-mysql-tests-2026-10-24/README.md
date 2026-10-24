# PHP 8.3.36 / MySQL – Testnachweis vom 24.10.2026

Die vollständige TripControl-Suite wurde lokal mit der verlangten PHP-Version gegen einen echten MySQL-Server ausgeführt. Alle MySQL-Tests bestanden im ersten Anwendungstestlauf. Es waren keine Korrekturen am Anwendungscode erforderlich. Kein Deployment und kein Zugriff auf produktive Benutzer, Daten, Dokumente oder Mailkonten.

## Ergebnisse

| Prüfung | Ergebnis | Nachweis |
|---|---|---|
| PHP 8.3.36 + MySQL 8.0.46 | 45 Tests, 337 Assertions; keine Fehler, keine übersprungenen Tests | [Testausgabe](mysql.txt), [JUnit](mysql.xml) |
| PHP 8.3.36 + SQLite im Speicher | 45 Tests, 335 Assertions; 44 bestanden, MySQL-Sperrtest erwartungsgemäss übersprungen | [Testausgabe](sqlite.txt), [JUnit](sqlite.xml) |
| Composer-Plattformanforderungen | Alle erfüllt | [Protokoll](platform.txt) |
| PHP-Syntax | 119 Dateien ohne Syntaxfehler | [Protokoll](php-lint.txt) |

Der MySQL-Lauf umfasst insbesondere die Sperre zwischen zwei echten Datenbankverbindungen, den Erhalt bestehender Zeilen/Passwort-Hashes/Freigabetokens beim additiven Upgrade und den anschliessenden Download einer alten Dokumentdatei durch einen Mitbenutzer. Dokumentersatz, Transaktionsrollback, Löschreihenfolge, Authentifizierung, Mailimport-Schutz und Seitenrendering sind ebenfalls enthalten. Die Tests erzeugen hierfür synthetische Daten und Dateien.

## Isolierte Laufzeit

- macOS/Darwin, ARM64; selbst gebautes PHP-CLI **8.3.36**, PHPUnit **11.5.57**, vorhandene Pakete aus dem Projekt-Lockfile.
- Offizielles MySQL-Community-Binärpaket **8.0.46** für macOS/ARM64. Ausschliesslich Unix-Socket, `skip_networking=1`, X-Protokoll deaktiviert.
- Frische Datenbank `tripcontrol_test`, Benutzer `tripcontrol_test_user@localhost` mit Rechten ausschliesslich für diese Datenbank. Alle 19 Tabellen: InnoDB, `utf8mb4_unicode_ci`.
- Strikter SQL-Modus einschliesslich `ONLY_FULL_GROUP_BY` und `STRICT_TRANS_TABLES`.
- Server, Daten und PHP-Build liegen unter `/private/tmp/tripcontrol-php83-mysql`, mit Verzeichniszugriff nur für den lokalen Benutzer. Der Testserver wurde nach Abschluss sauber beendet; keine globale Installation und kein Autostart.
- [Maschinell ausgelesene Laufzeit, Tabellen-Engines und PHP-Erweiterungen](runtime.json).

PHP-Quellarchiv: `https://www.php.net/distributions/php-8.3.36.tar.gz`, SHA-256 `c93f3441f87f6ab156bef4432db215d7e61ca2c0183bb3a2aa28d9886ab59f53` (gegen offizielle Angabe geprüft).

MySQL-Archiv: `https://cdn.mysql.com/Downloads/MySQL-8.0/mysql-8.0.46-macos15-arm64.tar.gz`, veröffentlichte MD5-Prüfsumme `aefb850c25a2c703a63554283fb94cae` stimmt überein; Download über HTTPS.

## Wiederholung

Nur in einem isolierten Testverzeichnis mit eigener Testdatenbank und eigenem Benutzer ausführen. Die Suite führt `migrate:fresh` aus und leert diese Datenbank. Testpasswort über die Test-`.env` oder eine sichere CI-Variable bereitstellen. Kein produktives Storage-Verzeichnis verwenden.

```bash
TC_PHP=/pfad/zu/php-8.3.36
"$TC_PHP" -r 'if (PHP_VERSION !== "8.3.36") { fwrite(STDERR, "PHP 8.3.36 erforderlich\n"); exit(1); }'
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=tripcontrol_test DB_USERNAME=tripcontrol_test_user \
  "$TC_PHP" vendor/bin/phpunit --colors=never --testdox --log-junit mysql.xml
DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  "$TC_PHP" vendor/bin/phpunit --colors=never --testdox --log-junit sqlite.xml
```

Für den protokollierten lokalen MySQL-Lauf wurde statt TCP `DB_SOCKET=/private/tmp/tripcontrol-php83-mysql/mysql.sock` verwendet; PHP-Binary: `/private/tmp/tripcontrol-php83-mysql/sapi/cli/php`. Vor einer Wiederholung muss der temporäre MySQL-Server ausdrücklich wieder gestartet werden.

## Grenzen

Dies ist ein lokaler Kompatibilitäts- und Regressionstest, keine Produktionsfreigabe. Die genaue produktive MySQL-Version ist noch nicht bekannt. Metanet-Staging, Restore einer separaten Bestandskopie, Web-/CLI-Chroot, Dateirechte und echte SMTP-/IMAP-Zustellung bleiben zu prüfen. IMAP ist in diesem lokalen PHP-Build nicht enthalten; die Mailbox und externe HTTP-Dienste werden in der Suite durch Testimplementierungen ersetzt. Es wurden keine echten OpenAI-Aufrufe oder Mails versendet. Die GitHub-CI wurde in diesem Arbeitsschritt nicht ausgeführt.

Die weiteren Freigabeschritte stehen in [DEPLOY_METANET.md](../../DEPLOY_METANET.md).
