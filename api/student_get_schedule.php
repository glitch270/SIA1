<?php
session_start();
header("Content-Type: application/json");

require_once "student_db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$userId = $_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT 
        sub.course_code,
        sub.description,
        sub.unit,
        s.section,
        s.day,
        s.start_time,
        s.end_time,
        r.room_name,
        u.full_name AS instructor_name
    FROM enrollment e
    INNER JOIN schedule s ON e.schedule_id = s.id
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN rooms r ON s.room_id = r.id
    LEFT JOIN instructor i ON s.instructor_id = i.instructor_id
    LEFT JOIN users u ON i.user_id = u.user_id
    WHERE e.user_id = ?
    ORDER BY s.id ASC
");

$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ FORMAT DAY + TIME HERE
foreach ($rows as &$row) {
    $start = $row['start_time'] ? date("h:i A", strtotime($row['start_time'])) : '';
    $end = $row['end_time'] ? date("h:i A", strtotime($row['end_time'])) : '';

    $row['schedule'] = $row['day'] . " " . $start . " - " . $end;
}

echo json_encode($rows);
?>