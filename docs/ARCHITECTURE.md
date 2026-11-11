# Smart Company Management System — Architecture & Folder Guide

## 1. Architectural Overview
SmartCMS is designed as a modular, framework-free **Native PHP 8.3+ OOP MVC-inspired application** paired with a **Python 3 FastAPI financial analytics & export microservice**, backed by **MySQL 8** using prepared PDO statements and atomic database transactions.

```
Browser (Bootstrap 5 + Chart.js + Vanilla JS)
   │
   ▼
Native PHP Front Controller (public/index.php)
   ├── Core Router & Middleware (Auth, CSRF, RBAC Permissions)
   ├── Controllers (HTTP / JSON orchestration)
   ├── Services (Business logic, DB transactions, calculations)
   ├── Models (PDO prepared queries, soft deletes)
   └── ExportService (PhpSpreadsheet XLSX, DomPDF PDF, CSV)
         │
         ├──► MySQL 8 Database (28 relational tables)
         └──► Python FastAPI Microservice (:8001, X-API-Key secured)
                ├── Pandas Financial Forecasting & Anomaly Detection
                ├── Configurable Performance & Payroll Calculators
                └── OpenPyXL XLSX & ReportLab PDF Engines
```

## 2. Directory Breakdown

- **`public/`**: Web document root (`index.php` front controller, `router.php` for PHP built-in dev server, local CSS/JS/vendor assets). Uploaded files are intentionally stored outside `public/` in `storage/uploads/` and served through authenticated PHP download controllers.
- **`config/`**: Environment-backed application config (`app.php`), PDO MySQL config (`database.php`), and declarative web/API route definitions (`routes.php`).
- **`app/Core/`**: Zero-framework foundation classes:
  - `Application.php` — Bootstraps sessions, locale, timezone, and global error handling.
  - `Router.php` — Regex route matching with parameter extraction and middleware pipelines.
  - `Database.php` — Singleton PDO connection factory with prepared statement wrappers and `transaction()` closure support.
  - `Auth.php` — Session & API-token authentication, password verification, RBAC permission resolution, and user preferences loader.
  - `Csrf.php` — Cryptographic per-session CSRF tokens verified with `hash_equals`.
  - `Validator.php` — Reusable request validation engine.
  - `Translator.php` — Multi-language translation resolver (`en`, `uz`, `ja`).
  - `View.php` & `Helpers.php` — Layout renderer, XSS escaping (`e()`), locale/user-format-aware currency/date/number formatters.
- **`app/Middleware/`**: `AuthMiddleware`, `PermissionMiddleware`, and `CsrfMiddleware`.
- **`app/Models/`**: Object-oriented table models extending `BaseModel` with soft-delete support (`deleted_at`).
- **`app/Services/`**: Pure business logic layer separated from controllers:
  - `FinanceService` & `CurrencyService` — Multi-currency conversion, `Income - Expenses = Net Balance`, recurring expense generation, expense approvals.
  - `EmployeeService` — 8-section employee profile aggregation, secure document & photo storage, privacy enforcement.
  - `PayrollService` — Flexible salary engine (`fixed`, `hourly`, `daily`, `project`, `task`) enforcing explicit authorization before deducting penalties/deductions, plus atomic payment posting to ledger.
  - `TaskPerformanceService` — Task completion rewards, attendance tracking, configurable KPI weights & bonus tiers.
  - `ReportService` & `ExportService` — All 11 automatic report types, Method B manual custom table & formula builder, and exports via `PhpSpreadsheet`, `DomPDF`, `CSV`, or Python `OpenPyXL`/`ReportLab`.
  - `PythonAnalyticsClient` — cURL client authenticating to FastAPI with `X-API-Key`.
  - `BackupService`, `NotificationService`, `ActivityLogService`, `FileUploadService`, `PersonalizationService`.
- **`app/Controllers/`**: Thin HTTP & REST API controllers (`DashboardController`, `FinanceController`, `EmployeeController`, `PayrollController`, `TaskController`, `PerformanceController`, `ReportController`, `NotificationController`, `SettingsController`, `ApiController`).
- **`database/migrations/` & `database/seeders/`**: Versioned SQL schema migrations (28 tables with foreign keys and indexes) and realistic multi-currency demo seeder.
- **`bin/`**: CLI utilities (`migrate.php`, `seed.php`, `backup.php`).
- **`resources/lang/` & `resources/views/`**: Translation dictionaries (`en.php`, `uz.php`, `ja.php`) and Bootstrap 5 views.
- **`analytics_service/`**: Standalone Python 3 FastAPI microservice (`Pandas`, `OpenPyXL`, `ReportLab`, `PyMySQL`, `Pytest`).
- **`tests/`**: Automated PHP test suite verifying auth, RBAC, finance, payroll, reports, PDF/XLSX exports, and live API endpoints.
