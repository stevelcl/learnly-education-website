CREATE DATABASE IF NOT EXISTS learnly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE learnly;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'moderator', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    level VARCHAR(80) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE course_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    resource_type ENUM('note', 'video', 'quiz') NOT NULL,
    content TEXT NOT NULL,
    resource_url VARCHAR(255),
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    progress_percent INT NOT NULL DEFAULT 0,
    saved BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_course (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

CREATE TABLE forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    author VARCHAR(140) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    inventory INT NOT NULL DEFAULT 0,
    cover_url VARCHAR(255)
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_book (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('paid', 'processing', 'cancelled') NOT NULL DEFAULT 'processing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id)
);

INSERT INTO courses (title, subject, description, level) VALUES
('Academic Writing Essentials', 'Communication', 'Learn how to structure essays, cite sources, and write with clarity for university assignments.', 'Year 1'),
('Introduction to Programming', 'Computer Science', 'Build foundational programming skills using variables, control flow, functions, and problem solving.', 'Year 1'),
('Database Systems', 'Information Systems', 'Understand relational design, SQL, normalization, transactions, and database security.', 'Year 2'),
('Statistics for Research', 'Mathematics', 'Explore descriptive statistics, probability, hypothesis testing, and data interpretation.', 'Year 2');

INSERT INTO course_resources (course_id, title, resource_type, content, resource_url, sort_order) VALUES
(1, 'Lecture Note: Essay Structure', 'note', 'Introduction, body paragraphs, evidence, analysis, and conclusion are the core parts of academic essays.', NULL, 1),
(1, 'Video: Avoiding Plagiarism', 'video', 'Short tutorial on paraphrasing, quoting, and using citations ethically.', 'https://www.youtube.com/embed/2q0NlWcTq1Y', 2),
(2, 'Lecture Note: Variables and Data Types', 'note', 'Variables store values. Common data types include strings, integers, floats, and booleans.', NULL, 1),
(2, 'Interactive Quiz: Programming Basics', 'quiz', 'Answer quick questions to review programming fundamentals.', NULL, 2),
(3, 'Lecture Note: Normalization', 'note', 'Normalization reduces redundancy and improves data consistency through structured table design.', NULL, 1),
(3, 'Video: SQL Joins Explained', 'video', 'Understand INNER JOIN, LEFT JOIN, and relationship queries.', 'https://www.youtube.com/embed/9Pzj7Aj25lw', 2),
(4, 'Lecture Note: Hypothesis Testing', 'note', 'Hypothesis testing helps researchers decide whether sample results support a claim.', NULL, 1);

INSERT INTO quiz_questions (course_id, question, option_a, option_b, option_c, correct_option) VALUES
(2, 'Which structure repeats code while a condition is true?', 'Loop', 'Array', 'Function', 'A'),
(2, 'Which data type stores true or false values?', 'String', 'Boolean', 'Float', 'B'),
(3, 'What is the main purpose of normalization?', 'Increase duplication', 'Reduce redundancy', 'Remove all keys', 'B'),
(4, 'A p-value is commonly used to help decide whether to:', 'Format a table', 'Reject or fail to reject a hypothesis', 'Choose a font', 'B');

INSERT INTO books (title, author, category, description, price, inventory, cover_url) VALUES
('Writing for University Success', 'Maya Collins', 'Communication', 'A practical guide to academic essays, reports, and citation habits.', 42.50, 18, 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=600&q=80'),
('Programming Logic Made Clear', 'Daniel Hart', 'Computer Science', 'Beginner-friendly programming concepts with exercises and examples.', 58.90, 25, 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=600&q=80'),
('Database Design Fundamentals', 'Nadia Rahman', 'Information Systems', 'Relational database design, SQL queries, indexing, and security.', 65.00, 14, 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=600&q=80'),
('Statistics for Social Research', 'Owen Lim', 'Mathematics', 'Core statistics concepts for undergraduate research projects.', 51.75, 20, 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=600&q=80'),
('Study Skills Handbook', 'Aisha Tan', 'Student Success', 'Time management, note-taking, exam preparation, and reflective learning.', 35.00, 30, 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?auto=format&fit=crop&w=600&q=80');
