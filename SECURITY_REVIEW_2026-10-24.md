# TripControl Sicherheitsprüfung und Refactoringplan

> Historischer Bestandsaudit vor den Korrekturen. Aktueller Umsetzungs- und Teststand: [SECURITY_IMPLEMENTATION.md](SECURITY_IMPLEMENTATION.md). Die folgenden Befunde/Zeilenangaben beziehen sich auf den damaligen Code.


Stand: 24. Oktober 2026. Geprüfter Commit: `d666874` auf `main`. Architekturvergleich: lokales Projekt `/Users/thomasrietmann/Documents/LEG`.

TripControl kann auf seiner bestehenden Laravel-/Blade-Architektur weiterentwickelt werden. Vor einem grösseren Refactoring sind jedoch die E-Mail-Zuordnung und der Lebenszyklus der Dokumente zu korrigieren. Dort bestehen reproduzierte Risiken für fremde Daten und für Datenverlust. Die grundlegenden Policies funktionieren in den geprüften Zugriffsszenarien.

Diese Prüfung ändert keine Anwendungsdateien und führt kein Deployment aus. Die nachstehenden Änderungen sind ein Umsetzungsplan. Eine Freigabe für die konkrete Metanet-Installation setzt die Prüfung des dort tatsächlich installierten Codes, der Abhängigkeiten, Konfiguration und Bestandsdaten voraus.

## Umfang und Beweislage

Geprüft wurden Anwendungscode, sämtliche Routen, Requests, Policies, Models und Services, 25 Blade-Views, 15 Migrationen, Factories/Seeder, Konfiguration, Cron-Einstieg, Composer-Konfiguration und Betriebsanleitungen. Zum Vergleich wurden LEG-Architekturdokumentation sowie konkrete Implementierungen für Middleware, Login, Autorisierung, Logging, Suche, Tests und Build-Konfiguration gelesen.

Lokale Validierung:

- 89 PHP-Dateien beziehungsweise PHP-Einstiegspunkte: Syntaxprüfung ohne Fehler mit PHP 8.4.1.
- `composer validate --no-check-publish`: gültige Konfiguration; Hinweis auf fehlende Lizenzangabe.
- Alle 25 Blade-Views in einer isolierten Kopie kompiliert; keine Kompilierungsfehler.
- Zehn angemeldete Seiten mit künstlichen Daten aufgerufen: jeweils HTTP 200.
- Fremder Benutzer auf Reise und Dokument: HTTP 403; freigegebener Benutzer auf Dokument: HTTP 200; normaler Benutzer auf Adminbereich: HTTP 403; Gast auf Dokument: Login-Redirect.
- Schreibfehler, Alias-Kollision, Absenderzuordnung, Importzustände und Dateilöschung mit künstlichen Daten beziehungsweise gezielten Fehler-Simulationen geprüft.

TripControl enthält weder `vendor/` noch `composer.lock`, `tests/` oder `phpunit.xml`. Die dynamischen Prüfungen verwendeten deshalb ausschliesslich in einer temporären Kopie den vorhandenen Laravel-13.37.0-Abhängigkeitsstand von LEG und eine SQLite-Datenbank im Arbeitsspeicher. Das belegt die beschriebenen Codepfade, ersetzt aber weder Tests gegen TripControls Produktions-Lockfile noch einen vollständigen Integrationstest. CSRF wird im Laravel-Testmodus nicht als Browser-Ende-zu-Ende-Schutz geprüft. Dompdf, echtes IMAP, echte OpenAI-Aufrufe, produktives MySQL/SQLite und Plesk wurden nicht ausgeführt oder verändert.

Die maschinenlesbaren Ergebnisse und der isolierte Prüfaufbau liegen unter `outputs/security-review-2026-10-24/`. Es wurden keine echten Benutzerdaten oder Dokumente verwendet.

Bereits sinnvolle Kontrollen im Code: Laravel-Webmiddleware mit CSRF-Schutz ohne konfigurierte Ausnahmen, escaped Blade-Ausgaben, Eloquent-/Query-Builder-Zugriffe ohne benutzergesteuerte Raw-SQL-Fragmente, explizite Auswahl der Registrierungsfelder, private neue Dokumentuploads und Policies vor Downloads. Der Cron-Wrapper maskiert Shellargumente und hält seine eigene Sperre. Diese Kontrollen sollten beim Umbau erhalten bleiben; sie sind keine pauschale Sicherheitszusage.

Prioritäten: **P1** = vor beziehungsweise im ersten Sicherheitsrelease behandeln; **P2** = im anschliessenden Härtungs- und Refactoringrelease. Bedingte Produktionsbefunde sind ausdrücklich gekennzeichnet.

## Befunde mit hoher Priorität

### F01 P1 Registrierung kann eine bestehende Mailzuordnung übernehmen

**Belege:** `app/Http/Controllers/Auth/RegisteredUserController.php:24`, `app/Http/Requests/UserEmailAliasRequest.php:22`, `app/Http/Controllers/SettingsController.php:24`, `app/Services/IncomingMailImporter.php:525`.

Die Registrierung prüft E-Mail-Eindeutigkeit nur in `users`. Aliase liegen in einer anderen Tabelle. Im Import wird zuerst `users.email`, erst danach die Aliastabelle ausgewertet. Ein neuer Benutzer kann deshalb eine Adresse registrieren, die bereits als Alias eines anderen Benutzers existiert. Ein Besitznachweis findet weder bei Registrierung noch beim Anlegen eines Alias statt.

**Reproduziert:** Ein Alias gehörte Benutzer A. Die Registrierung derselben Adresse für Benutzer B gelang mit leerem `email_verified_at`. Anschliessend ordnete der Import diese Adresse B zu. Künftige weitergeleitete Buchungsunterlagen können so beim falschen Konto landen. Auch die vorgängige Reservierung fremder, noch nicht registrierter Mailadressen ist möglich.

**Korrektur:** Besitznachweis vor Aktivierung neuer Mailidentitäten; gemeinsame normalisierte Eindeutigkeitsregel für Login-Adressen und Aliase; Import nur über freigegebene Identitäten. Bestehende Kollisionen zunächst inventarisieren und gezielt auflösen. Bestehende Benutzer nicht pauschal durch eine neue `verified`-Middleware aussperren. Die Regeln für neue Identitäten und den Übergang bestehender Konten müssen getrennt eingeführt werden.

### F02 P1 Mailimport vertraut auf Reply-To als Benutzeridentität

**Belege:** `app/Services/IncomingMailImporter.php:109`, `:525`, `:661`.

`senderEmails()` sammelt `From`, `Sender` und `Reply-To`; die erste bekannte Adresse bestimmt das Benutzerkonto. `Reply-To` ist kein Identitätsnachweis. Im Anwendungscode gibt es keine Prüfung einer vertrauenswürdigen Absenderauthentisierung durch den empfangenden Mailserver.

**Reproduziert:** Eine unbekannte From-Adresse mit der Adresse eines bestehenden Benutzers in Reply-To wird diesem Benutzer zugeordnet. Dadurch können fremde Reisen mit importierten Inhalten verändert und API-/Speicherressourcen ausgelöst werden. Dieser Befund allein belegt keinen Lesezugriff auf das Zielkonto; der Datenabfluss aus F01 ist ein eigener Fehlerpfad.

**Korrektur:** Reply-To aus der Autorisierung entfernen. Eine geprüfte Absenderidentität und vertrauenswürdige, durch Metanet gesetzte Authentisierungsresultate verwenden oder persönliche, widerrufbare Importadressen einführen. Vom Absender selbst mitgelieferte `Authentication-Results` dürfen nicht ungeprüft als Nachweis gelten. Unsichere Nachrichten nachvollziehbar zurückstellen, statt sie stillschweigend einem Konto zuzuweisen. Die konkrete Metanet-Weiterleitungskette ist vor der Umsetzung zu prüfen.

### F03 P1 Dokumentmigration kann die einzige Datei löschen

**Belege:** `routes/console.php:16`, insbesondere `:25–38`; `config/filesystems.php:11`.

`documents:migrate-private` prüft den Rückgabewert von `put()` nicht und löscht danach die Quelle. Die Disks haben `throw=false`. Fehlgeschlagene Schreibzugriffe können deshalb ohne Exception zurückkehren. Laravel dokumentiert diese Rückgabewerte ausdrücklich in der [Filesystem-Dokumentation](https://laravel.com/framework/docs/13.x/filesystem#failed-writes).

**Reproduziert:** Bei simuliertem `put() === false` wurde die öffentliche Quelldatei gelöscht. Der Befehl meldete ein migriertes Dokument und endete mit Exit-Code 0.

**Korrektur:** Trockenlauf, vollständiges Inventar, Kopieren, Grössen-/Hashvergleich und erst danach ein gesonderter Bereinigungsschritt. Fehler müssen die Quelle erhalten und einen Fehlerstatus liefern. Alle konfigurierten Fallback-Wurzeln berücksichtigen. Den heutigen Befehl nicht für eine Bestandsmigration verwenden.

### F04 P1 Dokumentersatz löscht das Original vor erfolgreichem Upload

**Belege:** `app/Http/Controllers/DocumentController.php:107`, `:45`; `app/Http/Controllers/BookingController.php:75`.

Beim Ersetzen wird zuerst die alte Datei gelöscht. Erst danach wird die neue gespeichert und der Datenbankeintrag geändert. Auch neue manuelle Uploads prüfen den Erfolg von `store()` nicht zuverlässig.

**Reproduziert:** Bei einem simulierten Speicherfehler wurde das Original gelöscht, die Anfrage als Redirect abgeschlossen und `file_path` als `"0"` gespeichert. Der Benutzer erhält keinen verlässlichen Hinweis auf den Verlust.

**Korrektur:** Neue Datei unter neuem Namen schreiben und prüfen, den Datensatz erfolgreich umstellen und das Original erst danach kontrolliert bereinigen. Bei DB-Fehlern die neue Datei kompensierend entfernen. Eine Datenbanktransaktion allein rollt Dateisystemoperationen nicht zurück. Alle Uploadwege müssen denselben Ablauf nutzen.

### F05 P1 Öffentliche Altdokumente können Policies umgehen

**Belege:** `DEPLOY_METANET.md:178`, `config/filesystems.php:22`, `app/Http/Controllers/DocumentController.php:144`, `routes/console.php:21`.

Die Betriebsanleitung legt `public/storage` an. Liegen dort weiterhin alte Dokumente und wird der Symlink statisch ausgeliefert, ist ein bekannter Dateipfad ohne Laravel-Authentifizierung abrufbar. Die Policy im Downloadcontroller schützt diesen direkten Webserverpfad nicht. Ob diese Kombination produktiv noch besteht, wurde nicht auf Metanet geprüft.

**Reproduzierter Zusatzfehler:** Existiert bereits eine private Kopie, überspringt der Migrationsbefehl den Datensatz und lässt eine vorhandene öffentliche Kopie liegen. Ein scheinbar abgeschlossener Umzug beseitigt die öffentliche Exposition deshalb nicht zwingend.

**Korrektur:** Öffentliche Kopien inventarisieren; Zugriff über den autorisierten Downloadpfad für alle alten Pfade verifizieren; statischen Zugriff auf Dokumentverzeichnisse in der tatsächlichen Apache-/nginx-Konfiguration sperren. Bestandsdateien zunächst erhalten beziehungsweise in einen nicht öffentlichen Quarantänebereich sichern. Andere öffentliche Assets dürfen dabei nicht ausfallen.

### F06 P1 Keine reproduzierbare und ausreichend abgesicherte Releasebasis

**Belege:** `composer.json:5`, `DEPLOY_METANET.md:99`, `:238`, `README.md:45`, versionierte Datei `database/database.sqlite`, fehlendes Testverzeichnis.

Ohne versioniertes Lockfile ist nicht bestimmt, welche Laravel-, Dompdf- und transitiven Versionen ein neuer Build installiert. Die Betriebsanleitung beschreibt ein möglicherweise nur auf dem Server vorhandenes Lockfile. Ohne dessen Inhalt ist auch keine belastbare Schwachstellenprüfung der produktiven Abhängigkeiten möglich. Composer empfiehlt für Anwendungen das versionierte Lockfile und Installation der festgelegten Versionen ([Composer-Dokumentation](https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies)).

Der Updateablauf besteht im Wesentlichen aus `git pull`, Installation und Migrationen. Ein abgestimmtes Backup von Datenbank und Dokumenten, Stoppen paralleler Importe, Restore-Prüfung und Rollbackablauf fehlen. Die Anleitung zur Initialinstallation enthält zudem `migrate:fresh --seed --force`; sie ist ausdrücklich für Erstinstallation beschriftet, darf aber keinesfalls auf den heutigen Bestand angewendet werden.

Die leere SQLite-Datei ist versioniert. Ein späteres Entfernen dieser Datei aus Git oder ein Austausch des Releaseverzeichnisses muss so vorbereitet werden, dass eine tatsächlich dort betriebene Datenbank erhalten bleibt. Ein normales `git pull` überschreibt nicht automatisch jede lokal geänderte Datenbank; riskant sind insbesondere erzwungene Checkouts und Deployments, die das komplette Verzeichnis ersetzen.

**Korrektur:** Produktionsstand und Lockfile sichern, zunächst denselben Abhängigkeitsstand reproduzieren, Produktions-PHP als Plattformziel festlegen, `composer audit` und Regressionstests darauf ausführen. Framework-Upgrade und Refactoring getrennt behandeln. Persistente Daten explizit vom Releasepaket ausschliessen und den nachstehenden Ablauf etablieren.

### F07 P1 Demo-Administrator mit bekanntem Passwort im Seeder

**Belege:** `database/seeders/DatabaseSeeder.php:24`, `resources/views/auth/login.blade.php:7`, `DEPLOY_METANET.md:161`.

Der Seeder erzeugt zusätzlich zum normalen Demobenutzer einen Administrator `admin@tripcontrol.test` mit dem Passwort `password`. Die Anleitung weist nur den normalen Demo-Login aus. Falls dieser Seeder produktiv ausgeführt und das Administratorkonto nicht abgesichert wurde, besteht ein unmittelbar verwendbarer Adminzugang. Der aktuelle produktive Kontostand ist unbekannt.

**Korrektur:** Dieses Konto gezielt auf Metanet prüfen und gegebenenfalls sperren beziehungsweise sein Passwort sicher ändern. Demo-Daten technisch auf lokale/Testumgebungen begrenzen; den Demo-Hinweis aus der produktiven Loginansicht entfernen. Kein pauschales Löschen von Benutzern mit möglichen Reisebeziehungen.

## Weitere Sicherheits- und Betriebsbefunde

### F08 P2 Login, Registrierung und kostenpflichtige Importe haben keine Anwendungslimits

**Belege:** `routes/auth.php:7`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php:18`, `routes/web.php:28`, `:44`, `app/Http/Requests/BookingImportRequest.php:18`.

Es gibt weder Login-RateLimiter noch Throttle-Middleware für die genannten Aktionen. Acht fehlerhafte Logins wurden im Test unverändert verarbeitet. Offene Registrierung und unmittelbar nutzbare AI-Uploads ermöglichen auch Missbrauch von Speicher und API-Budget. Zehn Dateien zu je bis zu 15 MB werden synchron importiert; mehrere externe Aufrufe mit jeweils bis zu 90 Sekunden Timeout können einen Webprozess lange binden.

**Korrektur:** Loginbegrenzung nach LEG-Vorbild, kombinierte Benutzer-/IP-Limits, Registrierungsbegrenzung und Importkontingente. Zusätzlich Anzahl, Gesamtgrösse und Laufzeit begrenzen. Grössere Stapel über einen begrenzten, per Cron gestarteten Hintergrundlauf bearbeiten. Dafür ist kein dauerhaft laufender Worker erforderlich.

### F09 P2 Importzustände und Sperren sind nicht über alle Einstiege konsistent

**Belege:** `cron-mail-import.php:21`, `app/Http/Controllers/Admin/MailImportController.php:29`, `app/Services/IncomingMailImporter.php:89`, `:183`, `:676`, `routes/console.php:61`.

Nur der Cron-Wrapper hält eine Dateisperre. Adminaktion und direkter Artisan-Aufruf umgehen sie. Ein vorhandener `processing`-Eintrag wird sofort als abgebrochen behandelt; ein konkurrierender Lauf kann damit einen noch aktiven Import als fehlgeschlagen markieren. Bereits fehlgeschlagene Importe werden bei Wiederholung ignoriert. Fehler werden zudem normalerweise als gelesen markiert; ein Teilfehler führt im Artisan-Befehl nicht zu einem entsprechenden Fehler-Exitcode.

**Reproduziert:** `processing` wurde beim zweiten Aufruf zu `failed`; der nächste Versuch lieferte `ignored`. Für diese Prüfung wurden IMAP-Headerfunktionen lokal simuliert, keine echte Mailbox verwendet.

**Korrektur:** Gemeinsame Sperre beziehungsweise atomare Übernahme im Service, Statusübergänge mit Zeitgrenze, begrenzte Wiederholungen und manuelle Wiederaufnahme. Die eindeutige Message-ID hilft bereits gegen manche Doppelanlagen, reicht aber nicht als Zustandsmodell. Bei UID-Ersatzschlüsseln Mailboxidentität und UIDVALIDITY berücksichtigen. Zustellfehler dürfen eine spätere Nachricht nicht dauerhaft blockieren. Fehlgeschlagene Läufe für Cron erkennbar machen.

### F10 P2 Dokumentkopien und verwaiste Dateien bleiben bestehen

**Belege:** `app/Services/DocumentStorage.php:12`, `app/Http/Controllers/DocumentController.php:157`, `app/Http/Controllers/TripController.php:66`, `database/migrations/2026_05_14_000003_create_documents_table.php:13`.

`DocumentStorage` schreibt in jede erreichbare private Wurzel, auch nach einem erfolgreichen ersten Schreibzugriff. Der Löschpfad entfernt nur den ersten gefundenen Treffer. Bei verschiedenen physischen Wurzeln bleiben dadurch Kopien zurück. Bei Reiselöschung entfernt die Datenbankkaskade die Dokumentzeilen, aber keine Dateien. Auch beim Rollback eines Imports werden bereits geschriebene Dateien nicht kompensiert.

**Reproduziert:** Nach Dokumentlöschung blieb eine Kopie in einer separaten Fallback-Wurzel; nach Reiselöschung blieb die Datei bei bereits gelöschtem Dokumentdatensatz.

**Korrektur:** Ein kanonischer Schreibort, gemeinsame Leseauflösung für Altpfade und ein expliziter Bereinigungsablauf. Chroot-Aliase können dieselbe physische Datei bezeichnen; vor Bereinigung diese Gleichheit prüfen. Bereits verwaiste Dateien nur inventarisieren und mit Aufbewahrungs-/Wiederherstellungsbedarf abgleichen, nicht blind löschen.

### F11 P2 Mailanhänge werden anhand behaupteter Metadaten akzeptiert

**Belege:** `app/Services/IncomingMailImporter.php:556`, `:622`, `:319`, `:362`, `:599`.

Eine erlaubte Erweiterung **oder** ein erlaubter MIME-Header genügt. Inhaltsprüfung, begrenzte MIME-Verschachtelung und ein Gesamtlimit fehlen im Mailparser. Der vollständige Inhalt wird vor der Prüfung geladen. Die Begrenzung auf fünf AI-Anhänge begrenzt weder das Einlesen noch das spätere Speichern aller Anhänge.

**Reproduziert:** Sowohl `example.php` mit behauptetem PDF-MIME-Typ als auch `example.pdf` mit `text/plain` wurden als unterstützt akzeptiert. Das beweist eine Validierungslücke, keine ausführbare PHP-Datei im privaten Storage und keine bestätigte Remote-Code-Ausführung.

Die PDF-Erzeugung deaktiviert externe Ressourcen bereits sinnvoll. Die Regex-Bereinigung ist dennoch keine vollständige HTML-Sanitisierung; komplexes fremdes HTML belastet den Renderer.

**Korrektur:** Nachrichtengrösse vor Abruf prüfen, Anzahl/Grösse/Tiefe begrenzen, Dateiinhalte erkennen und sichere Erweiterungen ableiten. PDF-Erzeugung in einen eigenen Service mit einer kontrollierten Vorlage überführen; bevorzugt escaped Text oder eine geprüfte HTML-Allowlist. Einen konkreten Dompdf-CVE-Befund kann erst der installierte Versionsstand liefern.

### F12 P2 OpenAI-Datenhaltung, Fehlerausgaben und Protokollierung benötigen klare Grenzen

**Belege:** `app/Services/BookingAiExtractor.php:47`, `:65`, `:77`; `app/Services/MailAiExtractor.php:50`, `:79`; `app/Services/TripSummaryGenerator.php:20`, `:94`; `app/Http/Controllers/BookingImportController.php:61`; `app/Services/MailImportLogger.php:24`.

Alle Responses-Aufrufe lassen `store` ungesetzt. Datei-DELETE-Antworten werden nicht auf Erfolg geprüft; nur geworfene Exceptions werden abgefangen. HTTP-Fehler können somit unbemerkte externe Dateikopien hinterlassen. Die offiziellen [OpenAI-Datenkontrollen](https://developers.openai.com/api/docs/guides/your-data) beschreiben die standardmässige Responses-Speicherung sowie gesonderte Dateilöschung/Ablaufzeiten. `store=false` begrenzt den Anwendungszustand, ist allein aber keine Zusicherung vollständiger Zero Data Retention.

Die Summary übermittelt unter anderem Notizen und Buchungsreferenzen. Rohantworten bei API-Fehlern gelangen in Exceptions, teilweise zur Benutzeransicht und in Importlogs. Der eigene Logger rotiert nicht und liest für die letzten 50 Zeilen die gesamte Datei. Ein Auditprotokoll für Freigaben, Löschungen und sicherheitsrelevante Änderungen fehlt.

**Korrektur:** `store=false` explizit setzen, erforderlichen Kontext reduzieren, Datei-Cleanup samt Statusprüfung und Wiederholung vorsehen, gegebenenfalls Ablaufzeit konfigurieren. Technische Fehlercodes statt externer Rohantworten anzeigen. Logs mit Rotation, begrenztem Lesen und erlaubten Kontextfeldern; separater minimaler Audit-Logger nach LEG-Vorbild. Beim normalen manuellen Upload wird die Datei selbst nicht automatisch an OpenAI gesendet; der Buchungs-/Mailimport sendet Inhalte, die Summary Metadaten und Texte.

### F13 P2 Browserhärtung fehlt im Code und Countdown-Links sind nicht widerrufbar

**Belege:** `bootstrap/app.php:18`, `config/session.php:16`, `resources/views/layouts/app.blade.php:14`, `resources/views/layouts/public.blade.php:17`, `app/Models/User.php:65`, `app/Http/Controllers/CountdownController.php:22`.

Es fehlen anwendungsseitige Security-Header, eine explizite Hostbegrenzung sowie ein dokumentierter HTTPS-/Proxy-Abgleich. `SESSION_SECURE_COOKIE` ist nicht ausdrücklich im Produktionsbeispiel gesetzt. Diese Kontrollen können teilweise in Plesk existieren; der tatsächliche HTTP-Zustand bleibt zu prüfen. HttpOnly, SameSite und Sessionregeneration beim Login sind bereits vorhanden.

Beide Layouts laden unversioniertes Tailwind-JavaScript vom CDN, auch auf der Loginseite. Tailwind bezeichnet den [Play CDN ausdrücklich als Entwicklungswerkzeug](https://tailwindcss.com/docs/installation/play-cdn). Ein lokaler Build reduziert Laufzeitabhängigkeiten und erlaubt eine strengere CSP. Eine CSP aus LEG darf erst nach Anpassung der vorhandenen Inline-Scripts und Eventhandler aktiviert werden.

Der 48 Zeichen lange Zufallstoken für den Countdown ist ausreichend schwer zu erraten, hat aber keinen Widerrufs-/Rotationsweg. Er entsteht bereits beim Öffnen der Einstellungen und zeigt auch künftig hinzugefügte Reisen, geteilte Reisen und Geburtstage mit Namen und Alter. Im Test wurden diese Daten ohne Login wie vorgesehen angezeigt; `Referrer-Policy` und CSP fehlten, Cache-Control war `no-cache, private` statt `no-store`.

**Korrektur:** Statische Assets lokal bauen, Header und HTTPS mit Plesk abstimmen, sensible Antworten mit No-Store versehen. Öffentliche Freigaben bewusst aktivierbar, widerrufbar und inhaltlich beschränkbar machen. Bestehende veröffentlichte Links nur mit bewusstem Übergang ändern.

### F14 P2 Leere optionale Pfade überschreiben die vorgesehenen Standardwerte

**Belege:** `.env.example:13`, `:28–33`, `config/view.php:4`, `config/filesystems.php:9`, `config/logging.php:13`.

Die Beispieldatei setzt optionale Pfadvariablen auf leere Strings. `env('NAME', $default)` liefert dann den leeren String und nicht den Standardwert. Ein Neuaufbau anhand dieser Vorlage kann deshalb anders funktionieren als die bestehende Installation mit korrigierten absoluten Pfaden.

**Reproduziert:** Mit den leeren Variablen ergaben sich `view.paths=[]`, ein leerer View-Cachepfad und ein leerer privater Storage-Root.

**Korrektur:** Unbenötigte optionale Werte in der Vorlage auskommentieren oder leere Werte ausdrücklich auf Standardpfade zurückführen. Nicht leere produktive Pfade unverändert unterstützen; Auflösung sowohl unter Web-PHP als auch im chrooted Cron prüfen.

## Refactoring nach dem LEG-Vorbild

LEG ist ein gutes Vorbild für einen schlanken Laravel-Monolithen auf Shared Hosting: kleine Controller, Form Requests, Policies, fachlich benannte Services, lokale Assets und gezielte Tests. TripControl braucht dafür keinen Wechsel zu SPA, Microservices, Redis oder permanenten Queue-Workern.

| Bereich | Änderung in TripControl | Nutzen und Kompatibilität |
| --- | --- | --- |
| Dokumente | Bestehenden `DocumentStorage` um geprüften Schreib-, Lese-, Ersatz- und Bereinigungsablauf erweitern; alle Controller/Importe darüber führen | Entfernt aktuell abweichende Dateiregeln; `file_path` und Altpfade bleiben lesbar |
| Mailimport | Den 744-zeiligen Importer schrittweise in Mailboxzugriff/MIME-Verarbeitung, Zuordnung und PDF-Erzeugung aufteilen; Orchestrierung klein halten | Fehlerpfade separat prüfbar; Einstieg `mail:import` und Crondatei bleiben erhalten |
| Buchungen | Gemeinsame Normalisierung und Validierungsregeln für Formular-, Datei- und Mailimport | Regeln sind heute in `BookingRequest`, `BookingImportController` und `IncomingMailImporter` dupliziert |
| OpenAI | Gemeinsamer Client für Timeout, Fehlerabbildung, Antwortauswertung und Datei-Cleanup; fachliche Prompts getrennt | Entfernt Dreifachimplementierung, ohne Modell oder Nutzungsverhalten stillschweigend zu wechseln |
| Autorisierung | Berechtigungen in passenden Form Requests vor Validierung ausdrücken; bestehende Policies weiterverwenden; gemeinsamer `visibleTo(User)`-Scope | Heute sind Requests pauschal autorisiert, Controller prüfen anschliessend; diese Controllerchecks verhindern bereits viele IDOR-Fälle |
| Listen | Sichtbarkeit und Abfragen bündeln; Pagination beziehungsweise begrenzte Abrufe; unnötiges Eager Loading entfernen | Dashboard lädt beispielsweise Dokumente, obwohl es sie nicht anzeigt; Countdown lädt ungenutzte Buchungen/Tasks |
| Status und Darstellung | Bei Nutzen string-backed Enums wie in LEG, gemeinsame kleine Blade-Komponenten, JavaScript in lokale Dateien | Bestehende DB-Werte und Formwerte beibehalten; vor Enum-Casts unbekannte Bestandswerte inventarisieren |
| Betrieb | Artisan-Closures in benannte Commands verschieben; rotierendes Logging und Audit-Service | Stabiler CLI-Vertrag, bessere Fehlercodes und separat testbare Befehle |

Die eigenen PDF-Fallbackfunktionen können erst entfernt werden, wenn die Dompdf-Erzeugung auf Metanet zuverlässig getestet ist. Die Storage-Fallbacks sind ebenfalls zunächst eine Kompatibilitätsschicht. Ihre ersatzlose Entfernung wäre für bestehende Dokumente riskant.

Beim Admin-Command ebenfalls LEG als Vorbild nutzen: Passwort verdeckt und mit Bestätigung abfragen, statt `--password` in Shellhistorie oder Prozessargumenten zu erlauben. Bestehende Konten nicht beiläufig durch `updateOrCreate` zum Administrator hochstufen oder deren Passwort überschreiben. Das ist eine Härtung des privilegierten Betreiberwerkzeugs, kein nachgewiesener Zugriff durch normale Webbenutzer.

Weitere fachliche Vereinfachungen: Geldsummen ohne Währung nicht verwenden; Status `partially_paid` derzeit nicht als exakt bezahlten Teilbetrag interpretieren. Die App speichert keinen Teilzahlungsbetrag. Stornierte Buchungen, fällige Aufgaben am heutigen Tag und vergangene Zahlungsfristen benötigen zuerst festgehaltene fachliche Erwartungen. Bestehende Werte nicht im Rahmen eines strukturellen Refactorings neu interpretieren. Betragsobergrenzen an `decimal(10,2)` ausrichten und Freitext-/AI-Felder begrenzen, damit SQLite-Entwicklung und strenges MySQL nicht unterschiedlich scheitern.

## Kompatibilitätsregeln für bestehende Benutzer

1. `APP_KEY`, Benutzer-IDs, Passwort-Hashes, Beziehungen, Dateipfade und vorhandene Freigaben zunächst erhalten.
2. Den LEG-Hashalgorithmus nicht direkt übernehmen: Der Test mit Argon2id und einem bestehenden bcrypt-Hash warf bei aktivierter Algorithmusprüfung eine RuntimeException. bcrypt zunächst beibehalten; ein späterer Wechsel braucht eine getestete Migration beim erfolgreichen Login.
3. Session-Driver, Cookie-Name und Sessionverschlüsselung nicht gleichzeitig mit dem Refactoring umstellen. Ein solcher Wechsel kann bestehende Sitzungen ungültig machen und benötigt einen bewussten Übergang.
4. SQLite nicht allein zur Angleichung an LEG durch MariaDB ersetzen. Das wäre eine eigene Datenmigration mit zusätzlichem Risiko.
5. Neue Tabellen/Spalten zunächst additiv und gegebenenfalls nullable einführen. Alte Migrationen nicht umschreiben; bestehende Tabellen nicht neu erzeugen.
6. Geteilte Benutzer können heute Reisen bearbeiten sowie Buchungen, Aufgaben und Dokumente ändern/löschen; nur Besitzer dürfen Reisen löschen oder Freigaben verwalten. Diese Semantik beim Refactoring bewahren. Eine neue Leserechterolle wäre eine separate Produktänderung.

## Reihenfolge der Umsetzung

**Release 1: Bestand absichern.** Produktionsstand inventarisieren, reproduzierbare Abhängigkeiten und zentrale Regressionstests herstellen; F01–F05 beheben, Demo-Admin prüfen und Login-/Importlimits ergänzen. Dokumentmigration zunächst nur inventarisierend und kopierend gestalten. Neue E-Mail-Identitäten sicher aktivieren, ohne bestehende Konten pauschal zu sperren.

**Release 2: Struktur und Härtung.** Gemeinsame Dokument-/Buchungsservices, Importzustände und Sperren, OpenAI-Cleanup, Logging/Audit, lokal gebaute Assets und passende Header. Jede Extraktion mit Verhaltenstests absichern. Wenn Wartezeiten tatsächlich problematisch sind, einen begrenzten Cron-Jobablauf ergänzen.

**Release 3: Kontrollierte Bereinigung.** Verifizierte private Dokumentmigration abschliessen; öffentliche/verwaiste Kopien nach Inventar, Backup und Aufbewahrungsentscheidung bereinigen. Fallbacks erst nach nachgewiesener Nutzlosigkeit abbauen. Paket- oder PHP-Upgrades separat testen.

## Ablauf für ein sicheres Metanet-Deployment

1. **Iststand festhalten:** deployter Commit beziehungsweise Source-Diff, produktives Lockfile und Paketversionen, Web-/CLI-PHP und Extensions, Datenbanktyp/-pfad, Document Root, Storage-Wurzeln, Cron-Konfiguration und Schreibrechte. Secrets nicht in diesen Bericht oder Git übernehmen. Bei PHP-Wechsel insbesondere die Verfügbarkeit der IMAP-Erweiterung prüfen.
2. **Restoreprobe vorbereiten:** Datenbank, alle tatsächlichen Dokumentwurzeln, lokale Wechselkurskonfiguration und geschützte Konfiguration sichern; `APP_KEY` geschützt erhalten. Bei SQLite konsistente Datenbanksicherung einschliesslich des WAL-Zustands beziehungsweise eine geeignete Backupfunktion verwenden. Zeilenanzahlen und Dokumentinventar mit Grösse/Hash festhalten. Eine Sicherung gilt erst nach erfolgreicher Wiederherstellung als geprüft.
3. **Staging abgleichen:** Mit derselben PHP-/DB-Konfiguration und dem festgelegten Lockfile testen. Bestandskompatibilität auf einer geschützten Kopie prüfen, ohne echte E-Mails oder Dokumente an externe APIs zu senden. Mailimport deaktivieren beziehungsweise vollständig simulieren.
4. **Release vorbereiten:** Geprüftes Paket mit `composer install --no-dev --optimize-autoloader` aus dem Lockfile und lokal gebauten Assets bereitstellen. Kein `composer update` auf Produktion. `.env`, Datenbank und Dokumente nicht durch Inhalte des Releasepakets ersetzen.
5. **Schreibzugriffe koordinieren:** Kurzes Wartungsfenster aktivieren, Cron und manuelle Importe anhalten und laufende Jobs beenden lassen. Abschliessende konsistente Sicherung erstellen. Ein Wartungsmodus allein stoppt den Cron-Wrapper nicht.
6. **Code und Migrationen:** Release wechseln oder kontrolliert aktualisieren; nur vorab geprüfte `migrate --force`-Migrationen ausführen. Niemals `migrate:fresh`, `migrate:refresh`, Demo-Seeding oder `key:generate` auf dem bestehenden Produktivbestand. Ein Wechsel des Datenbankpfads muss vorher separat vorbereitet und geprüft sein.
7. **Caches und Pfade:** Alte Konfigurations-/Routen-/View-Caches gezielt erneuern und am endgültigen Serverpfad neu erzeugen. Absolute Metanet-/Chroot-Pfade berücksichtigen. Keine laufenden Importsperren während eines Jobs durch pauschales Cache-Löschen entfernen.
8. **Abnahme vor Freigabe:** Vorhandener Benutzer und Admin können sich anmelden; Rollen und Freigaben stimmen; alte private und alte Legacy-Dokumente laden; Upload und Ersatz funktionieren; fremder Download bleibt verboten; Dashboard, Aufgaben, Countdown und Einstellungen rendern; Import läuft einmal und ein Wiederholungsversuch dupliziert nichts. Bekannte öffentliche Dokumentpfade sind nach der vorgesehenen Sperrung anonym nicht abrufbar. Daten- und Datei-Inventar abgleichen.
9. **Wiederanlauf:** Wartung beenden und genau den vorgesehenen Cron aktivieren. Fehlerquoten, Importzustände, Speicher und Logs prüfen. Beobachtung ist Bestandteil des Deploymentplans; es wurde hier keine Automation eingerichtet.
10. **Rollback:** Vorheriges Release bereithalten. Additive DB-Änderungen möglichst stehen lassen, sofern der alte Code sie toleriert; keine automatische `migrate:rollback`-Kaskade. Falls ein Datenrestore nötig wird, zuerst alle Schreiber stoppen und seit der Sicherung entstandene Daten berücksichtigen. Ein altes Backup blind zurückzuspielen würde neue Benutzerdaten verlieren.

Die Dokumentbereinigung ist ein eigener, überprüfbarer Schritt und kein automatischer Teil jedes Deployments.

## Verbindliche Regressionstests vor der Umsetzung

| Bereich | Erwartetes Verhalten |
| --- | --- |
| Bestandslogin | bcrypt-Konten funktionieren; Benutzer/Admin bleiben getrennt; Sessionwechsel sind bewusst getestet |
| Rechte | Besitzer, Mitbenutzer, Fremder und Gast für Lesen/Ändern/Löschen/Freigaben; fremde Buchung darf keinem Dokument zugeordnet werden |
| E-Mail-Identität | Alias-/Login-Kollision, Besitznachweis, Gross-/Kleinschreibung, manipuliertes Reply-To und bestehende Aliase |
| Dokumente | Privater/öffentlicher Altpfad, Chroot-Alias, mehrere physische Kopien, Schreibfehler, DB-Fehler, vollständige Downloadbytes |
| Migration | Quelle bleibt bei Fehler erhalten; Zielhash stimmt; zweiter Lauf ist sicher; vorhandene öffentliche Dublette wird erkannt |
| Import | Simuliertes IMAP/HTTP, beschädigte/grosse Anhänge, API-Timeout, Cleanup-Fehler, Parallelstart, Retry und doppelte Nachricht |
| Upgrade | Migration auf einer bestehenden DB-Kopie, Zeilenanzahlen/IDs/FKs unverändert, Dokumentinventar vollständig, Code-Rollback möglich |
| Oberfläche/Betrieb | Alle Hauptseiten, CSP-kompatible Interaktion, Authentifizierung/CSRF im realen Browser, Cron unter tatsächlichem Metanet-PHP |

Die endgültige Deploymentfreigabe bleibt an diese Nachweise und den überprüften Produktionsstand gebunden.
