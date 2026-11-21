# TripControl – Projektinstruktionen

## Laravel-Caches auf Metanet

Die bestehende Metanet-Installation verwendet unterschiedliche Pfade für SSH und Web. SSH sieht das Projekt unter `/home/httpd/vhosts/aufbollen.ch/travel.git`, die Webausführung innerhalb des Chroots unter `/travel.git`. Konfigurations- und View-Caches können absolute SSH-Pfade speichern, die in der Webausführung zu HTTP 500 führen.

Am 21.11.2026 bestätigte der Betreiber, dass die Anwendung nach dem gezielten Leeren der Konfigurations-, View- und Route-Caches wieder funktioniert. Diesen Ablauf für künftige Deployment-Anweisungen verwenden. Nach Änderungen an `.env` oder Konfigurationsdateien ebenfalls `config:clear` mit dem PHP der Installation ausführen.

Im bestehenden Projektordner mit `artisan` ausführen. Der zuletzt verwendete PHP-Pfad ist `/opt/php83/bin/php`.

```bash
/opt/php83/bin/php artisan config:clear &&
/opt/php83/bin/php artisan view:clear &&
/opt/php83/bin/php artisan route:clear
```

Anschliessend die Login-Seite, `/up` und eine bestehende Reise über die Webanwendung prüfen. Bei HTTP 500 die aktuellen Laravel-Fehler anhand ihrer Zeitstempel auswerten.

Cache-Optimierungen mit `config:cache`, `view:cache`, `route:cache` oder `optimize` setzen eine für CLI und Web geprüfte Pfadzuordnung und einen erfolgreichen Webtest voraus. Der bestätigte Standardablauf auf dieser Installation verwendet die oben gezeigten `clear`-Befehle.

Die zugehörigen Betriebsanleitungen stehen in `DEPLOY_METANET.md`, `UPGRADE_METANET.md` und `STAGING_ABNAHME_METANET.md`.
