<?php
session_start();
header("Content-Type: application/json");

require_once "student_db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$user_id = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($user_id === "" || $password === "") {
    echo json_encode(["status" => "error", "message" => "User ID and password required"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

$storedPassword = $user["password"];

if (str_starts_with($storedPassword, '$2y$')) {
    $valid = password_verify($password, $storedPassword);
} else {
    $valid = ($password === $storedPassword);
}

if (!$valid) {
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

if ($user["role"] !== "student") {
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$_SESSION["user_id"] = $user["user_id"];
$_SESSION["name"] = $user["full_name"];
$_SESSION["role"] = $user["role"];

// Fix: Return user_id and full_name for localStorage
echo json_encode([
    "status"    => "success",
    "role"      => "student",
    "user_id"   => $user["user_id"],
    "full_name" => $user["full_name"]
]);
?>