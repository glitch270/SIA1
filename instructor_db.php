<?php
$conn = new mysqli("localhost", "root", "", "unified_scheduling");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>