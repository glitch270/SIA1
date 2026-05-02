<?php
session_start();
include 'administrator_db.php';

$validationResults = null;
$conflicts = [];
$schedules = [];

function timeOverlaps($start1, $end1, $start2, $end2)
{
    return (strtotime($start1) < strtotime($end2) &&
            strtotime($end1) > strtotime($start2));
}

function formatTime($time)
{
    return !empty($time) ? date('h:i A', strtotime($time)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate'])) {

    // Fix: Added subjects join to get course_code
    $query = "
        SELECT 
            s.*,
            sub.course_code,
            u.full_name AS instructor_name,
            r.room_name
        FROM schedule s
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        LEFT JOIN instructor i ON s.instructor_id = i.instructor_id
        LEFT JOIN users u ON i.user_id = u.user_id
        LEFT JOIN rooms r ON s.room_id = r.id
    ";

    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $schedules[] = [
                'id' => $row['id'],
                'subject' => $row['course_code'] ?? 'N/A',
                'teacher' => $row['instructor_name'] ?? 'N/A',
                'classroom' => $row['room_name'] ?? 'N/A',
                'day' => $row['day'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ];
        }
    }

    for ($i = 0; $i < count($schedules); $i++) {
        for ($j = $i + 1; $j < count($schedules); $j++) {
            $s1 = $schedules[$i];
            $s2 = $schedules[$j];

            if ($s1['classroom'] === $s2['classroom'] && $s1['day'] === $s2['day']) {
                if (timeOverlaps($s1['start_time'], $s1['end_time'], $s2['start_time'], $s2['end_time'])) {
                    $conflicts[] = [
                        'type' => 'Classroom Conflict',
                        'description' => "{$s1['classroom']} is used by {$s1['subject']} and {$s2['subject']} on {$s1['day']} with overlapping time.",
                        'schedule1' => $s1,
                        'schedule2' => $s2
                    ];
                }
            }
        }
    }

    $validationResults = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Validate Schedule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .top-bar { position: fixed; top: 0; left: 0; width: 100%; height: 45px; background: #0b0f3b; z-index: 1000; }
        body { padding-top: 45px; background: #fdfdfd; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background: #e9ecef; display: flex; flex-direction: column; padding: 25px 15px; border-right: 1px solid #dee2e6; }
        .sidebar .header { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .sidebar .logo { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        .sidebar .school-text h1 { font-size: 13px; font-weight: 700; color: #333; line-height: 1.2; }
        .sidebar .school-text p { font-size: 11px; color: #666; }
        .sidebar h2 { font-size: 18px; margin-bottom: 20px; color: #000; font-weight: 700; text-align: center; }
        .sidebar nav { display: flex; flex-direction: column; gap: 8px; }
        .sidebar nav a { text-decoration: none; background: #0a0a3c; color: white; padding: 12px 15px; border-radius: 4px; font-size: 18px; font-weight: 700; text-align: center; transition: background 0.2s; }
        .sidebar nav a:hover { background: #2a2a7c; }
        .sidebar nav a.active { background: #2a2a7c; box-shadow: inset 0 0 0 2px #fff; }
        .main { flex: 1; padding: 40px; overflow-y: auto; }
        .main h2 { font-size: 20px; margin-bottom: 25px; color: #000; font-weight: 700; }
        .validation-box { background: white; border: 1px solid #eee; border-radius: 8px; padding: 30px 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .validation-box p { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; max-width: 600px; }
        .btn-validate { display: inline-block; background: #0a0a3c; color: white; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: background 0.2s; }
        .btn-validate:hover { background: #4a66a0; }
        .results-box { background: #e9ecef; border-radius: 8px; padding: 40px; text-align: center; color: #777; font-size: 15px; }
        .results-box.has-results { text-align: left; }
        .success-result { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb; }
        .conflict-item { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #f5c6cb; }
        .conflict-item strong { display: block; margin-bottom: 5px; }
        .schedule-count { font-size: 14px; color: #666; margin-bottom: 15px; }
        .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
        .btn-logout:hover { background-color: #d32f2f; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <aside class="sidebar">
        <div class="header">
            <img src="/PSU.png" alt="University Logo" class="logo">
            <div class="school-text">
                <h1>Partido State University</h1>
                <p>Goa, Camarines Sur</p>
            </div>
        </div>

        <h2>Schedule Management</h2>

        <nav id="sidebarNav">
            <a href="/api/administrator_assign_subject.php">Assign subject / teacher / classroom</a>
            <a href="/api/administrator_create_schedule.php">Create Schedule</a>
            <a href="/api/administrator_view_schedule.php">View Schedule</a>
            <a href="/api/administrator_validate_schedule.php" class="active">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </nav>
    </aside>

    <main class="main">
        <h2>Validate Schedule</h2>

        <div class="validation-box">
            <p>Click the button below to validate all schedules for conflicts. The system will check for teacher and classroom conflicts.</p>
            <form method="POST" action="">
                <button type="submit" name="validate" class="btn-validate">Validate All Schedule</button>
            </form>
        </div>

        <div class="results-box <?php echo !empty($validationResults) ? 'has-results' : ''; ?>">
            <?php if (empty($validationResults)): ?>
                <p>No validation results yet. Click the button above to start validation.</p>

            <?php elseif (empty($schedules)): ?>
                <p>No schedules to validate. Please create some schedules first.</p>

            <?php elseif (empty($conflicts)): ?>
                <div class="success-result">
                    <strong>✓ Validation Complete</strong>
                    <p>No conflicts found! All <?php echo count($schedules); ?> schedules are valid.</p>
                </div>

            <?php else: ?>
                <p class="schedule-count">
                    Validated <?php echo count($schedules); ?> schedules.
                    Found <?php echo count($conflicts); ?> conflict(s):
                </p>

                <?php foreach ($conflicts as $conflict): ?>
                    <div class="conflict-item">
                        <strong>⚠ <?php echo htmlspecialchars($conflict['type']); ?></strong>
                        <p><?php echo htmlspecialchars($conflict['description']); ?></p>
                        <small>
                            Schedule 1: <?php echo htmlspecialchars($conflict['schedule1']['subject'] ?? 'N/A'); ?>
                            (<?php echo formatTime($conflict['schedule1']['start_time'] ?? ''); ?> - <?php echo formatTime($conflict['schedule1']['end_time'] ?? ''); ?>)
                            <br>
                            Schedule 2: <?php echo htmlspecialchars($conflict['schedule2']['subject'] ?? 'N/A'); ?>
                            (<?php echo formatTime($conflict['schedule2']['start_time'] ?? ''); ?> - <?php echo formatTime($conflict['schedule2']['end_time'] ?? ''); ?>)
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function logout() {
            localStorage.removeItem('user_id');
            localStorage.removeItem('role');
            localStorage.removeItem('full_name');
            window.location.href = '/api/administrator_logout.php';
        }
    </script>
</body>
</html>