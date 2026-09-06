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

## Initial ERD

erDiagram
    Users {
        int user_id PK
        string first_name
        string last_name
        string email
        string role
    }

    Lost_Reports {
        int report_id PK
        int user_id FK
        string description
        date date_lost
        string status
    }

    Found_Items {
        int item_id PK
        int user_id FK
        string description
        string storage_location
        string status
    }

    Claims {
        int claim_id PK
        int item_id FK
        int user_id FK
        string status
    }

    %% Relationships based on PK/FK pairs
    Users ||--o{ Lost_Reports : "has"
    Users ||--o{ Found_Items : "reports"
    Users ||--o{ Claims : "makes"
    Found_Items ||--o{ Claims : "receives"
