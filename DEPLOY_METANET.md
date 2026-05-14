# TripControl auf METANET Hosting installieren

Diese Anleitung beschreibt die Initialinstallation von TripControl auf einem METANET Webhosting mit Plesk, SSH, Git und Composer.

## Voraussetzungen

- METANET Hosting mit Plesk-Zugang
- SSH-Zugang fuer den Systembenutzer aktiviert
- GitHub-Zugriff auf `git@github.com:thomasrietmann/travel.git`
- PHP 8.3 fuer die Domain
- Composer 2
- SQLite oder alternativ eine MySQL-Datenbank

TripControl ist aktuell fuer PHP 8.3+ konfiguriert. Auf METANET ist PHP 8.3 per SSH typischerweise unter diesem Pfad verfuegbar:

```bash
/opt/php83/bin/php
```

## 1. SSH in Plesk aktivieren

1. In Plesk einloggen.
2. `Websites & Domains` oeffnen.
3. Beim Systembenutzer `Webhosting-Zugang` bearbeiten.
4. SSH-Zugang auf `/bin/bash (chrooted)` setzen.
5. Speichern.

Danach per SSH verbinden:

```bash
ssh <benutzername>@<server> -p2121
```

Beispiel:

```bash
ssh exampleuser@server.host.ch -p2121
```

## 2. GitHub SSH-Key vorbereiten

Auf dem Hosting pruefen, ob bereits ein SSH-Key existiert:

```bash
ls -la ~/.ssh
```

Falls kein Key vorhanden ist:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "metanet-tripcontrol"
cat ~/.ssh/id_ed25519.pub
```

Den angezeigten Public Key in GitHub hinterlegen:

1. GitHub Repository `travel` oeffnen.
2. `Settings` -> `Deploy keys`.
3. `Add deploy key`.
4. Public Key einfuegen.
5. Fuer reines Deployment reicht Read-only. Fuer Push vom Server waere Write access noetig.

Verbindung testen:

```bash
ssh -T git@github.com
```

## 3. Projekt auf den Server klonen

In ein Verzeichnis ausserhalb des Webroots wechseln. Beispiel:

```bash
cd ~
git clone git@github.com:thomasrietmann/travel.git tripcontrol
cd tripcontrol
```

Wichtig: Die Domain darf spaeter nicht auf dieses Projektverzeichnis zeigen, sondern auf den Unterordner `public`.

## 4. PHP-Version pruefen

```bash
/opt/php83/bin/php -v
```

Falls diese Version nicht verfuegbar ist, in Plesk unter `PHP-Einstellungen` fuer die Domain PHP 8.3 aktivieren oder beim METANET Support pruefen lassen.

## 5. Composer Dependencies installieren

Im Projektverzeichnis:

```bash
/opt/php83/bin/php -d memory_limit=2048M /bin/composer install --no-dev --optimize-autoloader
```

Falls `/bin/composer` nicht funktioniert, pruefen:

```bash
which composer
which composer2
```

Dann entsprechend verwenden, zum Beispiel:

```bash
/opt/php83/bin/php -d memory_limit=2048M /bin/composer2 install --no-dev --optimize-autoloader
```

## 6. Environment-Datei erstellen

```bash
cp .env.example .env
nano .env
```

Empfohlene Werte fuer den Start mit SQLite:

```env
APP_NAME=TripControl
APP_ENV=production
APP_DEBUG=false
APP_URL=https://deine-domain.ch

DB_CONNECTION=sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

Danach den App-Key erzeugen:

```bash
/opt/php83/bin/php artisan key:generate
```

## 7. SQLite-Datenbank anlegen

```bash
touch database/database.sqlite
chmod 664 database/database.sqlite
```

Falls SQLite auf deinem Hosting nicht aktiv ist, nutze MySQL. Dann in Plesk eine Datenbank erstellen und `.env` anpassen:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<datenbankname>
DB_USERNAME=<datenbankuser>
DB_PASSWORD=<passwort>
```

## 8. Migrationen und Seed-Daten ausfuehren

Initialinstallation mit Demo-Daten:

```bash
/opt/php83/bin/php artisan migrate:fresh --seed --force
```

Der Demo-Login ist danach:

```text
E-Mail: demo@tripcontrol.test
Passwort: password
```

Nach dem ersten produktiven Login solltest du entweder das Passwort aendern oder einen eigenen User registrieren und den Demo-User entfernen.

## 9. Storage-Link fuer Dokument-Uploads

```bash
/opt/php83/bin/php artisan storage:link
```

Falls der Symlink auf dem Hosting nicht erlaubt ist, kann alternativ ein manuell gesetzter Symlink noetig sein:

```bash
ln -s ../storage/app/public public/storage
```

## 10. Rechte fuer Laravel-Verzeichnisse setzen

```bash
chmod -R ug+rw storage bootstrap/cache database
```

Falls es Schreibprobleme gibt, pruefe insbesondere:

```bash
ls -la storage
ls -la bootstrap/cache
ls -la database/database.sqlite
```

## 11. Document Root in Plesk setzen

In Plesk:

1. `Websites & Domains` oeffnen.
2. Domain auswaehlen.
3. `Hosting-Einstellungen` oeffnen.
4. Document Root auf den Laravel-Ordner `public` setzen.

Beispiel, wenn das Projekt unter `~/tripcontrol` liegt:

```text
tripcontrol/public
```

Das ist wichtig, weil Laravel nur den Ordner `public` direkt ausliefern darf. Dateien wie `.env`, `storage` oder `vendor` duerfen nicht oeffentlich erreichbar sein.

## 12. Laravel Caches fuer Produktion bauen

```bash
/opt/php83/bin/php artisan config:cache
/opt/php83/bin/php artisan route:cache
/opt/php83/bin/php artisan view:cache
```

Wenn du spaeter `.env`, Routes oder Views aenderst:

```bash
/opt/php83/bin/php artisan optimize:clear
/opt/php83/bin/php artisan config:cache
/opt/php83/bin/php artisan route:cache
/opt/php83/bin/php artisan view:cache
```

## 13. Deployment nach spaeteren Updates

Bei neuen Commits:

```bash
cd ~/tripcontrol
git pull origin main
/opt/php83/bin/php -d memory_limit=2048M /bin/composer install --no-dev --optimize-autoloader
/opt/php83/bin/php artisan migrate --force
/opt/php83/bin/php artisan optimize:clear
/opt/php83/bin/php artisan config:cache
/opt/php83/bin/php artisan route:cache
/opt/php83/bin/php artisan view:cache
```

## 14. Fehlerbehebung

### 500 Fehler

Laravel-Log pruefen:

```bash
tail -100 storage/logs/laravel.log
```

Haefige Ursachen:

- `APP_KEY` fehlt
- `.env` ist falsch konfiguriert
- `storage` oder `bootstrap/cache` ist nicht schreibbar
- Document Root zeigt nicht auf `public`
- PHP-Version ist nicht 8.3+

### Composer meldet falsche PHP-Version

Composer explizit mit PHP 8.3 starten:

```bash
/opt/php83/bin/php -d memory_limit=2048M /bin/composer install --no-dev --optimize-autoloader
```

### Uploads funktionieren nicht

Pruefen:

```bash
ls -la public/storage
ls -la storage/app/public
```

Danach ggf. erneut:

```bash
/opt/php83/bin/php artisan storage:link
```

### Domain zeigt Laravel-Dateiliste oder Root-Dateien

Der Document Root ist falsch. Er muss auf `tripcontrol/public` zeigen, nicht auf `tripcontrol`.

## Quellen

- METANET Support: SSH-Zugriff, SSH-Port `2121`, Composer und PHP-Pfade
- METANET Support: PHP-Version in Plesk konfigurieren
- Laravel Deployment-Best-Practice: Webserver Document Root muss auf `public` zeigen
