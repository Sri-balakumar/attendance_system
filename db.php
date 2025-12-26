<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "attendance_db";  // Removed the semicolon here

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>