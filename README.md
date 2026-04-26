# Learnly Learning Website

Learnly is a responsive education website for undergraduate students. It combines course learning resources, a student portal, discussion forum, and academic bookstore with a cart and checkout flow.

## Technology Stack

- Front-end: HTML, CSS, JavaScript
- Back-end: PHP
- Database: MySQL, compatible with Azure Database for MySQL

## Main Features

- Responsive homepage
- Course catalogue with lecture notes, videos, quizzes, and progress tracking
- Student registration and login
- Personalized student dashboard
- Discussion forum with Q&A posts and moderation actions
- Bookstore catalogue with categories, search, cart, checkout, inventory, and purchase history
- Secure database access using PDO prepared statements
- Password hashing with PHP `password_hash`
- CSRF protection for POST forms

## Setup

1. Create an Azure Database for MySQL flexible server.
2. Create a database named `learnly`.
3. Import `database/schema.sql`.
4. Copy `config.example.php` to `config.php`.
5. Update `config.php` with your Azure MySQL host, database name, username, password, and SSL setting.
6. Run the project through a PHP server:

```bash
php -S localhost:8000
```

7. Open `http://localhost:8000`.

## Azure Database Notes

Azure Database for MySQL usually requires SSL. In `config.php`, keep `DB_SSL` enabled unless your server configuration says otherwise.

The sample schema includes seed data for:

- Courses
- Resources
- Quiz questions
- Books
- Forum posts

## Demo Login

After importing the schema, you can register a new account from the website. The seed data does not include a pre-made user because passwords should be generated securely by the app.

To test moderation tools, register normally and then promote your account in MySQL:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

## Folder Structure

```text
assets/
  css/styles.css
  js/app.js
includes/
  auth.php
  cart.php
  csrf.php
  db.php
  footer.php
  header.php
database/
  schema.sql
docs/
  documentation.md
```
