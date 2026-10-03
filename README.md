# Cardinal Finds

A campus lost-and-found web app that replaces Mapua University's paper lost-and-found logbook.

Cardinal Finds lets students and faculty browse items turned in to the Lost & Found office, report what they lost, and
claim their belongings online. Staff log found items and verify ownership claims, and administrators manage
accounts. Built by ITS122P AM2 Group 4 with PHP, MySQL, and plain HTML, CSS and JavaScript.

## Table of Contents

- [Background](#background)
- [Install](#install)
- [Usage](#usage)
- [Project structure](#project-structure)
- [Deployment](#deployment)
- [API](#api)
- [Maintainers](#maintainers)
- [Contributing](#contributing)
- [License](#license)

## Background

Mapua University records lost items in a paper logbook. A turned-in item is written down and put in a drawer,
and a student who loses something has to visit the office in person to describe it or look through the
display case. Cardinal Finds moves that process online.

| Role | Who | Can do |
|---|---|---|
| Student / Faculty | anyone who registers and confirms their email | Browse found items, report lost items, claim items, post items they found (staff approve them before they are public) |
| Security & Maintenance | assigned by an admin | Log found items at intake, review claims and student posts, manage found items, match lost reports, view statistics. Does not file lost reports or claims |
| Office Administrator | assigned by the master admin | Manage student and staff accounts, view account activity and security logs. Has no access to found-item records, lost reports or claims: does not report, claim, log items, or review claims and posts |
| Master Administrator | one seeded account | Everything an administrator does, plus manage administrator accounts. Never locked out by failed log-ins (failures are logged and emailed instead); recovers access through password reset |

Account security: email verification on sign-up, Cloudflare Turnstile on log-in and registration, a password
policy (8+ characters with upper and lower case, a number and a symbol), a lock after 3 wrong passwords in a row
that only an admin can lift (the user visits the Lost & Found office; Admin → Users & Activity → Unlock), sessions that end after 30 minutes without activity, one device per account at a time (a new login ends the older session), and a log of every log-in, failure, lockout and
logout that admins can read under **Admin → Security Logs**.

The database design is in [docs/erd.html](docs/erd.html) and [database/schema.sql](database/schema.sql).

## Install

### With Docker (recommended)

Needs [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
docker compose up --build
```

Open <http://localhost:8080>. The database is created and filled with sample data on the first start.

To wipe all data and start fresh:

```bash
docker compose down -v
```

### With XAMPP

1. Start MySQL in the XAMPP Control Panel, then create the database, or bring an existing one up to date
   (it never deletes data; it applies any new file in `database/migrations/`):

   ```powershell
   C:\xampp\php\php.exe database/seed.php
   ```

2. Start the site and open <http://localhost:8000>:

   ```bash
   C:\xampp\php\php.exe -S localhost:8000 -t public
   ```

If your MySQL user isn't `root` with no password, create `config/db_connect.local.php`:

```php
<?php
define('DB_PASS', 'your-password');
```

## Usage

Log in at `/login.php` with a sample account. The password for each is `password123`.

| Email | Role |
|---|---|
| `masteradminp@gmail.com` | Master Administrator |
| `adminanalog1@gmail.com`, `adminlizancing2@gmail.com` | Office Administrator |
| `staffmarcos1@gmail.com` | Security & Maintenance |

There are no sample Student / Faculty accounts. Students and faculty register at `/register.php` with any
email address, then confirm it through the emailed link before logging in.

- **Change password:** My Activity → Account & Password.
- **Emails on your own computer:** verification, password-reset and security emails aren't sent without
  `BREVO_API_KEY`. They appear in the server log instead (the `php -S` window, or `docker compose logs app`).
- **CAPTCHA on your own computer:** without Turnstile keys, Cloudflare's test keys are used and always pass.
  The check still goes to Cloudflare, so log-in needs an internet connection.

## Project structure

Only `public/` is served to the browser. Everything else sits beside it, so it can't be opened by URL.

```
public/              Web root: pages, JSON API and static files
  api/               JSON endpoints called by public/js/api.js
  css/ js/ images/   Stylesheet, scripts, logo and sample photos
  uploads/           Item photos uploaded by users (not in git)
src/                 PHP logic shared by every page: bootstrap.php loads it all
templates/layout/    Page header, navigation bar and footer
config/              Settings: constants and database credentials
database/            schema.sql (tables and sample data) and seed.php (loads it in Docker)
docker/              Apache, PHP and container start-up configuration
docs/                ERD and API documentation
```

## Deployment

The site deploys to [Railway](https://railway.com) from this repository.

1. In Railway, create a project with **Deploy from GitHub repo** and choose this repo.
2. Add a database: **+ Create → Database → MySQL**.
3. On the app service, open **Variables** and add:

   | Variable | Value |
   |---|---|
   | `MYSQL_URL` | `${{MySQL.MYSQL_URL}}` |
   | `SEED_PASSWORD` | a private password for the sample accounts, replacing `password123` |
   | `APP_URL` | the site's `https://` address, used in emailed links |
   | `BREVO_API_KEY` | API key from [Brevo](https://www.brevo.com), for verification, reset and security emails |
   | `MAIL_FROM` | a dedicated sender address for system mail, verified in Brevo |
   | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | from Cloudflare dashboard → Turnstile → Add widget, with your domain. **Required**: without them every log-in and registration is refused |

4. Right-click the app service → **Attach volume**, mount path `/var/www/html/public/uploads`, so uploaded
   photos survive redeploys.
5. **Settings → Networking → Generate Domain** gives you the public link.

Every push to `main` redeploys the site, and the data is kept; new files in `database/migrations/` are applied
on start. After the first deploy, log in with your `SEED_PASSWORD` and change the sample accounts' passwords.

## API

The pages call JSON endpoints in [`public/api/`](public/api/) through [`public/js/api.js`](public/js/api.js).

| Endpoint | Purpose |
|---|---|
| `GET api/get_items.php` | List found items or lost reports (search and filters) |
| `POST api/add_item.php` | Add a found item or lost report, with an optional photo |
| `POST api/update_item.php` | Edit a found item or lost report |
| `GET api/staff_updates.php` | Newest pending claim and post (staff pages poll this to offer a refresh; doesn't count as session activity) |
| `POST api/delete_item.php` | Delete a found item logged by mistake (staff; not once returned or a claim is approved) |
| `POST api/update_status.php` | Change a status, match a report to an item, or approve/reject a student's post |
| `POST api/claims.php` | Submit, approve, reject or withdraw a claim |
| `POST api/update_user.php` | Admin: change a user's role, deactivate or unlock an account |

Full details: [docs/Group4_API_Documentation.pdf](docs/Group4_API_Documentation.pdf).

## Maintainers

ITS122P AM2 Group 4:

- Samuela Ysebelle Adame
- Kervin Del Rosario
- Mariah Kate Guiang
- Kendrick Sebastian

## Contributing

This is a class project, so pull requests are only accepted from group members. For questions or bug
reports, [open an issue](https://github.com/Kaigosama/Campus-Lost-and-Found-System/issues).

## License

© 2026 ITS122P AM2 Group 4. All rights reserved.
