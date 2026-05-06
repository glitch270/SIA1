<?php
session_start();
include 'administrator_db.php';

$searchTerm    = trim($_GET['search'] ?? '');
$filterSubject = $_GET['filter_subject'] ?? 'all';
$filterRoom    = $_GET['filter_room'] ?? 'all';
$filterTeacher = $_GET['filter_teacher'] ?? 'all';
$filterSemester = $_GET['filter_semester'] ?? 'all';

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
        OR s.course_program LIKE '%$searchTerm%'
        OR s.semester LIKE '%$searchTerm%'
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

if ($filterSemester !== 'all') {
    $filterSemester = mysqli_real_escape_string($conn, $filterSemester);
    $sql .= " AND s.semester = '$filterSemester'";
}

$result = mysqli_query($conn, $sql);
$filteredSchedules = [];
while ($row = mysqli_fetch_assoc($result)) {
    $filteredSchedules[] = $row;
}

// Load schedule_days for each schedule
foreach ($filteredSchedules as &$sched) {
    $sid = $sched['id'];
    $daysRes = mysqli_query($conn, "SELECT * FROM schedule_days WHERE schedule_id = $sid ORDER BY id");
    $sched['days'] = [];
    while ($d = mysqli_fetch_assoc($daysRes)) {
        $sched['days'][] = $d;
    }
}
unset($sched);

$allSubjects = [];
$res = mysqli_query($conn, "SELECT course_code FROM subjects ORDER BY course_code");
while ($r = mysqli_fetch_assoc($res)) $allSubjects[] = $r['course_code'];

$allRooms = [];
$res = mysqli_query($conn, "SELECT room_name FROM rooms ORDER BY room_name");
while ($r = mysqli_fetch_assoc($res)) $allRooms[] = $r['room_name'];

$allTeachers = [];
$res = mysqli_query($conn, "SELECT u.full_name FROM instructor i JOIN users u ON i.user_id = u.user_id ORDER BY u.full_name");
while ($r = mysqli_fetch_assoc($res)) $allTeachers[] = $r['full_name'];
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
        .sidebar { width: 280px; background: #e9ecef; display: flex; flex-direction: column; padding: 25px 15px; border-right: 1px solid #dee2e6; overflow-y: auto; }
        .sidebar .header { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
        .sidebar .logo { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        .sidebar .school-text h1 { font-size: 13px; font-weight: 700; color: #333; line-height: 1.2; }
        .sidebar .school-text p { font-size: 11px; color: #666; }
        .sidebar h2 { font-size: 18px; margin-bottom: 20px; color: #000; font-weight: 700; text-align: center; }
        .sidebar nav { display: flex; flex-direction: column; gap: 8px; }
        .sidebar nav a { text-decoration: none; background: #0a0a3c; color: white; padding: 12px 15px; border-radius: 4px; font-size: 18px; font-weight: 700; text-align: center; transition: background 0.2s; }
        .sidebar nav a:hover { background: #2a2a7c; }
        .sidebar nav a.active { background: #2a2a7c; box-shadow: inset 0 0 0 2px #fff; }
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .main h2 { font-size: 20px; margin-bottom: 20px; color: #000; font-weight: 700; }
        .filters { background: #eee; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .filters input { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ccc; }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .filter-row div { flex: 1; min-width: 150px; }
        .filter-row label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
        .filter-row select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead { background: #ddd; }
        th, td { padding: 10px 12px; text-align: left; }
        tbody tr { border-bottom: 1px solid #ccc; }
        tbody tr:hover { background: #f9f9f9; }
        .edit { color: green; cursor: pointer; text-decoration: none; }
        .edit:hover { text-decoration: underline; }
        .delete { color: red; cursor: pointer; text-decoration: none; }
        .delete:hover { text-decoration: underline; }
        .footer-text { margin-top: 10px; font-size: 13px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #666; font-style: italic; }
        .filter-btn { padding: 8px 20px; background: #0a0a3c; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; font-size: 13px; }
        .filter-btn:hover { background: #2a2a7c; }
        .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
        .btn-logout:hover { background-color: #d32f2f; }
        .days-list { margin: 0; padding: 0; list-style: none; }
        .days-list li { font-size: 13px; color: #444; }
        .badge-semester { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #0a0a3c; color: white; }
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
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </aside>

        <main class="main">
            <h2>View Schedule</h2>

            <form method="GET" action="">
                <div class="filters">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search by subject, teacher, course, semester..."
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
                            <label>Filter by Semester</label>
                            <select name="filter_semester">
                                <option value="all">All Semesters</option>
                                <option value="1st Semester" <?php echo $filterSemester === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd Semester" <?php echo $filterSemester === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
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
                        <th>Course / Program</th>
                        <th>Semester</th>
                        <th>Classroom</th>
                        <th>Days & Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredSchedules)): ?>
                        <tr>
                            <td colspan="7" class="no-data">No schedules found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredSchedules as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['course_code'] ?? 'N/A'); ?><br>
                                    <small style="color:#666;"><?php echo htmlspecialchars($s['description'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($s['teacher_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['course_program'] ?? 'N/A'); ?></td>
                                <td><span class="badge-semester"><?php echo htmlspecialchars($s['semester'] ?? 'N/A'); ?></span></td>
                                <td><?php echo htmlspecialchars($s['room_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($s['days'])): ?>
                                        <ul class="days-list">
                                            <?php foreach ($s['days'] as $day): ?>
                                                <li>
                                                    <?php
                                                        $st = $day['start_time'] ? date("h:i A", strtotime($day['start_time'])) : '';
                                                        $et = $day['end_time']   ? date("h:i A", strtotime($day['end_time']))   : '';
                                                        echo htmlspecialchars($day['day'] . ": " . $st . " - " . $et);
                                                    ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span style="color:#999;">No days set</span>
                                    <?php endif; ?>
                                </td>
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