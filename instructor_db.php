<?php
$conn = new mysqli(
    getenv('MYSQL_HOST'),
    getenv('MYSQL_USER'),
    getenv('MYSQL_PASSWORD'),
    getenv('MYSQL_DATABASE'),
    (int)getenv('MYSQL_PORT')
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

