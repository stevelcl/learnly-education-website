# Learnly Project Documentation

## Project Overview

Learnly is a responsive education website designed for undergraduate students. The website combines learning resources, student account features, discussion support, and an academic bookstore. The goal is to reduce the need for students to move between separate platforms when studying, asking questions, tracking progress, and buying books.

## Design Choices And Rationale

The website uses a clear navigation bar with direct links to Courses, Forum, Bookstore, Cart, Dashboard, Login, and Register. This supports fast movement between the main student tasks.

The homepage introduces the platform with a large learning-focused hero section and quick links to course resources and the bookstore. Course and bookstore cards use consistent spacing, readable headings, and short descriptions so students can scan content quickly.

The design uses a restrained academic color palette with blue, white, neutral gray, and warm accent tones. This gives the site a professional education feel without making the interface look too plain.

The layout is responsive. On smaller screens, the navigation collapses into a toggle menu, cards stack vertically, and tables become block-style layouts for better mobile readability.

## Implementation Process

The project was implemented using HTML, CSS, JavaScript, PHP, and MySQL.

PHP handles server-side pages, sessions, authentication, form submissions, database reads, and database writes. MySQL stores users, courses, learning resources, progress records, forum posts, replies, books, orders, and order items.

The database is accessed through PDO. All queries that include user input use prepared statements to reduce SQL injection risk.

The bookstore module includes category filtering, search, inventory display, cart storage using sessions, checkout processing, order creation, order items, and stock reduction.

The student portal includes registration, login, dashboard, saved resources, progress tracking, purchase history, and forum activity.

The discussion forum supports question posting, replies, and moderation. Admin or moderator users can hide or restore posts and replies.

For testing, a normal registered account can be promoted to admin using:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

## Database Design

The database contains these main tables:

- `users`: stores student and moderator accounts.
- `courses`: stores course/module information.
- `course_resources`: stores notes, videos, and quiz resources.
- `quiz_questions`: stores course quiz questions.
- `user_progress`: stores personalized course progress and saved courses.
- `forum_posts`: stores Q&A questions.
- `forum_replies`: stores answers and discussion replies.
- `books`: stores bookstore catalogue and inventory.
- `orders`: stores purchase records.
- `order_items`: stores books purchased in each order.

Relationships are enforced using foreign keys. For example, course resources belong to courses, forum replies belong to forum posts, and order items belong to orders.

## Azure Database Integration

The project is compatible with Azure Database for MySQL. The file `config.example.php` contains the required database configuration values:

- Azure MySQL host
- Port
- Database name
- Username
- Password
- SSL setting

To deploy with Azure:

1. Create an Azure Database for MySQL flexible server.
2. Create the `learnly` database.
3. Import `database/schema.sql`.
4. Copy `config.example.php` to `config.php`.
5. Replace the placeholder values with the Azure database credentials.
6. Keep SSL enabled if required by the Azure server.

## Usability Reflection

The website focuses on common student workflows:

- Find a course.
- Open learning resources.
- Track learning progress.
- Save useful modules.
- Ask or answer forum questions.
- Search for books.
- Add books to cart.
- Complete checkout.
- Review purchases from the dashboard.

The interface avoids unnecessary steps and keeps navigation visible across pages. Course content, forum posts, and books are grouped into cards to make information easier to scan.

## Accessibility Reflection

The website uses semantic HTML elements such as `header`, `nav`, `main`, `section`, `article`, and `footer`. Forms use labels for inputs. Navigation toggle buttons include accessible labels. Colors were selected with readability in mind, and the layout supports desktop and mobile screens.

Video resources are embedded in responsive frames. Images use empty alt text when decorative, while meaningful text is provided beside the images.

## Security Reflection

Security measures include:

- Password hashing using PHP `password_hash`.
- Password verification using `password_verify`.
- PDO prepared statements for database queries.
- Session regeneration after login.
- CSRF tokens for POST forms.
- Role checks for moderation tools.
- Output escaping with `htmlspecialchars`.
- Server-side validation for registration, login, forum posts, cart updates, and checkout.

Further improvements for production would include HTTPS enforcement, rate limiting login attempts, email verification, stricter content moderation, server-side payment integration, and detailed audit logs.

## Limitations

The checkout process is a simulated academic purchase flow and does not connect to a real payment gateway. This is suitable for a coursework prototype, but a production bookstore would need secure integration with a payment provider.

The quiz display currently reveals answers through expandable sections. A future version could store quiz attempts and calculate quiz scores.
