<?php
$conn = mysqli_connect("localhost", "root", "", "unified_scheduling");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>