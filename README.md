# Group 4: Campus Lost-and-Found System

ITS122P - AM2
Group Members:

- Samuela Ysebelle Adame
- Kervin Del Rosario
- Mariah Kate Guiang
- Kendrick Sebastian

## Problem Statement

Mapua University utilizes a manual and traditional paper and pen logbook for
lost items. An item is turned in to the lost and found office. Written into a
logbook and shoved in a drawer. A student who loses something must physically
visit the office and describe the item or look at the displayed casing.

## Target Users

- Students and Faculty
- Security and Maintenance staff
- Office Administrators

## Proposed Features

- Secure user registration and role-based access
- List item reporting form
- Found item intake logging with physical storage tracking
- Ownership verification

## User Roles

- Administrator
- Staff/Employee
- Customer/User

## System Architecture

- Responsive Web UI
- User Authentication
- Relational Database

## Tech Stack

- Frontend: Blade templates and Tailwind CSS
- Backend: PHP with the Laravel Framework
- Database: MySQL

## Project Structure

```text
CLAFS/
├── api/                    # JSON endpoints called from public/js/api.js
│   ├── get_items.php       # GET  list found items / lost reports (filters, role-aware fields)
│   ├── add_item.php        # POST validate + create a found item or lost report
│   └── update_status.php   # POST change a found item's / lost report's status
├── config/
│   └── db_connect.php      # App core: constants, DB settings + db(), helpers, auth stub, data layer
├── docs/
│   ├── schema.sql          # MySQL tables (ERD) + UI-required additions + seed rows
│   └── erd.html            # Mermaid.js ERD
├── includes/
│   ├── header.php          # <head>, opens <main>, flash message
│   ├── navbar.php          # role-aware navigation
│   └── footer.php          # footer, preview role switcher, scripts
├── public/
│   ├── css/styles.css
│   ├── js/app.js           # nav, validation, image preview, table filter, API-backed forms
│   ├── js/api.js           # fetch() wrappers for api/
│   ├── images/             # static icons and logos
│   └── uploads/            # user-uploaded item photos (git-ignored)
├── index.php               # Homepage + login/register (guests) · tabbed dashboard (users, staff, admin)
├── report.php              # Report a lost item / log a found item; ?id= edits
├── browse.php              # Found items (public) · ?type=lost lost reports (staff) · ?manage=1 inventory (staff)
└── view_item.php           # Item / report detail, ownership claims, staff review and hand-over
```

### Running locally

```bash
C:\xampp\php\php.exe -S localhost:8000 -t .
```

Then open <http://localhost:8000>. Until real login exists, use the **Preview as** bar at the bottom
of every page to switch between guest, user, staff and admin. The database is not connected yet;
create it with `docs/schema.sql` when the backend phase starts.

## ERD

Interactive version: [docs/erd.html](docs/erd.html). Created by [docs/schema.sql](docs/schema.sql).

```mermaid
erDiagram
    users {
        int      user_id     PK
        varchar  first_name
        varchar  last_name
        varchar  email       UK
        enum     role        "user | staff | admin"
        datetime created_at
        datetime updated_at
    }

    lost_reports {
        int      report_id      PK
        int      user_id        FK "reporter"
        varchar  category
        text     description
        varchar  location_lost
        date     date_lost
        varchar  image_url
        enum     status         "open | matched | closed"
        datetime created_at
        datetime updated_at
    }

    found_items {
        int      item_id           PK
        int      user_id           FK "staff who logged it"
        varchar  category
        text     description
        varchar  location_found
        varchar  storage_location
        date     date_found
        varchar  image_url
        enum     status            "stored | returned | disposed"
        datetime created_at
        datetime updated_at
    }

    claims {
        int      claim_id      PK
        int      item_id       FK
        int      user_id       FK "claimant"
        int      report_id     FK "optional"
        enum     status        "pending | approved | rejected"
        date     date_claimed
        datetime created_at
        datetime updated_at
    }

    users        ||--o{ lost_reports : "files"
    users        ||--o{ found_items  : "logs"
    users        ||--o{ claims       : "makes"
    found_items  ||--o{ claims       : "receives"
    lost_reports |o--o{ claims       : "is linked to"
```

The front-end also uses a few columns not in the diagram (`item_name`, `private_details`,
`proof_description`, `review_*`, `is_active`, `password_hash`); they are added in a separate,
removable section of `schema.sql` until the group decides whether to adopt them into the ERD.
