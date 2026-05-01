<?php
session_start();
header("Content-Type: application/json");

include "administrator_db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

/* GET instructor_id from user_id */
$stmt = $conn->prepare("
    SELECT instructor_id 
    FROM instructor 
    WHERE user_id = ?
");
$stmt->bind_param("s", $user_id);
$stmt->execute();

$res = $stmt->get_result();
$inst = $res->fetch_assoc();

if (!$inst) {
    echo json_encode([]);
    exit;
}

$instructor_id = $inst['instructor_id'];

/* GET ONLY ASSIGNED SCHEDULES */
$sql = "
    SELECT 
        sub.course_code,
        sub.description,
        s.section,
        s.day,
        s.start_time,
        s.end_time,
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
    $data[] = [
        "code" => $row['course_code'],
        "name" => $row['description'],
        "section" => $row['section'],
        "day" => $row['day'],
        "start_time" => $row['start_time'],
        "end_time" => $row['end_time'],
        "room_name" => $row['room_name']
    ];
}

echo json_encode($data);
?>