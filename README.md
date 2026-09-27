# Campus Lost-and-Found System

A web app that replaces Mapua University's paper lost-and-found logbook.

CLAFS lets students and faculty browse items turned in to the Lost & Found office, report what they lost, and
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
display case. CLAFS moves that process online.

| Role | Who | Can do |
|---|---|---|
| Student / Faculty | anyone who registers | Browse found items, report lost items, claim items |
| Security & Maintenance | assigned by an admin | Everything above, plus log found items and review claims |
| Office Administrator | assigned by an admin | Everything above, plus manage users and view statistics |

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

1. Start MySQL in the XAMPP Control Panel, then create the database (PowerShell):

   ```powershell
   Get-Content database/schema.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root
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
| `admin@mapua.edu.ph` | Office Administrator |
| `staff@mapua.edu.ph` | Security & Maintenance |
| `student1@mymail.mapua.edu.ph` | Student / Faculty |

New accounts can register at `/register.php` with a `@mymail.mapua.edu.ph` or `@mapua.edu.ph` email.

- **Change password:** My Activity → Account & Password.
- **Forgot password:** use the link on the login page. On your own computer the reset email isn't sent. It
  appears in the server log instead (the `php -S` window, or `docker compose logs app`).

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
   | `BREVO_API_KEY` | API key from [Brevo](https://www.brevo.com), for password-reset emails |
   | `MAIL_FROM` | a sender address you verified in Brevo |

4. Right-click the app service → **Attach volume**, mount path `/var/www/html/public/uploads`, so uploaded
   photos survive redeploys.
5. **Settings → Networking → Generate Domain** gives you the public link.

Every push to `main` redeploys the site, and the data is kept. After the first deploy, log in with your
`SEED_PASSWORD` and change the sample accounts' passwords.

## API

The pages call JSON endpoints in [`public/api/`](public/api/) through [`public/js/api.js`](public/js/api.js).

| Endpoint | Purpose |
|---|---|
| `GET api/get_items.php` | List found items or lost reports (search and filters) |
| `POST api/add_item.php` | Add a found item or lost report, with an optional photo |
| `POST api/update_item.php` | Edit a found item or lost report |
| `POST api/update_status.php` | Change a status, or match a report to an item |
| `POST api/claims.php` | Submit, approve, reject or withdraw a claim |
| `POST api/update_user.php` | Admin: change a user's role or deactivate an account |

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
