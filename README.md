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
CREATE DATABASE attendance_db;
USE attendance_db;

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL
);

CREATE TABLE students (
    register_no VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL
);

CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_no VARCHAR(10) NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    department VARCHAR(50) NOT NULL,
    FOREIGN KEY (register_no) REFERENCES students(register_no)
);

CREATE TABLE holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL
);
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

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

## 📬 Contact

For issues, bugs, or suggestions, please open an issue on GitHub.
