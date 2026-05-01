<?php
session_start();
header("Content-Type: application/json");

$conn = new mysqli(
    getenv('MYSQL_HOST'),
    getenv('MYSQL_USER'),
    getenv('MYSQL_PASSWORD'),
    getenv('MYSQL_DATABASE'),
    (int)getenv('MYSQL_PORT')
);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Please fill in all fields"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, password, role, full_name FROM users WHERE user_id = ?");
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($password !== $row['password']) {
        echo json_encode(["status" => "error", "message" => "Invalid password"]);
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['full_name'] = $row['full_name'];

    echo json_encode([
        "status" => "success",
        "role" => $row['role']
    ]);

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username"
    ]);
}

$conn->close();
?>