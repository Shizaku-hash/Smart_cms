# Smart Company Management System (SmartCMS)

A complete, production-structured Company Management System built with **Native PHP 8.3+ OOP MVC (no PHP framework)**, **MySQL**, **Python 3 FastAPI**, **Bootstrap 5**, **Chart.js**, **PhpSpreadsheet**, **DomPDF**, **Pandas**, **OpenPyXL**, and **ReportLab**.

## Key Features
- **Executive Dashboard**: Dynamic KPIs (Net Balance, Income, Expenses, Profit, Payroll, Pending Payments), custom date range filter, 5 interactive Chart.js visualizations, customizable/reorderable widgets, and live Python FastAPI Pandas cashflow forecasts & anomaly alerts.
- **Financial Management**: Income & Expense CRUD, custom categories, recurring expense scheduler, file attachments, multi-currency conversion with configurable exchange rates, approval workflow, and automatic `Total Income - Total Expenses = Net Balance`.
- **Employee Management (HRM)**: Directory, search/filters, and an 8-section Employee Profile (Personal, Employment, Salary, Attendance, Performance, Bonuses & Penalties, Payment History, Documents) with strict role-based privacy controls.
- **Salary & Payroll**: Supports `fixed`, `hourly`, `daily`, `project`, and `task` compensation models, attendance/overtime integration, task rewards, bonus tracking, **mandatory authorization before applying deductions**, printable/PDF/XLSX salary statements, and atomic ledger posting on payment.
- **Tasks & Performance**: Task assignments, deadlines, completion rewards, attendance recording, and configurable KPI weights & bonus tiers (no hardcoded scoring!).
- **Report Builder & Exports**: All 11 report types via **Method A (Automatic DB Generation)** and **Method B (Manual Custom Table Builder with formulas like `=B1-C1`, `=SUM(B1:B3)`)**, reusable templates, and exports in **PDF, XLSX, and CSV** via either PHP (`DomPDF`/`PhpSpreadsheet`) or Python (`ReportLab`/`OpenPyXL`).
- **Personalization, RBAC, Multilingual & Security**: 7 default roles with configurable permission matrix, Light/Dark themes, **English / Uzbek / Japanese** translations, pinned sidebar modules, shortcuts, activity audit logs, soft deletes, CSRF protection, and MySQL `.sql` backups.

---

## Requirements
- PHP 8.3+ (`cli`, `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `curl`, `intl`, `bcmath`)
- Composer 2.x
- MySQL 8.0+ or MariaDB 10.6+
- Python 3.10+ (`venv`, `pip`)

---

## Installation & Setup

### 1. Database Setup
Create the MySQL database and user (matching `.env`):
```sql
CREATE DATABASE smart_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cms_user'@'127.0.0.1' IDENTIFIED BY 'cms_dev_pass';
GRANT ALL PRIVILEGES ON smart_cms.* TO 'cms_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 2. Environment & Dependencies
```bash
cp .env.example .env
composer install
```

Setup Python FastAPI microservice:
```bash
python3 -m venv analytics_service/.venv
analytics_service/.venv/bin/pip install -r analytics_service/requirements.txt
```

### 3. Run Database Migrations & Demo Seeder
```bash
php bin/migrate.php --fresh
php bin/seed.php
```

### 4. Start the Application
```bash
chmod +x start.sh stop.sh
./start.sh
```
- Web Application: `http://127.0.0.1:8080`
- Python FastAPI Docs: `http://127.0.0.1:8001/docs`

Stop both servers cleanly with:
```bash
./stop.sh
```

---

## Demo Accounts
| Role | Email | Password |
| :--- | :--- | :--- |
| Super Admin | `admin@novatech.io` | `Admin123!` |
| Company Owner | `owner@novatech.io` | `Owner123!` |
| Accountant | `accountant@novatech.io` | `Accountant123!` |
| HR Manager | `hr@novatech.io` | `Hr123456!` |
| Department Manager | `manager@novatech.io` | `Manager123!` |
| Employee | `employee@novatech.io` | `Employee123!` |

---

## Running Automated Tests
### PHP Integration & Unit Suite
```bash
php tests/TestRunner.php
```
### Python FastAPI & Analytics Suite
```bash
cd analytics_service && .venv/bin/pytest -v
```
