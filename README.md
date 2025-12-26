# 🎓 Attendance Management System

A web-based system for educational institutions to manage and track student attendance efficiently. The system includes faculty and admin portals, holiday management, and export features.

---

## 📌 Features

### 👨‍🏫 Faculty Portal
- Secure login with department selection
- Mark and update daily attendance
- Real-time attendance statistics
- Export attendance to Excel
- Holiday check to prevent marking on holidays
- User-friendly UI with keyboard shortcuts and bulk actions

### 🛠️ Admin Portal
- View all attendance records with filters (department, date, status, register number)
- Display statistics (total records, present/absent count, unique students/dates)
- Export filtered data to Excel
- Manage common holidays via interface
- Track completed/pending attendance by department and date

### 📅 Holiday Management
- Add, update, and delete common holidays
- Display holidays with reason
- Block attendance marking on holidays

---

## 🧰 Tech Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL

---

## ⚙️ Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or any web server
- Chrome / Firefox / Edge browser

---

## 🚀 Installation Guide

### 1. Clone the Repository

```bash
git clone <repository-url>
cd attendance-management-system
```

### 2. Set Up the Database

```sql
CREATE DATABASE IF NOT EXISTS attendance_db;
USE attendance_db;

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS students (
    register_no VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_no VARCHAR(10) NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    department VARCHAR(50) NOT NULL,
    FOREIGN KEY (register_no) REFERENCES students(register_no)
);

CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL
);bh
```

### 3. Configure Database Connection

Edit `db.php`:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "attendance_db";
```

### 4. Deploy to Web Server

- Place project files in `htdocs` (or root directory of your server)
- Ensure PHP and MySQL services are running

---

### 5. If required

```sql

-- ============================================
-- ATTENDANCE MANAGEMENT SYSTEM DATABASE
-- Complete Setup with Teachers and Students
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS attendance_db;
USE attendance_db;

-- ============================================
-- TABLE: teachers
-- Stores faculty login credentials by department
-- ============================================
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: students
-- Stores student information by department
-- ============================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_no VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    department VARCHAR(50) NOT NULL,
    year INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_register (register_no),
    INDEX idx_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: attendance_records
-- Stores daily attendance records
-- ============================================
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_no VARCHAR(20) NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    department VARCHAR(50) NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (register_no, attendance_date),
    FOREIGN KEY (register_no) REFERENCES students(register_no) ON DELETE CASCADE,
    INDEX idx_date (attendance_date),
    INDEX idx_dept_date (department, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: holidays
-- Stores common holidays for all departments
-- ============================================
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT TEACHERS DATA (SKIP IF ALREADY EXISTS)
-- One teacher per department with credentials
-- ============================================

-- Clear existing teachers (OPTIONAL - uncomment if you want to start fresh)
-- DELETE FROM teachers;

-- Insert teachers only if they don't exist
INSERT IGNORE INTO teachers (username, password, department) VALUES
-- CSE Department
('cse_faculty', 'cse123', 'CSE'),

-- IT Department
('it_faculty', 'it123', 'IT'),

-- ECE Department
('ece_faculty', 'ece123', 'ECE'),

-- MECH Department
('mech_faculty', 'mech123', 'MECH'),

-- CIVIL Department
('civil_faculty', 'civil123', 'CIVIL'),

-- EEE Department
('eee_faculty', 'eee123', 'EEE');

-- ============================================
-- INSERT STUDENTS DATA (SKIP IF ALREADY EXISTS)
-- 5 students per department (30 total)
-- ============================================

-- Clear existing students (OPTIONAL - uncomment if you want to start fresh)
-- DELETE FROM students;

-- CSE Department Students
INSERT IGNORE INTO students (register_no, name, department) VALUES
('CSE2024001', 'Aarav Malhotra', 'CSE'),
('CSE2024002', 'Diya Kapoor', 'CSE'),
('CSE2024003', 'Rohan Desai', 'CSE'),
('CSE2024004', 'Ananya Singh', 'CSE'),
('CSE2024005', 'Kabir Mehta', 'CSE'),

-- IT Department Students
('IT2024001', 'Ishaan Joshi', 'IT'),
('IT2024002', 'Saanvi Gupta', 'IT'),
('IT2024003', 'Arjun Nair', 'IT'),
('IT2024004', 'Myra Pillai', 'IT'),
('IT2024005', 'Vihaan Rao', 'IT'),

-- ECE Department Students
('ECE2024001', 'Advait Choudhury', 'ECE'),
('ECE2024002', 'Kiara Banerjee', 'ECE'),
('ECE2024003', 'Reyansh Bose', 'ECE'),
('ECE2024004', 'Anika Das', 'ECE'),
('ECE2024005', 'Ayaan Ghosh', 'ECE'),

-- MECH Department Students
('MECH2024001', 'Vivaan Thakur', 'MECH'),
('MECH2024002', 'Navya Iyer', 'MECH'),
('MECH2024003', 'Shaurya Menon', 'MECH'),
('MECH2024004', 'Pari Krishnan', 'MECH'),
('MECH2024005', 'Arnav Pandey', 'MECH'),

-- CIVIL Department Students
('CIVIL2024001', 'Aditya Saxena', 'CIVIL'),
('CIVIL2024002', 'Ira Mishra', 'CIVIL'),
('CIVIL2024003', 'Dhruv Agarwal', 'CIVIL'),
('CIVIL2024004', 'Tara Bhatt', 'CIVIL'),
('CIVIL2024005', 'Yash Jain', 'CIVIL'),

-- EEE Department Students
('EEE2024001', 'Atharv Chauhan', 'EEE'),
('EEE2024002', 'Riya Sinha', 'EEE'),
('EEE2024003', 'Lakshya Tiwari', 'EEE'),
('EEE2024004', 'Aadhya Dubey', 'EEE'),
('EEE2024005', 'Pranav Yadav', 'EEE');

-- ============================================
-- INSERT SAMPLE HOLIDAYS (SKIP IF ALREADY EXISTS)
-- Common holidays for all departments
-- ============================================

-- Clear existing holidays (OPTIONAL - uncomment if you want to start fresh)
-- DELETE FROM holidays;

INSERT IGNORE INTO holidays (holiday_date, reason) VALUES
('2025-01-26', 'Republic Day'),
('2025-03-08', 'Maha Shivaratri'),
('2025-03-25', 'Holi'),
('2025-04-10', 'Eid al-Fitr'),
('2025-04-14', 'Dr. Ambedkar Jayanti'),
('2025-05-01', 'Labour Day'),
('2025-08-15', 'Independence Day'),
('2025-10-02', 'Gandhi Jayanti'),
('2025-10-24', 'Dussehra'),
('2025-11-12', 'Diwali'),
('2025-12-25', 'Christmas Day');

-- ============================================
-- VERIFICATION QUERIES
-- Run these to verify your setup
-- ============================================

-- View all teachers
SELECT username, department FROM teachers ORDER BY department;

-- View all students by department
SELECT register_no, name, department FROM students ORDER BY department, register_no;

-- Count students per department
SELECT department, COUNT(*) as student_count FROM students GROUP BY department ORDER BY department;

-- View all holidays
SELECT holiday_date, reason FROM holidays ORDER BY holiday_date;

-- ============================================
-- QUICK REFERENCE - LOGIN CREDENTIALS
-- ============================================
/*
DEPARTMENT | USERNAME      | PASSWORD
-----------|---------------|----------
CSE        | cse_faculty   | cse123
IT         | it_faculty    | it123
ECE        | ece_faculty   | ece123
MECH       | mech_faculty  | mech123
CIVIL      | civil_faculty | civil123
EEE        | eee_faculty   | eee123
*/

```



## 🌐 Access the System

- Faculty Login: [http://localhost/attendance-management-system/login.php](http://localhost/attendance-management-system/login.php)  
- Admin Panel: [http://localhost/attendance-management-system/view_attendance.php](http://localhost/attendance-management-system/view_attendance.php)

---

## 🧑‍💻 Usage

### Faculty
- Login → Select department → Mark attendance in `attendance.php`
- Use bulk actions: **Mark All Present**, **Mark All Absent**, or clear
- Export records to Excel

### Admin
- Access `view_attendance.php`
- Apply filters: Department, Date, Register No, Status
- Export or print results
- Click **Common Holidays** to manage holidays

### Holiday Management
- Access `manage_holidays.php`
- Add, edit, or delete holidays with reason

---

## 📁 File Structure

| File | Description |
|------|-------------|
| `db.php` | DB connection |
| `login.php` | Faculty login with department |
| `attendance.php` | Mark/view attendance |
| `view_attendance.php` | Admin dashboard |
| `manage_holidays.php` | Manage holidays |
| `logout.php` | Logout script |

---

## 🔐 Security Notes

- **Passwords**: Currently stored as plain text. ⚠️ Use `password_hash()` & `password_verify()` in production.
- **XSS Prevention**: `htmlspecialchars()` applied on input.
- **SQL Injection**: Uses **prepared statements**.
- **Sessions**: Validates session data with redirection for unauthorized access.

---

## 📈 Future Improvements

- 🔐 Password hashing for faculty login
- 🔐 Role-based access control (Admin vs Faculty)
- 📊 Weekly/monthly attendance summary reports
- 🔗 API integration support
- 📥 Bulk student import from Excel
- 📈 Interactive charts and visual analytics

---

## 🤝 Contributing

1. Fork the repository  
2. Create a feature branch: `git checkout -b feature/YourFeature`  
3. Commit your changes: `git commit -m 'Add YourFeature'`  
4. Push to GitHub: `git push origin feature/YourFeature`  
5. Create a Pull Request  

---

## 👨‍💻 Author

**Sri Balakumar**

* GitHub: [@Sri-balakumar](https://github.com/Sri-balakumar)

## 📜 License

This project is licensed under the [MIT License](LICENSE).

---

⭐️ Star this repo if you found it useful!

````

