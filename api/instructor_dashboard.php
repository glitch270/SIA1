<?php
session_start();
header("Content-Type: application/json");

include "administrator_db.php";

// Fix: Get user_id from SESSION or GET parameter
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "assignedClasses" => 0,
        "totalSubjects" => 0,
        "classrooms" => 0
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT instructor_id FROM instructor WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$inst = $res->fetch_assoc();

if (!$inst) {
    echo json_encode([
        "assignedClasses" => 0,
        "totalSubjects" => 0,
        "classrooms" => 0
    ]);
    exit;
}

$instructor_id = $inst['instructor_id'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM schedule WHERE instructor_id = ?");
$stmt->bind_param("s", $instructor_id);
$stmt->execute();
$assigned = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT subject_id) as total FROM schedule WHERE instructor_id = ?");
$stmt->bind_param("s", $instructor_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT room_id) as total FROM schedule WHERE instructor_id = ?");
$stmt->bind_param("s", $instructor_id);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "assignedClasses" => $assigned['total'] ?? 0,
    "totalSubjects" => $subjects['total'] ?? 0,
    "classrooms" => $rooms['total'] ?? 0
]);
?>