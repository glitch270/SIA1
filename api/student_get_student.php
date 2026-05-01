<?php
session_start();
header("Content-Type: application/json");

require_once "student_db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$userId = $_SESSION["user_id"];

// JOIN users + student_profile
$stmt = $pdo->prepare("
    SELECT 
        u.user_id,
        u.full_name,
        sp.course_program,
        sp.section,
        sp.academic_year,
        sp.period
    FROM users u
    LEFT JOIN student_profile sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(["status" => "error", "message" => "Student record not found"]);
    exit;
}

echo json_encode([
    "status"        => "success",
    "name"          => $student["full_name"],
    "section"       => $student["section"] ?? "",
    "academic_year" => $student["academic_year"] ?? "",
    "period"        => $student["period"] ?? ""
]);
?>