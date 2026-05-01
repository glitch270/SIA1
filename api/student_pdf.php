<?php
session_start();
require_once "student_db.php";

// Fix: Get user_id from SESSION or GET parameter
$userId = $_SESSION["user_id"] ?? $_GET["user_id"] ?? null;

if (!$userId) {
    die("Not logged in");
}

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
    ORDER BY s.day, s.start_time
");

$stmt->execute([$userId]);
$schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Class Schedule PDF</title>
    <style>
        body { font-family: Arial; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 8px; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
<h2>Class Schedule</h2>
<table>
    <tr>
        <th>Course Code</th>
        <th>Description</th>
        <th>Unit</th>
        <th>Schedule</th>
        <th>Room</th>
        <th>Instructor</th>
        <th>Section</th>
    </tr>

    <?php if (empty($schedules)): ?>
        <tr>
            <td colspan="7" style="text-align:center;">No enrolled classes found</td>
        </tr>
    <?php else: ?>
        <?php foreach ($schedules as $row): ?>
            <?php
                $start = $row['start_time'] ? date("h:i A", strtotime($row['start_time'])) : '';
                $end   = $row['end_time'] ? date("h:i A", strtotime($row['end_time'])) : '';
                $schedule = $row['day'] . " " . $start . " - " . $end;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['course_code']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($row['unit']) ?></td>
                <td><?= htmlspecialchars($schedule) ?></td>
                <td><?= htmlspecialchars($row['room_name']) ?></td>
                <td><?= htmlspecialchars($row['instructor_name']) ?></td>
                <td><?= htmlspecialchars($row['section']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<script>
    // Fix: Pass user_id via URL for PDF
    const userId = localStorage.getItem('user_id');
    if (userId && !window.location.search.includes('user_id')) {
        window.location.href = '/api/student_pdf.php?user_id=' + userId;
    } else {
        window.print();
    }
</script>
</body>
</html>