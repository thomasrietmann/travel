# Prüfbelege zum TripControl Review

`results.json` enthält Ergebnisse des unveränderten Anwendungsstands `d666874`, geprüft am 24. Oktober 2026. Die Ergebnisse dokumentieren sowohl funktionierende Zugriffsgrenzen als auch reproduzierte Fehler; ein Exit-Code 0 des Prüfskripts bedeutet nicht, dass die Anwendung sicher ist.

`review.php` arbeitet nur innerhalb einer temporären Anwendungskopie. Es erstellt synthetische Benutzer und eine Datenbank im Arbeitsspeicher, simuliert Schreibfehler und IMAP-Header und blockiert externe Laravel-HTTP-Aufrufe. Dieses Skript nicht in eine produktive Anwendung kopieren oder dort ausführen.

Der Wrapper erstellt die benötigte Isolation und liest keine produktive `.env`, Datenbank oder Dokumentablage:

```bash
python3 outputs/security-review-2026-10-24/reproduce.py \
  --php /Users/thomasrietmann/Documents/LEG/.tools/php \
  --vendor /Users/thomasrietmann/Documents/LEG/vendor
```

Für diese Prüfung wurden PHP 8.4.1 und Laravel 13.37.0 aus LEG verwendet, weil TripControls Lockfile und installierte Dependencies lokal fehlen. Andere Versionen können andere Ergebnisse liefern. Der Ablauf installiert keine Packages und ändert weder das angegebene Vendor-Verzeichnis noch Anwendungscode. Er benötigt unter anderem Mockery aus einer lokalen Entwicklungsinstallation.

Die Testkopie bleibt zur Inspektion im ausgegebenen temporären Verzeichnis. `results.json` im Repository wird bei Wiederholung nicht automatisch überschrieben. Die heutigen Fehlerreproduktionen sollten bei ihrer Behebung in reguläre Featuretests mit der jeweils gewünschten Gegenbedingung überführt werden, etwa: Originaldatei bleibt bei fehlgeschlagenem Ersatz erhalten.

Der vollständige Befund- und Deploymentplan steht in `SECURITY_REVIEW_2026-10-24.md` im Projektstamm.
