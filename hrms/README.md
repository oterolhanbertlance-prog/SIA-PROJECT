# HRMS — Human Resource Management System v2

A complete, fully functional Human Resource Management System built with **PHP 8+**, **MySQL**, **Bootstrap 5**, and **Vanilla JavaScript**. No frameworks required — just PHP and MySQL.

---

## ✅ Requirements

- **PHP 8.0+** (PHP 8.1+ recommended)
- **MySQL 5.7+ / MariaDB 10.3+**
- **Apache** with `mod_rewrite` enabled
- **XAMPP**, **WAMP**, or **Laragon** (local development)
- Browser: Chrome, Firefox, Edge (latest)

---

## 🚀 Installation

### Step 1 — Copy Files

Copy the `hrms` folder to your web server root:

- **XAMPP**: `C:\xampp\htdocs\hrms\`
- **WAMP**: `C:\wamp64\www\hrms\`
- **Laragon**: `C:\laragon\www\hrms\`
- **Linux Apache**: `/var/www/html/hrms/`

### Step 2 — Import Database

#### Fresh Installation
1. Start **MySQL** (via XAMPP Control Panel or your server manager)
2. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
3. Click **Import** → **Choose File**
4. Select `database/hrms.sql`
5. Click **Go** to import

#### Upgrading from v1
If you already have the v1 database installed, run the migration instead:
1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Select the `hrms` database
3. Click **SQL** tab
4. Copy and paste the contents of `database/migration_v2.sql`
5. Click **Go**

### Step 3 — Configure Database Connection

Open `config/database.php` and update if needed:

```php
define('DB_HOST', 'localhost');   // Your MySQL host
define('DB_NAME', 'hrms');        // Database name (default: hrms)
define('DB_USER', 'root');        // MySQL username (default: root)
define('DB_PASS', '');            // MySQL password (default: empty for XAMPP)
define('SITE_URL', 'http://localhost/hrms/');  // Your site URL
```

### Step 4 — Set Upload Permissions

Make sure these folders are writable:

```
uploads/profiles/
uploads/documents/
uploads/resumes/
uploads/contracts/
```

On Linux/Mac:
```bash
chmod -R 755 uploads/
```

### Step 5 — Access the Application

Open your browser and go to:
```
http://localhost/hrms/
```

**Public Job Board (no login required):**
```
http://localhost/hrms/apply.php
```

---

## 🔑 Login Credentials

| Role     | Username   | Password   |
|----------|------------|------------|
| Admin    | `admin`    | `admin123` |
| HR       | `hr`       | `hr123`    |
| Employee | `employee` | `employee123` |

---

## 📦 Modules Included

| Module | Description |
|--------|-------------|
| **Dashboard** | Role-aware: employees see only their own data; admin/HR see org-wide stats |
| **Job Postings** | Create, edit, publish/close job openings |
| **Public Job Board** | `apply.php` — Applicants apply online without an account |
| **Applicants** | Track applicants through the hiring pipeline |
| **Interviews** | Schedule and record initial/final interviews |
| **Deployment** | Deploy hired applicants as employees with contracts |
| **Employees** | Full CRUD with profile, documents, government IDs |
| **Payroll** | Process monthly payroll — net pay never goes negative |
| **Leave Management** | File, approve/reject leave; dates validated (no backwards dates) |
| **Resignation Letters** | Employees submit, HR/Admin accepts or rejects |
| **Reports** | Employee, payroll, leave, applicant, department reports |
| **Audit Logs** | Track all system actions with user and timestamp |
| **User Management** | Admin creates users, assigns roles, changes passwords |

---

## 🆕 What's New in v2

### 1. Role-Aware Dashboard
- **Employees** now see only their own data: personal profile card, their leave balance, their latest payslip, and their own net-pay chart (last 6 months). They can no longer see other employees' salaries or HR-only pending leave lists.
- **Admin/HR** dashboard is unchanged: org-wide payroll chart, department headcount, all leave requests, upcoming interviews, and pending resignations.

### 2. Resignation Letter Module
- Employees can submit a formal resignation letter with: last working day, reason, and a full letter body.
- HR/Admin reviews all resignations at **Modules → Resignations**.
- **Accept**: Employee status changes to *Resigned*, their account is deactivated. The employee is notified.
- **Reject**: Employee is notified with HR remarks and can resubmit.
- Pending resignations are highlighted on the admin dashboard and in the sidebar badge.

### 3. Public Job Application Page (`apply.php`)
- External applicants can browse open jobs at `http://localhost/hrms/apply.php` — no login needed.
- They fill out a full application form (personal info, education, experience, resume upload, cover letter).
- Submitted applications appear in **Recruitment → Applicants** for HR to review.
- Duplicate check: same email + same job cannot apply twice.
- Admin/HR get an in-app notification for each new application.

### 4. Payroll — No Negative Net Pay
- Net pay is now capped at **₱0.00** minimum. If statutory deductions exceed gross pay, the shortfall is noted but net pay shows zero (not a negative number).
- The Process Payroll screen now shows an estimated net pay per employee before you process.
- The Edit Payroll screen has a live preview that recalculates gross/deductions/net as you type.

### 5. Leave Date Validation
- End date is **automatically constrained** to be on or after start date (both in the UI and on the server).
- Employees cannot submit leave with a start date in the past.
- Admin/HR retain the ability to backdate leave if needed.
- Weekend-only date ranges are rejected ("The selected dates fall entirely on weekends").

---

## 🔒 Security Features

- **PDO Prepared Statements** — 100% SQL injection protection
- **CSRF Tokens** — All forms protected
- **Password Hashing** — PHP `password_hash()` (bcrypt)
- **Session Security** — `session_regenerate_id()` on login
- **Role-Based Access Control** — Admin, HR, Employee roles enforced on every page
- **Input Sanitization** — All inputs sanitized with `htmlspecialchars()`
- **File Upload Validation** — MIME type + size checks
- **Secure Session Config** — `httponly`, `use_only_cookies`

---

## 🗂️ Project Structure (v2 additions)

```
hrms/
├── apply.php                         — Public job board & application form (NEW)
├── modules/
│   ├── resignation/
│   │   ├── my_resignation.php        — Employee: view/submit resignation (NEW)
│   │   └── list.php                  — HR/Admin: manage all resignations (NEW)
│   ├── payroll/
│   │   ├── process.php               — Fixed: no negative net pay (UPDATED)
│   │   └── edit_payroll.php          — Fixed + live preview (UPDATED)
│   └── leave/
│       └── add.php                   — Fixed: date validation (UPDATED)
├── dashboard.php                     — Fixed: role-aware (UPDATED)
├── includes/
│   └── header.php                    — Updated navigation (UPDATED)
└── database/
    ├── hrms.sql                      — Full fresh install (UPDATED)
    └── migration_v2.sql              — Upgrade script for existing installs (NEW)
```

---

## 🛠️ Troubleshooting

| Problem | Solution |
|---------|----------|
| "Database Connection Error" | Check DB_HOST, DB_USER, DB_PASS in `config/database.php` |
| Resignation menu not showing | Run `database/migration_v2.sql` to add the new table |
| Blank page | Check PHP error logs, enable `display_errors` |
| Upload fails | Check write permissions on `uploads/` folders |
| Login fails | Re-import `database/hrms.sql` to reset passwords |

### POS hiring records not appearing

Run `database/migration_v13_fix_hiring_records.sql` once against the `hrms`
database. It repairs older POS job postings, fills missing employee
departments and positions, and creates any missing active contract records.
New hires marked **Hired** now create or repair the employee, deployment,
contract, department, HRMS employee login, and matching POS account together.
| CSS not loading | Verify `SITE_URL` ends with trailing slash |
| apply.php shows "No open positions" | Go to Recruitment → Job Postings and set at least one job to "Open" |

---

## 📝 License

This project is provided for educational and commercial use. Feel free to modify it for your organization.

---

*Built with ❤️ using PHP 8+, MySQL, Bootstrap 5, and Chart.js*
