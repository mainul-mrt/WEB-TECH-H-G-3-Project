-- Database: web_project
CREATE DATABASE IF NOT EXISTS web_project;
USE web_project;

-- Table: departments
CREATE TABLE departments (
  code VARCHAR(10) PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

INSERT INTO departments (code, name) VALUES
('BBA', 'Business Administration'),
('CSE', 'Computer Science & Engineering'),
('EEE', 'Electrical & Electronic Engineering');

-- Table: courses
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dept_code VARCHAR(10) NOT NULL,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(100) NOT NULL,
  FOREIGN KEY (dept_code) REFERENCES departments(code)
);

INSERT INTO courses (id, dept_code, code, title) VALUES
(1, 'CSE', 'WEB-TECH', 'Web Technologies'),
(2, 'CSE', 'OOP', 'Object Oriented Programming'),
(3, 'CSE', 'DS', 'Data Science Fundamentals'),
(4, 'BBA', 'ACC', 'Accounting'),
(5, 'BBA', 'FIN', 'Financial Management');

-- Table: users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin','faculty','student') NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  verified TINYINT(1) DEFAULT 0,
  full_name VARCHAR(100),
  user_id VARCHAR(50) UNIQUE,
  dept VARCHAR(10),
  designation VARCHAR(100),
  email VARCHAR(120) UNIQUE,
  password_hash VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  profile_pic VARCHAR(255),
  FOREIGN KEY (dept) REFERENCES departments(code)
);



-- Table: quizzes
CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT NOT NULL,
  dept_code VARCHAR(10) NOT NULL,
  course_id INT NOT NULL,
  created_by VARCHAR(50),
  title VARCHAR(100),
  type VARCHAR(20),
  duration_minutes INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES users(id),
  FOREIGN KEY (dept_code) REFERENCES departments(code),
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Table: quiz_questions
CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  type ENUM('mcq','qa') NOT NULL,
  question TEXT NOT NULL,
  options JSON,
  answer TEXT,
  marks INT DEFAULT 1,
  position INT,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
);

-- Table: quiz_attempts
CREATE TABLE quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  quiz_id INT NOT NULL,
  answers JSON,
  grade INT DEFAULT 0,
  graded TINYINT(1) DEFAULT 0,
  grading_details JSON,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id),
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
);

-- Table: quiz_attempt_answers
CREATE TABLE quiz_attempt_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  answer_text TEXT,
  auto_awarded INT DEFAULT 0,
  awarded INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id),
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id)
);

-- Table: password_reset_tokens
CREATE TABLE password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
