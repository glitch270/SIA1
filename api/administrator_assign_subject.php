<?php
session_start();
include 'db.php';

// GET TOTAL SCHEDULES
$countQuery = "SELECT COUNT(*) as total FROM schedule";
$countResult = mysqli_query($conn, $countQuery);

$scheduleCount = 0;
if ($countResult) {
    $row = mysqli_fetch_assoc($countResult);
    $scheduleCount = $row['total'];
}

// GET RECENT SCHEDULES
$recentSchedulesQuery = "SELECT * FROM schedule ORDER BY id DESC LIMIT 5";
$recentResult = mysqli_query($conn, $recentSchedulesQuery);

$recentSchedules = [];
if ($recentResult) {
    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentSchedules[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Assign</title>
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
        .content-box { background: #e9ecef; border-radius: 8px; padding: 40px 30px; text-align: center; color: #555; line-height: 1.6; }
        .description { font-size: 15px; margin-bottom: 10px; color: #333; }
        .sub-description { font-size: 14px; margin-bottom: 30px; }
        .quick-actions { background: white; width: 100%; max-width: 500px; margin: 0 auto; padding: 25px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: left; }
        .quick-actions h3 { font-size: 16px; color: #000; margin-bottom: 15px; text-align: center; }
        .quick-actions ul { list-style-type: disc; padding-left: 20px; }
        .quick-actions li { margin-bottom: 8px; font-size: 14px; color: #333; }
        .quick-actions a { color: #0a0a3c; text-decoration: none; font-weight: 600; }
        .quick-actions a:hover { text-decoration: underline; }
        .stats-box { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-top: 20px; text-align: center; }
        .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
        .btn-logout:hover { background-color: #d32f2f; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <aside class="sidebar">
        <div class="header">
            <!-- Fix: absolute path for logo -->
            <img src="/PSU.png" alt="University Logo" class="logo">
            <div class="school-text">
                <h1>Partido State University</h1>
                <p>Goa, Camarines Sur</p>
            </div>
        </div>

        <h2>Schedule Management</h2>

        <nav id="sidebarNav">
            <!-- Fix: absolute paths for all links -->
            <a href="/api/administrator_assign_subject.php" class="active">Assign subject / teacher / classroom</a>
            <a href="/api/administrator_create_schedule.php">Create Schedule</a>
            <a href="/api/administrator_view_schedule.php">View Schedule</a>
            <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
        </nav>

        <button class="btn-logout" onclick="window.location.href='/api/administrator_logout.php'">Log Out</button>
    </aside>

    <main class="main">
        <h2>Assign subject / teacher / classroom</h2>

        <div class="content-box">
            <p class="description">
                This feature allows you to manage the assignment of subjects, teachers, and classrooms.
            </p>
            <p class="sub-description">
                Use the Create Schedule feature to assign subjects to teachers and classrooms, or use Update Schedule to modify existing assignments.
            </p>

            <div class="quick-actions">
                <h3>Quick Actions:</h3>
                <ul>
                    <li><a href="/api/administrator_create_schedule.php">Create Schedule</a> to assign new subjects</li>
                    <li><a href="/api/administrator_view_schedule.php">View Schedule</a> to see all the assignments</li>
                    <li><a href="/api/administrator_update_schedule.php">Update Schedule</a> to modify existing assignments</li>
                </ul>

                <?php if ($scheduleCount > 0): ?>
                    <div class="stats-box">
                        <strong>Current Status:</strong> <?php echo $scheduleCount; ?> schedule(s) created
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>