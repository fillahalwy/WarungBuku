# WarungBuku — Book Store Marketplace

A simple PHP-based book marketplace admin panel with a public storefront. Built with vanilla PHP, MySQL, and Bootstrap 5.

> **Note:** This project was originally developed during my software development internship at **CV. Surya Media (Lahat, Indonesia)**. It has since been refactored to improve maintainability, clean code standards, and database performance.

---

## 🛠️ Tech Stack

| Layer      | Technology                              |
|------------|-----------------------------------------|
| Backend    | PHP 8.x (procedural)                    |
| Database   | MySQL 8.x via `mysqli`                  |
| Frontend   | HTML5, CSS3 (vanilla)                   |
| UI Library | Bootstrap 5.1.3 + Bootstrap Icons 1.8.1 |
| Server     | Apache (via [Laragon](https://laragon.net)) |

---

## ⚙️ Requirements

- [Laragon](https://laragon.net) (or any LAMP/WAMP stack)
- PHP >= 7.4
- MySQL >= 5.7

---

## 🚀 Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/your-username/project-marketplace.git
```

Place the cloned folder inside your web server's root directory:

- **Laragon:** `C:\laragon\www\`
- **XAMPP:** `C:\xampp\htdocs\`
- **WAMP:** `C:\wamp64\www\`

### 2. Create the database

1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Click **Import**
3. Select the file `database/db_schema.sql`
4. Click **Go**

This will automatically create the `db_marketplace` database with all tables and seed data.

### 3. Configure the database connection

Open `koneksi.php` and adjust the credentials if needed:

```php
$conn = mysqli_connect('localhost', 'root', '', 'db_marketplace');
```

| Parameter | Default       |
|-----------|---------------|
| Host      | `localhost`   |
| Username  | `root`        |
| Password  | *(empty)*     |
| Database  | `db_marketplace` |

### 4. Run the application

Start Laragon (or your web server), then open your browser:

```
http://localhost/project-marketplace/
```

- **Public storefront:** `http://localhost/project-marketplace/index.php`
- **Admin panel:** `http://localhost/project-marketplace/login.php`

### 5. Default admin credentials

| Field    | Value   |
|----------|---------|
| Username | `admin` |
| Password | `admin` |

> ⚠️ Change the default password immediately after first login via the **Profile** page.

---

## 🗄️ Database Schema

Three tables are used:

```
admins        — Administrator accounts
categories    — Product categories
products      — Book/product listings (FK → categories.id, CASCADE)
```

See [`database/db_schema.sql`](database/db_schema.sql) for the full schema.