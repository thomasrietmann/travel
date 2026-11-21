# Bestandsupgrade auf den Sicherheitsrelease vom 24.10.2026

Die Staging-Abnahme wurde vom Betreiber am **24.10.2026 bestätigt**. Diese Anleitung und das Skript bereiten das anschliessende Upgrade der heutigen produktiven Installation vor. **In diesem Arbeitsschritt wurde kein Produktionsdeployment ausgeführt.** Die konkreten Metanet-Abnahmebelege bleiben beim Betreiber; die lokalen Testnachweise stehen separat im Repository.

## Was das Skript tut

Einstieg: `php ops/metanet/upgrade.php`, alternativ `php artisan deployment:upgrade`.

- Ohne `--apply`: nur Bestandsprüfung und Anzeige der ausstehenden Release-Migrationen; keine Schema- oder Dokumentänderung.
- Mit `--apply`: verlangt exakt benannte MySQL-Datenbank, `production` oder `staging` ohne Debug, vorhandenen APP_KEY, bcrypt, PHP mindestens 8.3.21 (kleiner als 9.0), InnoDB, Wartungsmodus und bestätigtes Backup/gestoppte Schreiber.
- Erlaubt ausschliesslich die Migrationen `2026_10_24_000001_create_document_file_deletions_table`, `2026_10_24_000002_add_email_identity_security`, `2026_11_21_000001_add_deposit_amount_to_bookings_table` und `2026_11_21_000002_create_itinerary_stops_table`. Ältere noch ausstehende, fremde oder teilweise ausgeführte Migrationen führen zum Abbruch.
- Speichert vor der ersten Änderung ein privates `upgrade-before.json` (0600). Vergleicht die Geschäftstabellen samt ursprünglichen Spalten, Passwort-Hashes, Tokens, APP_KEY-Fingerabdruck und alle referenzierten Dokumentbytes vor/nach der Änderung. Bei vorhandenem Tagesplan erfasst das Inventar auch `itinerary_stops`. Werte der Geschäftstabellen stehen nur als HMAC im Inventar.
- `--copy-documents` kopiert zusätzlich geprüfte Altdateien in die private Ablage. Originale bleiben erhalten, bestehende Dokumentpfade ändern sich nicht. Widersprüchliche/fehlende Kopien führen zum Abbruch.
- Eine Wiederholung mit **demselben Evidenzordner** verwendet unverändert das erste Inventar. Sie akzeptiert keine zwischenzeitlich veränderten Geschäftsdaten als neuen Ausgangsstand. Bereits vollständig protokollierte Migrationen werden nicht nochmals ausgeführt.
- Eine eigene Prozesssperre und die gemeinsame Importsperre verhindern konkurrierende Upgrades/neue Importe. Sie ersetzen nicht die betriebliche Schreibpause: alte Cronprozesse, Wartungs-Bypässe, Queue und manuelle DB-Schreiber müssen separat gestoppt sein.

Es gibt kein automatisches `migrate:fresh`, Seeding, `key:generate`, Passwort-/Tokenwechsel, SQL-Restore, Rollback, Löschen alter Dokumente oder `artisan up`. Das Skript installiert keine Pakete, wechselt keinen Git-Stand und ändert weder `.env` noch Mailfreigaben. Diese Schritte gehören bewusst zum betreuten Releaseablauf.

## PHP-8.3-Kompatibilität – Ergänzung vom 21.11.2026

Die bisherige Mindestprüfung auf 8.3.36 entsprach dem zuerst geprüften Release und war keine erforderliche Sprachfunktion der Anwendung. Die technische Untergrenze ist jetzt zentral in `app/Support/PhpRuntime.php` auf **8.3.21** definiert und mit Composer abgestimmt. Damit wird die auf Metanet beobachtete Kombination aus CLI 8.3.21 und Web/FPM 8.3.36 berücksichtigt; PHP 8.4 ist nicht erforderlich. Der Einstieg prüft die Version vor dem Laden von Composer/Laravel.

Der gemergte Oktober-Commit `5d49129` enthält noch die alte Sperre. Für die neue Kompatibilität müssen die Änderungen vom 21.11.2026 erst als neuer Release übernommen werden; nicht nur die Versionsprüfung auf dem Server von Hand entfernen. Für dieses Kompatibilitätsupdate sind **keine zusätzlichen Datenbankmigrationen** nötig. Die beiden Oktober-Migrationen bleiben unverändert.

Technische Kompatibilität und Sicherheits-Patchstand getrennt beurteilen: die Versionsnummer 8.3.21 beweist keine Sicherheits-Backports des Hosters. Ein aktuelles bzw. vom Hoster sicherheitsgepflegtes PHP 8.3 bleibt die Betriebsempfehlung. Die bestandene Oktober-Abnahme ist kein Nachweis für die geänderten Abhängigkeiten; vor dem produktiven Update die neue Version auf dem tatsächlichen Hosting-PHP im Staging prüfen.

Lokale Prüfung am 21.11.2026: PHP 8.3.21 und 8.3.36 bestanden jeweils die gesamte MySQL-Suite mit **78 Tests / 476 Assertions**, einschliesslich Restore und Bestandserhalt. Die Grenzen des lokalen Nachweises stehen im [README](README.md#tests-und-assets). Produktion und Metanet-Cron wurden nicht verändert.

Für die Anzahlungen kommt die optionale Spalte `bookings.deposit_amount` hinzu. Bei einem neuen Upgrade einen neuen Evidenzordner für den aktuellen Bestand verwenden.

Der Tagesplan benötigt die zusätzliche Tabelle `itinerary_stops` für manuell erfasste Stopps und Teilstrecken. Bestehende Buchungen erscheinen anhand ihrer gespeicherten Daten direkt im Tagesplan.

## 1. Release und aktuelle Sicherung vorbereiten

1. Geprüften Release-Commit eindeutig festlegen. Bestehende produktive PHP-/Paketversionen mit dem Kandidaten vergleichen. Lokale Änderungen und ein bislang ungetracktes produktives `composer.lock` zuerst sichern; nichts durch erzwungenen Checkout oder `git clean` entfernen.
2. Aktuelle zusammengehörige Sicherung von MySQL, Dokumenten, externen Fallback-Wurzeln, kompletter alter Anwendung samt `.env`, vendor und Lockfile erstellen. Schreibpause und Wiederherstellung wie in [DEPLOY_METANET.md](DEPLOY_METANET.md), Abschnitte 3 und 8. Das Staging-Backup ersetzt keine aktuelle produktive Sicherung.
3. Import-/Bereinigungs-Cronjobs, Queue und weitere Schreiber pausieren, Webanwendung im **alten** Release in Wartung setzen, laufende Prozesse auslaufen lassen. Bypass-Zugriffe dürfen während des Inventars nicht schreiben.
4. Den geprüften Code und seine Abhängigkeiten am bisherigen Standort einspielen. `.env`, APP_KEY, Dokumente, Chroot-/Fallback-Pfade und `config/exchange.local.php` erhalten. **Keine Staging-`.env` verwenden**: `travel-at` ist die Staging-Datenbank, nicht der bekannte Name der Produktivdatenbank.

```bash
TC_PHP=/opt/php83/bin/php       # tatsächlichen bestehenden PHP-8.3-Pfad prüfen
TC_COMPOSER=/usr/bin/composer2 # in der Metanet-SSH-Sitzung ermittelter Pfad; erneut prüfen
"$TC_PHP" -v
"$TC_PHP" "$TC_COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$TC_PHP" "$TC_COMPOSER" check-platform-reqs --no-dev
"$TC_PHP" artisan config:clear
```

Auf Produktion `APP_ENV=production`, `APP_DEBUG=false`, `STAGING_CHECKS_ENABLED=false`; bestehende DB-Verbindung und APP_KEY erhalten. Der SMTP-Transport erzwingt TLS. Mailimport-Freigaben aus der tatsächlich bestandenen Abnahme gezielt übernehmen; keine authserv-id raten. Die vollständigen SMTP-/Storage-/Chroot-Angaben stehen in der Deployment-Anleitung.

## 2. Bestand prüfen

Im bestehenden Projektverzeichnis mit dessen aktiver `.env` ausführen:

```bash
TC_DATABASE='EXAKTER-BESTEHENDER-PRODUKTIV-DB-NAME'
"$TC_PHP" ops/metanet/upgrade.php --expect-database="$TC_DATABASE"
```

Erwartet werden die korrekte Datenbank, die noch ausstehenden der oben aufgeführten Migrationen und lesbare, widerspruchsfreie Dokumente. Jeder nicht erfolgreiche Exitcode bedeutet Stopp. Bei unbekannter Altinstallation zuerst den Schema- und Migrationsstand abgleichen, statt alte Migrationen pauschal nachzuholen.

## 3. Upgrade ausdrücklich ausführen

Privaten Ordner ausserhalb sämtlicher Webroots und des Projekts neu anlegen. Den gleichen Ordner bei jeder Wiederholung dieses Upgradeversuchs behalten. Keine Inventare mit neuen Snapshots überschreiben.

```bash
umask 077
TC_EVIDENCE=/PRIVATER-PFAD/tripcontrol-upgrade-YYYYMMDD-HHMM
mkdir -m 700 "$TC_EVIDENCE"

"$TC_PHP" ops/metanet/upgrade.php \
  --apply \
  --expect-database="$TC_DATABASE" \
  --evidence-dir="$TC_EVIDENCE" \
  --backup-confirmed \
  --writers-stopped \
  --copy-documents > "$TC_EVIDENCE/upgrade.log" 2>&1
TC_UPGRADE_STATUS=$?
test "$TC_UPGRADE_STATUS" -eq 0
```

**Nur bei Exitcode 0 weitergehen.** Bei erneutem Lauf einen neuen privaten Logdateinamen verwenden; das ursprüngliche `upgrade-before.json` bleibt unverändert. Die beiden Bestätigungsflags sind Zusagen des Betreibers, keine automatischen Beweise für ein funktionierendes Backup oder gestoppte Cronjobs. `--copy-documents` kann weggelassen werden, wenn die alten Lesewurzeln erhalten bleiben und eine spätere geprüfte Kopie vorgesehen ist. Statische URL-Sperren sind trotzdem erforderlich.

Der Vergleich ist auf Geschäftsdaten und referenzierte Dokumente begrenzt. Sessions/Cache/Jobs, technische Tabellen und unreferenzierte Dateien müssen im vollständigen Backup enthalten sein. Datenbank und Dokumente werden niemals automatisch auf einen älteren Zeitpunkt zurückgesetzt.

### Bei Abbruch oder Verbindungsfehler

- Wartung aktiv und Schreibprozesse gestoppt lassen. Inventar, Log, Backup und aktuelle Dateien erhalten.
- MySQL-DDL kann trotz Fehlermeldung bereits ganz oder teilweise ausgeführt sein. `migrations`-Tabelle und tatsächlich vorhandene Tabellen/Spalten abgleichen. Das Skript erkennt fehlende Migrationsprotokolle bei vorhandenen Zielobjekten und verweigert dann eine blinde Wiederholung.
- Keine Migrationseinträge pauschal nachtragen, Tabellen löschen oder `migrate:rollback` verwenden. Erst den konkreten Zustand anhand Migration und Backup fachlich prüfen. Einen neuen Evidenzordner anzulegen, um eine Inventardifferenz zu umgehen, ist keine Fehlerbehebung.
- Bei fehlgeschlagenen Dokumentkopien zuerst Lesewurzeln/Schreibrechte/Platz und Prüfsummen prüfen. Teilweise erzeugte private Dateien können einen Konflikt auslösen; Quellen werden vom Skript nicht gelöscht.
- Falls nach der ersten Freigabe bereits neue Geschäftsdaten entstanden sind, gehört ein erneuter Release in einen eigenen Backup-/Abnahmezyklus. Das alte Inventar darf dann nicht als gültige neue Basis ausgegeben werden.

## 4. Nachkontrolle und getrennte Freigabe

Nach erfolgreichem Skriptlauf den [bestätigten Metanet-Cache-Ablauf](DEPLOY_METANET.md#6-webfreigabe-und-smoke-test) verwenden. Unterschiedliche absolute SSH- und Webpfade können bei vorab erzeugten Konfigurations- und View-Caches zu HTTP 500 führen.

```bash
"$TC_PHP" artisan config:clear
"$TC_PHP" artisan view:clear
"$TC_PHP" artisan route:clear
"$TC_PHP" artisan deployment:check --probe-storage
# Bei freigegebenem Mailimport zusätzlich:
"$TC_PHP" artisan deployment:check --mail
```

Staging verwendet an dieser Stelle jeweils `deployment:check --staging`. Alle Exitcodes prüfen. OPcache/PHP-FPM gegebenenfalls über Plesk neu laden. Danach die HTTP-Smoke-Tests aus der Deployment-Anleitung durchführen: bestehende Logins, Freigaben, hashgleiche Altdownloads, direkte URL-Sperren, eigenes Testdokument, Formulare, Countdown und Mailversand. Der CLI-Erfolg allein öffnet die Anwendung nicht.

Erst nach diesen Prüfungen im betreuten Produktionsfenster Webzugang und erforderliche Cronjobs gezielt wieder freigeben. Diese Freigabe führt das Skript nicht aus. Evidenz und Sicherung bis zum Abschluss der Betriebsabnahme geschützt aufbewahren.
