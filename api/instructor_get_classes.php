<?php
session_start();
header("Content-Type: application/json");

include "administrator_db.php";

$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT instructor_id FROM instructor WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$inst = $stmt->get_result()->fetch_assoc();

if (!$inst) {
    echo json_encode([]);
    exit;
}

$instructor_id = $inst['instructor_id'];

$sql = "
    SELECT 
        sub.course_code,
        sub.description,
        s.id AS schedule_id,
        s.course_program,
        s.semester,
        r.room_name
    FROM schedule s
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN rooms r ON s.room_id = r.id
    WHERE s.instructor_id = ?
";

$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $instructor_id);
$stmt2->execute();
$result = $stmt2->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $scheduleId = $row['schedule_id'];

    // Get days from schedule_days table
    $daysStmt = $conn->prepare("SELECT day, start_time, end_time FROM schedule_days WHERE schedule_id = ? ORDER BY id");
    $daysStmt->bind_param("i", $scheduleId);
    $daysStmt->execute();
    $daysResult = $daysStmt->get_result();

    $days = [];
    while ($d = $daysResult->fetch_assoc()) {
        $days[] = $d;
    }

    $data[] = [
        "code"           => $row['course_code'],
        "name"           => $row['description'],
        "course_program" => $row['course_program'],
        "semester"       => $row['semester'],
        "room_name"      => $row['room_name'],
        "days"           => $days
    ];
}

echo json_encode($data);
?>