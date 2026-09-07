# TinyIB — PHP 8.5 edition

This edition targets PHP 8.5 and newer on a 64-bit runtime. It was verified on Windows with PHP 8.5.10 and nginx 1.30.4. PHP 8.4 and earlier are unsupported.

## Start the board

Double-click **Start TinyIB.bat**. Keep it beside `imgboard.php` inside the board folder, or beside a subfolder named `TinyIB`. It finds the board relative to its own location, so moving the folder or starting it from another working directory works. The launcher uses `C:\php\php.exe`, rebuilds `index.html`, starts the local server, and opens `http://127.0.0.1:8080/`. Keep its console open while using the board. Close the console or press Ctrl+C to stop it.

For the first run, follow the configuration steps below to create `settings.php`, set a random secret and initialize an administrator account. This repository contains source code and empty media directories; live settings, databases, uploads, generated pages and credentials are excluded.

The local server binds to the loopback interface. `local-router.php` exposes the board pages and media and blocks application code, configuration, database files, and scripts in upload directories.

## Requirements

- PHP 8.5+, 64 bit, with GD/FreeType, mbstring, fileinfo, curl, PDO, and the selected PDO database driver.
- The default configuration uses `pdo_sqlite` and `.tinyib.db`; no separate database server is needed.
- Write access to the board directory, `src`, `thumb`, and `res` for the PHP process.
- JavaScript for static-page form tokens and management actions.

## Configuration

`settings.php` now returns a configuration array. Options are validated and loaded into `TinyIB\Config`; old `TINYIB_*` constants are no longer used.

For a fresh installation:

1. Copy `settings.default.php` to `settings.php`.
2. Set `tripseed` to a random secret, for example the output of `php -r "echo bin2hex(random_bytes(32));"`.
3. Set `adminpass` to the initial administrator password. Optional `modpass` creates a moderator account.
4. Run `php imgboard.php` in the board directory. The database, initial accounts and static index are created.
5. Clear `adminpass` and `modpass` in settings after creation. Later runs never reset an existing account's password from these fields.

Common options:

| Option | Purpose |
| --- | --- |
| `board`, `boarddesc`, `boardtitle` | Board identifier, heading and browser title |
| `timezone` | PHP timezone identifier, initially `UTC` |
| `captcha`, `replycaptcha`, `managecaptcha`, `reportcaptcha` | `simple` or an empty string to disable that challenge |
| `report` | Enable reporting, initially `false` |
| `dbdriver`, `dbpath` | Initially `sqlite` and `.tinyib.db` |
| `maxkb`, `maxkbdesc` | Upload size in KiB and the displayed description |
| `maxwop`, `maxhop`, `maxw`, `maxh` | Thumbnail dimensions |
| `threadsperpage`, `maxthreads`, `maxreplies` | Pagination and retention limits |
| `uploads`, `embeds` | Allowed media types and oEmbed endpoints |
| `thumbnail` | `gd`, `imagemagick`, or `ffmpeg` |
| `uploadviaurl` | Remote file downloads; initially `false` |

Keep `tripseed` unchanged after setup. It is used for tripcodes and IP fingerprints. This edition uses HMAC tripcodes, so displayed tripcodes differ from original TinyIB. Old crypt/MD5 deletion-password formats and old flat-file databases are not supported.

The PDO implementation also contains MySQL and PostgreSQL connection/schema paths using `dbhost`, `dbport`, `dbname`, `dbusername`, `dbpassword`, or `dbdsn`. SQLite has been integration-tested. Set the appropriate port and install the matching PDO extension before using another database.

## nginx

`nginx.conf` is a standalone Windows configuration with an example root of `C:/path/to/tinyib`; update it to your installation directory. Start the FastCGI listener with `start-php.ps1`, then start nginx with its own directory as the prefix:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\path\to\tinyib\start-php.ps1"
C:\nginx\nginx.exe -p C:/nginx/ -c C:/path/to/tinyib/nginx.conf
```

nginx itself is not installed at `C:\nginx`; adjust that example to your nginx installation. The supplied configuration listens on `127.0.0.1:8080` and forwards PHP to `127.0.0.1:9000`. It executes only `imgboard.php` and `inc/captcha.php`. Run the Desktop server or nginx on port 8080 one at a time. Adjust the root, address and TLS configuration for a different deployment.

## Code and checks

Every PHP file declares strict types. Application classes are namespaced, methods have native parameter and return types, configuration uses readonly promoted properties and a computed property hook, and roles use an enum. PHP 8.5 features include the pipe operator, native URI parsing, `array_first`, `#[NoDiscard]`, and immutable configuration copies using clone-with. Password arguments use `#[SensitiveParameter]`.

The app has a single PDO data layer with prepared statements, explicit request normalization, session CSRF protection, one-use expiring CAPTCHA, password hashes, controlled external-process arguments, atomic static-page writes, and bounded remote downloads. PHP warnings and deprecations are surfaced as exceptions and logged.

Core entry points are `bootstrap.php`, `imgboard.php`, `inc/captcha.php`, and `local-router.php`. Application logic is under `app`. Translation packages, locales, reCAPTCHA, Apache rules, updater code, old database drivers, and compatibility fallbacks have been removed. The simple CAPTCHA font remains in `inc/fonts`.

Run the portable regression and source audit without modifying the board database:

```powershell
C:\php\php.exe tests/run.php
```

It checks an in-memory SQLite database, prepared queries, password verification, role privileges, input validation, CAPTCHA expiry/replay, subprocess arguments, strict declarations and method signatures. The modernization also passed isolated nginx workflow tests and real browser form checks. PHPStan level 5 has zero errors; `phpstan.neon` and `.php-cs-fixer.dist.php` make the checks repeatable with those development tools. Formatting follows PHP-FIG PER Coding Style with the PHP 8.5 migration rule set.

GD image uploads and thumbnails were exercised. Optional remote providers, video processing, ImageMagick, FFmpeg and ExifTool require their own services/programs and were not exercised end to end.

Original TinyIB attribution and MIT licensing are retained in `LICENSE`.
