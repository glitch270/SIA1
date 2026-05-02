<?php
session_start();
include 'administrator_db.php';

$selectedSchedule = null;
$deleted = false;
$message = '';

if (isset($_GET['id'])) {
    $scheduleId = mysqli_real_escape_string($conn, $_GET['id']);

    $query = "
        SELECT 
            s.*,
            sub.course_code,
            sub.description,
            r.room_name,
            u.full_name AS instructor_name
        FROM schedule s
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        LEFT JOIN rooms r ON s.room_id = r.id
        LEFT JOIN instructor i ON s.instructor_id = i.instructor_id
        LEFT JOIN users u ON i.user_id = u.user_id
        WHERE s.id = '$scheduleId'
    ";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $selectedSchedule = mysqli_fetch_assoc($result);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $scheduleId = intval($_POST['schedule_id'] ?? 0);

    if ($scheduleId <= 0) {
        $message = "Invalid schedule ID.";
    } else {
        $deleteEnrollment = "DELETE FROM enrollment WHERE schedule_id = $scheduleId";
        mysqli_query($conn, $deleteEnrollment);

        $deleteSql = "DELETE FROM schedule WHERE id = $scheduleId";

        if (mysqli_query($conn, $deleteSql)) {
            $deleted = true;
        } else {
            $message = "Error deleting record: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Delete Schedule</title>
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
        .delete-table { width: 100%; max-width: 800px; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #ddd; }
        .delete-table th { text-align: left; padding: 12px; font-size: 16px; border: 1px solid #ddd; background-color: #fff; }
        .delete-table td { padding: 10px 12px; border: 1px solid #ddd; font-size: 14px; color: #333; }
        .label-col { font-weight: bold; width: 30%; background-color: #f9f9f9; }
        .warning-msg { color: #000; font-weight: 600; font-size: 14px; margin-top: 20px; margin-bottom: 30px; max-width: 700px; }
        .warning-msg span.red-label { color: #b91c1c; }
        .btn-container { display: flex; gap: 10px; }
        .btn-confirm { background-color: #ff0000; color: white; padding: 10px 25px; border-radius: 20px; border: none; font-weight: bold; cursor: pointer; }
        .btn-confirm:hover { opacity: 0.8; }
        .btn-cancel { background-color: #ced4da; color: #444; padding: 10px 25px; border-radius: 20px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; }
        .btn-cancel:hover { background-color: #bdc3c7; }
        .success-box { padding: 20px; background: #d4edda; color: #155724; border-radius: 8px; border: 1px solid #c3e6cb; max-width: 800px; }
        .info-box { background: #e9ecef; border-radius: 8px; padding: 50px 40px; text-align: left; color: #555; max-width: 1000px; }
        .info-box p { font-size: 16px; margin-bottom: 30px; color: #333; }
        .btn-back { display: inline-block; background: #cccccc; color: #333; padding: 10px 35px; border-radius: 12px; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn-back:hover { background-color: #bbbbbb; }
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
            <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php" class="active">Delete Schedule</a>
            <!-- Fix: logout with localStorage clear -->
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </nav>
    </aside>

    <main class="main">
        <h2>Delete Schedule</h2>

        <?php if ($deleted): ?>
            <div class="success-box">
                <strong>Success!</strong> Schedule deleted successfully.
                <br><br>
                <a href="/api/administrator_view_schedule.php" class="btn-cancel">Back to List</a>
            </div>

        <?php elseif ($selectedSchedule === null): ?>
            <div class="info-box">
                <p>No schedule selected. Please go to View Schedule and click Delete.</p>
                <a href="/api/administrator_view_schedule.php" class="btn-back">Back</a>
            </div>

        <?php else: ?>
            <table class="delete-table">
                <thead>
                    <tr>
                        <th colspan="2">Delete Schedule [ID: <?php echo $selectedSchedule['id']; ?>]</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="label-col">Subject</td>
                        <td><?php echo htmlspecialchars($selectedSchedule['course_code'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Description</td>
                        <td><?php echo htmlspecialchars($selectedSchedule['description'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Teacher</td>
                        <td><?php echo htmlspecialchars($selectedSchedule['instructor_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Classroom</td>
                        <td><?php echo htmlspecialchars($selectedSchedule['room_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Schedule</td>
                        <td>
                            <?php
                                $start = $selectedSchedule['start_time'] ? date("h:i A", strtotime($selectedSchedule['start_time'])) : '';
                                $end = $selectedSchedule['end_time'] ? date("h:i A", strtotime($selectedSchedule['end_time'])) : '';
                                echo htmlspecialchars(($selectedSchedule['day'] ?? '') . " " . $start . " - " . $end);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">Section</td>
                        <td><?php echo htmlspecialchars($selectedSchedule['section']); ?></td>
                    </tr>
                </tbody>
            </table>

            <p class="warning-msg">
                <span class="red-label">WARNING:</span> This action is permanent and cannot be undone.
            </p>

            <form method="POST" class="btn-container">
                <input type="hidden" name="schedule_id" value="<?php echo $selectedSchedule['id']; ?>">
                <button type="submit" name="confirm_delete" class="btn-confirm">Confirm Delete</button>
                <a href="/api/administrator_view_schedule.php" class="btn-cancel">Cancel</a>
            </form>
        <?php endif; ?>
    </main>

    <!-- Fix: logout function with localStorage clear -->
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