<?php
session_start();
include 'administrator_db.php';

$searchTerm    = trim($_GET['search'] ?? '');
$filterSubject = $_GET['filter_subject'] ?? 'all';
$filterRoom    = $_GET['filter_room'] ?? 'all';
$filterTeacher = $_GET['filter_teacher'] ?? 'all';
$filterDay     = $_GET['filter_day'] ?? 'all';

$sql = "
SELECT 
    s.*,
    sub.course_code,
    sub.description,
    r.room_name,
    u.full_name AS teacher_name
FROM schedule s
LEFT JOIN subjects sub ON s.subject_id = sub.id
LEFT JOIN rooms r ON s.room_id = r.id
LEFT JOIN instructor i ON s.instructor_id = i.instructor_id
LEFT JOIN users u ON i.user_id = u.user_id
WHERE 1=1
";

if ($searchTerm !== '') {
    $searchTerm = mysqli_real_escape_string($conn, $searchTerm);
    $sql .= " AND (
        sub.course_code LIKE '%$searchTerm%'
        OR sub.description LIKE '%$searchTerm%'
        OR r.room_name LIKE '%$searchTerm%'
        OR u.full_name LIKE '%$searchTerm%'
        OR s.day LIKE '%$searchTerm%'
    )";
}

if ($filterSubject !== 'all') {
    $filterSubject = mysqli_real_escape_string($conn, $filterSubject);
    $sql .= " AND sub.course_code = '$filterSubject'";
}

if ($filterRoom !== 'all') {
    $filterRoom = mysqli_real_escape_string($conn, $filterRoom);
    $sql .= " AND r.room_name = '$filterRoom'";
}

if ($filterTeacher !== 'all') {
    $filterTeacher = mysqli_real_escape_string($conn, $filterTeacher);
    $sql .= " AND u.full_name = '$filterTeacher'";
}

if ($filterDay !== 'all') {
    $filterDay = mysqli_real_escape_string($conn, $filterDay);
    $sql .= " AND s.day = '$filterDay'";
}

$result = mysqli_query($conn, $sql);
$filteredSchedules = [];
while ($row = mysqli_fetch_assoc($result)) {
    $filteredSchedules[] = $row;
}

$subjects = mysqli_query($conn, "SELECT course_code FROM subjects");
$allSubjects = [];
while ($r = mysqli_fetch_assoc($subjects)) {
    $allSubjects[] = $r['course_code'];
}

$rooms = mysqli_query($conn, "SELECT room_name FROM rooms");
$allRooms = [];
while ($r = mysqli_fetch_assoc($rooms)) {
    $allRooms[] = $r['room_name'];
}

$teachers = mysqli_query($conn, "
    SELECT u.full_name
    FROM instructor i
    JOIN users u ON i.user_id = u.user_id
");
$allTeachers = [];
while ($r = mysqli_fetch_assoc($teachers)) {
    $allTeachers[] = $r['full_name'];
}

$daysResult = mysqli_query($conn, "SELECT DISTINCT day FROM schedule");
$allDays = [];
while ($r = mysqli_fetch_assoc($daysResult)) {
    $allDays[] = $r['day'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Management - View</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .top-bar { position: fixed; top: 0; left: 0; width: 100%; height: 45px; background: #0b0f3b; z-index: 1000; }
        body { background: #fdfdfd; display: flex; height: 100vh; overflow: hidden; }
        .container { display: flex; height: calc(100vh - 45px); margin-top: 45px; }
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
        .filters { background: #eee; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .filters input { width: 100%; padding: 10px; margin-bottom: 15px; }
        .filter-row { display: flex; gap: 20px; }
        .filter-row div { flex: 1; }
        .filter-row label { display: block; margin-bottom: 5px; font-size: 14px; }
        .filter-row select { width: 100%; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #ddd; }
        th, td { padding: 12px; text-align: left; }
        tbody tr { border-bottom: 1px solid #ccc; }
        .edit { color: green; cursor: pointer; text-decoration: none; }
        .edit:hover { text-decoration: underline; }
        .delete { color: red; cursor: pointer; text-decoration: none; }
        .delete:hover { text-decoration: underline; }
        .footer-text { margin-top: 10px; font-size: 13px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #666; font-style: italic; }
        .filter-btn { padding: 8px 20px; background: #0a0a3c; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        .filter-btn:hover { background: #2a2a7c; }
        .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
        .btn-logout:hover { background-color: #d32f2f; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="container">
        <aside class="sidebar">
            <div class="header">
                <img src="/PSU.png" alt="PSU Logo" class="logo">
                <div class="school-text">
                    <h1>Partido State University</h1>
                    <p>Goa, Camarines Sur</p>
                </div>
            </div>

            <h2>Schedule Management</h2>

            <nav>
                <a href="/api/administrator_assign_subject.php">Assign subject / teacher / classroom</a>
                <a href="/api/administrator_create_schedule.php">Create Schedule</a>
                <a href="/api/administrator_view_schedule.php" class="active">View Schedule</a>
                <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
                <a href="/api/administrator_update_schedule.php">Update Schedule</a>
                <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
            </nav>

            <!-- Fix: logout with localStorage clear -->
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </aside>

        <main class="main">
            <h2>View Schedule</h2>

            <form method="GET" action="">
                <div class="filters">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search by subject, teacher, or classroom..."
                        value="<?php echo htmlspecialchars($searchTerm); ?>">

                    <div class="filter-row">
                        <div>
                            <label>Filter by Subject</label>
                            <select name="filter_subject">
                                <option value="all">All Subjects</option>
                                <?php foreach ($allSubjects as $subj): ?>
                                    <option value="<?php echo htmlspecialchars($subj); ?>"
                                        <?php echo ($filterSubject === $subj) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subj); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Filter by Teacher</label>
                            <select name="filter_teacher">
                                <option value="all">All Teachers</option>
                                <?php foreach ($allTeachers as $teach): ?>
                                    <option value="<?php echo htmlspecialchars($teach); ?>"
                                        <?php echo ($filterTeacher === $teach) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teach); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Filter by Day</label>
                            <select name="filter_day">
                                <option value="all">All Days</option>
                                <?php foreach ($allDays as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d); ?>"
                                        <?php echo ($filterDay === $d) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="filter-btn">Apply Filters</button>
                    <button type="button" class="filter-btn"
                        onclick="window.location.href='/api/administrator_view_schedule.php'">
                        Clear Filters
                    </button>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Classroom</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredSchedules)): ?>
                        <tr>
                            <td colspan="6" class="no-data">No schedules found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredSchedules as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['course_code'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['teacher_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['room_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['day'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['start_time'] . ' - ' . $s['end_time']); ?></td>
                                <td>
                                    <a href="/api/administrator_update_schedule.php?id=<?php echo $s['id']; ?>" class="edit">Edit</a> |
                                    <a href="/api/administrator_delete_schedule.php?id=<?php echo $s['id']; ?>" class="delete">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="footer-text">Showing <?php echo count($filteredSchedules); ?> schedules</p>
        </main>
    </div>

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