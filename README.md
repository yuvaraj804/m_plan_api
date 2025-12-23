# 🧰 M-Plan API

A lightweight **Maintenance Plan Management System** that provides a RESTful API with a simple **HTML/JavaScript/CSS** frontend for managing maintenance plans, work orders, and complaints.

**Backend:** PHP
**Frontend:** HTML / JavaScript / CSS
**Database:** PostgreSQL (schema `_10009_1pl`)

---

## 📖 Table of Contents

* [About](#about)
* [Features](#features)
* [Requirements](#requirements)
* [Installation](#installation)
* [Configuration](#configuration)
* [Running (development)](#running-development)
* [API Endpoints (examples)](#api-endpoints-examples)
* [Frontend](#frontend)
* [Actions & Security](#actions--security)
* [Database Schema](#database-schema)
* [Testing](#testing)
* [Development & Contributing](#development--contributing)
* [Deployment](#deployment)
* [License](#license)
* [Authors](#authors)
* [Acknowledgements](#acknowledgements)

---

## 🧩 About

The **M-Plan API** enables tracking, scheduling, and managing maintenance plans and work orders.
It includes:

* PHP backend APIs for CRUD operations.
* PostgreSQL schema for plans, work orders, and complaints.
* HTML + JavaScript frontend (with AJAX + CSRF-protected forms).

---

## ⚙️ Features

* REST-style API for Maintenance Plans, Work Orders, and Complaints
* Session-based authentication and **CSRF token protection**
* CRUD operations via `action` parameter:

  * `insert` — create new record
  * `edit` — update record
  * `delete` — remove record
* Automatic **Next Due Date** calculation
* File attachments and remarks support
* PostgreSQL with ENUM-based status fields
* Simple Bootstrap + JS interface

---

## 🧾 Requirements

* PHP **7.4+** (recommended: PHP 8.x)
* PostgreSQL or MariaDB
* Apache / Nginx / PHP built-in server
* Git for version control
* Optional: Composer (if dependencies exist)

---

## 🚀 Installation

1. **Clone repository**

   ```bash
   git clone https://github.com/yuvaraj804/m_plan_api.git
   cd m_plan_api
   ```

2. **Environment setup**

   ```bash
   cp .env.example .env
   ```

3. **Database setup**

   * Create a PostgreSQL database (e.g. `m_plan_api`)
   * Import schema:

     ```sql
     \i db/db_copy.sql
     ```

4. **File permissions**

   * Allow write access for:

     * `/uploads/` (attachments)
     * `/logs/` (if enabled)

---

## ⚙️ Configuration

Example `.env`:

```env
APP_ENV=development
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=m_plan_api
DB_USERNAME=postgres
DB_PASSWORD=secret

CSRF_TOKEN_SECRET=your_secret_here
```

---

## 🧮 Running (development)

### Option A — PHP built-in server

```bash
php -S localhost:8000
```

Visit: [http://localhost:8000](http://localhost:8000)

### Option B — Apache / Nginx

* Point VirtualHost or server block to the project root.
* Enable rewrite rules for `/api/*` requests.

---

## 🔗 API Endpoints (examples)


### 🧱 Maintenance Plan

| Method | Endpoint          | Description                                |
| ------ | ----------------- | ------------------------------------------ |
| GET    | `/api/mplan`      | List maintenance plans                     |
| POST   | `/api/mplan`      | Insert, edit, or delete plans via `action` |
| GET    | `/api/mplan/{id}` | Get single plan details                    |

Example `POST /api/mplan` body:

```json
{
  "action": "insert",
  "branch_idf": 1,
  "pstage_idf": 2,
  "product_idf": 4,
  "mtype_idf": 3,
  "mref_no": "MC011",
  "start_date": "2025-10-11 00:00:00",
  "end_date": "2025-10-16 00:00:00",
  "desc_prob": "Routine check",
  "assign_to": 5,
  "frequency": "Every Day",
  "pref_idf": 5,
  "next_due": "2025-10-12 00:00:00",
  "due_alert": "Y",
  "rstatus": "act",
  "csrf_token": "<valid_csrf_token>"
}
```

---

## 🖥️ Frontend

Frontend pages are built with **Bootstrap + JavaScript (AJAX)**:

| File                     | Description                                |
| ------------------------ | ------------------------------------------ |
| `mp_form.php`            | Maintenance plan form (insert/edit/delete) |
| `mp_request.php`         | Request or view plan data                  |
| `wo_order.php`           | Work order creation and update             |
| `calculate_next_due.php` | Backend logic for due date calculation     |

Forms automatically include a hidden CSRF token field retrieved from session:

```html
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
```

---

## 🛡️ Actions & Security

### Action Handling

All database changes are handled through an `action` field in POST requests:

| Action   | Operation              |
| -------- | ---------------------- |
| `insert` | Add new record         |
| `edit`   | Update existing record |
| `delete` | Remove record          |

Example:

```js
const payload = {
  action: "edit",
  id: 10,
  desc_prob: "Updated description",
  csrf_token: sessionToken
};
```

### CSRF Protection

* Each session includes a unique `$_SESSION['csrf_token']`
* Every form and AJAX POST must include this token.
* Backend verifies the token before processing:

  ```php
  if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
      http_response_code(403);
      exit('CSRF validation failed');
  }
  ```

---

## 🗃️ Database Schema

Schema: `_10009_1pl`

| Table              | Purpose                                   |
| ------------------ | ----------------------------------------- |
| `common`           | Master list (branch, product, pref, etc.) |
| `msopl_mplan`      | Maintenance plan master                   |
| `msopl_work_order` | Work order records                        |
| `msopl_complaint`  | Complaint tracking                        |

**Enums:**

* `en_status`: `act`, `can`, `com`, `del`, `log`, `sus`, `rev`, `iac`, `pro`, `obs`, `pre`, `sac`, `shp`
* `etic_status`: `opn`, `cls`, `ini`, `hol`

---

## 🧪 Testing

Use **Postman** or **curl** for manual testing.

Example:

```bash
curl -X POST http://localhost:8000/api/mplan \
  -H "Content-Type: application/json" \
  -d '{"action":"insert","csrf_token":"abcd1234"}'
```

Optional PHPUnit (if tests are added):

```bash
vendor/bin/phpunit
```

---

## 👨‍💻 Development & Contributing

1. Fork repository
2. Create a feature branch
3. Commit and open PR
4. Follow **PSR-12** PHP style and JS best practices

---

## 🌐 Deployment

1. Use production `.env` values
2. Run:

   ```bash
   composer install --no-dev
   ```
3. Import DB schema and seed data
4. Configure server root and HTTPS
5. Ensure sessions and CSRF are properly configured

---

## 📜 License

MIT License — see `LICENSE` file.

---

## ✍️ Author

**Yuvaraj Palani** — [github.com/yuvaraj804](https://github.com/yuvaraj804)

---

## 🙏 Acknowledgements

* Bootstrap / FontAwesome
* PostgreSQL
* PHP community docs

