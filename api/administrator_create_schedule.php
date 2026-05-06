<?php
session_start();
include 'db.php';

$message = '';
$messageType = '';

$created_by = $_SESSION['user_id'] ?? $_POST['user_id'] ?? null;

/* =========================
   LOAD DROPDOWNS FROM DB
========================= */
$subjectsResult = mysqli_query($conn, "SELECT * FROM subjects ORDER BY course_code");
$subjects = [];
while ($row = mysqli_fetch_assoc($subjectsResult)) {
    $subjects[] = $row;
}

$teachersResult = mysqli_query($conn, "
    SELECT i.instructor_id, u.full_name 
    FROM instructor i
    INNER JOIN users u ON i.user_id = u.user_id
    ORDER BY u.full_name
");

$roomsResult = mysqli_query($conn, "SELECT * FROM rooms ORDER BY room_name");

// Load course programs grouped by department
$courseProgramsResult = mysqli_query($conn, "SELECT * FROM course_program ORDER BY department, program_name");
$coursesByDepartment = [];
while ($row = mysqli_fetch_assoc($courseProgramsResult)) {
    $dept = $row['department'] ?? 'Other';
    $coursesByDepartment[$dept][] = $row['program_name'];
}

$departments = [
    'College of Business and Management',
    'College of Education',
    'College of Science',
    'College of Arts and Humanities',
    'College of Engineering and Computational Sciences'
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/* =========================
   POST HANDLER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create'])) {

        $created_by     = $_SESSION['user_id'] ?? $_POST['user_id'] ?? null;
        $subject        = mysqli_real_escape_string($conn, $_POST['subject']        ?? '');
        $teacher        = mysqli_real_escape_string($conn, $_POST['teacher']        ?? '');
        $classroom      = mysqli_real_escape_string($conn, $_POST['classroom']      ?? '');
        $semester       = mysqli_real_escape_string($conn, $_POST['semester']       ?? '');
        $course_program = mysqli_real_escape_string($conn, $_POST['course_program'] ?? '');
        $department     = mysqli_real_escape_string($conn, $_POST['department']     ?? '');
        $daysData       = $_POST['schedule_days'] ?? [];

        if (!$created_by) {
            $message = "Session expired. Please log in again.";
            $messageType = "error";

        } else {
            $checkUser = mysqli_query($conn, "SELECT user_id FROM users WHERE user_id = '$created_by'");

            if (mysqli_num_rows($checkUser) === 0) {
                $message = "Invalid user. Please log in again.";
                $messageType = "error";

            } elseif (empty($subject) || empty($teacher) || empty($classroom) || empty($semester) || empty($course_program) || empty($department)) {
                $message = "Please complete all fields.";
                $messageType = "error";

            } elseif (empty($daysData)) {
                $message = "Please add at least one day and time.";
                $messageType = "error";

            } else {
                // Save course_program to table if not exists
                $cpCheck = mysqli_query($conn, "SELECT id FROM course_program WHERE program_name = '$course_program'");
                if (mysqli_num_rows($cpCheck) === 0) {
                    mysqli_query($conn, "INSERT INTO course_program (program_name, department) VALUES ('$course_program', '$department')");
                }

                // Insert main schedule
                $sql = "INSERT INTO schedule (
                    subject_id, room_id, instructor_id,
                    day, start_time, end_time,
                    created_by, semester, course_program
                ) VALUES (
                    '$subject', '$classroom', '$teacher',
                    '', '', '',
                    '$created_by', '$semester', '$course_program'
                )";

                if (mysqli_query($conn, $sql)) {
                    $newScheduleId = mysqli_insert_id($conn);
                    $hasError = false;

                    foreach ($daysData as $entry) {
                        $d  = mysqli_real_escape_string($conn, $entry['day']   ?? '');
                        $st = mysqli_real_escape_string($conn, $entry['start'] ?? '');
                        $et = mysqli_real_escape_string($conn, $entry['end']   ?? '');

                        if (empty($d) || empty($st) || empty($et)) continue;

                        if ($st >= $et) {
                            $message = "End time must be after start time for $d.";
                            $messageType = "error";
                            $hasError = true;
                            break;
                        }

                        mysqli_query($conn, "
                            INSERT INTO schedule_days (schedule_id, day, start_time, end_time)
                            VALUES ($newScheduleId, '$d', '$st', '$et')
                        ");
                    }

                    if (!$hasError) {
                        $message = "Schedule created successfully!";
                        $messageType = "success";
                    }
                } else {
                    $message = "Error: " . mysqli_error($conn);
                    $messageType = "error";
                }
            }
        }
    }

    if (isset($_POST['clear'])) {
        header("Location: /api/administrator_create_schedule.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Schedule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .top-bar { position: fixed; top: 0; width: 100%; height: 45px; background: #0b0f3b; z-index: 1000; }
        body { padding-top: 45px; background: #fdfdfd; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background: #e9ecef; display: flex; flex-direction: column; padding: 25px 15px; border-right: 1px solid #dee2e6; overflow-y: auto; }
        .sidebar .header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .sidebar .logo { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        .sidebar .school-text h1 { font-size: 13px; font-weight: 700; color: #333; line-height: 1.2; }
        .sidebar .school-text p { font-size: 11px; color: #666; }
        .sidebar h2 { font-size: 18px; margin-bottom: 25px; color: #000; font-weight: 700; text-align: center; }
        .sidebar nav { display: flex; flex-direction: column; gap: 8px; }
        .sidebar nav a { text-decoration: none; background: #0a0a3c; color: white; padding: 12px 15px; border-radius: 4px; font-size: 18px; font-weight: 700; text-align: center; transition: background 0.2s; }
        .sidebar nav a:hover { background: #2a2a7c; }
        .sidebar nav a.active { background: #2a2a7c; box-shadow: inset 0 0 0 2px #fff; }
        .content { flex: 1; padding: 40px; overflow-y: auto; }
        .title { text-align: center; margin-bottom: 20px; font-size: 20px; font-weight: 700; }
        .form-box { background: #d9d9d9; padding: 25px; border-radius: 10px; width: 620px; margin: auto; }
        .form-box label { display: block; font-weight: 600; margin-bottom: 4px; color: #333; }
        .form-box select,
        .form-box input[type="text"],
        .form-box input[type="time"] { width: 100%; padding: 10px; margin: 4px 0 14px 0; border-radius: 6px; border: 1px solid #aaa; font-size: 14px; background: white; }
        .autocomplete-wrapper { position: relative; margin-bottom: 14px; }
        .autocomplete-wrapper input { margin-bottom: 0; width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #aaa; font-size: 14px; background: white; }
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #aaa; border-top: none; border-radius: 0 0 6px 6px; max-height: 180px; overflow-y: auto; z-index: 100; display: none; }
        .autocomplete-list div { padding: 10px; cursor: pointer; font-size: 14px; }
        .autocomplete-list div:hover { background: #e9ecef; }
        .no-courses-msg { font-size: 13px; color: #888; font-style: italic; margin-top: -10px; margin-bottom: 14px; }
        .day-entry { background: white; border-radius: 8px; padding: 15px; margin-bottom: 10px; position: relative; }
        .day-entry select,
        .day-entry input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #aaa; margin-bottom: 10px; font-size: 14px; }
        .time-row { display: flex; gap: 15px; }
        .time-row div { flex: 1; }
        .time-row label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px; }
        .remove-day-btn { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
        .remove-day-btn:hover { background: #a71d2a; }
        .add-day-btn { display: block; width: 100%; padding: 10px; background: #4a66a0; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; margin-bottom: 15px; }
        .add-day-btn:hover { background: #2a2a7c; }
        .btn-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        .btn-row button { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-row .create { background: #0a0a3c; color: white; }
        .btn-row .create:hover { background: #3f5191; }
        .btn-row .clear { background: #ccc; color: #333; }
        .message { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
        .btn-logout:hover { background-color: #d32f2f; }
        .section-label { font-size: 13px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
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
            <a href="/api/administrator_create_schedule.php" class="active">Create Schedule</a>
            <a href="/api/administrator_view_schedule.php">View Schedule</a>
            <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </nav>
    </aside>

    <div class="content">
        <h2 class="title">Create Schedule</h2>
        <div class="form-box">

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="scheduleForm">
                <input type="hidden" name="user_id" id="user_id_field">

                <!-- SUBJECT -->
                <label>Subject</label>
                <select name="subject" required>
                    <option value="">Select a subject</option>
                    <?php foreach ($subjects as $subj): ?>
                        <option value="<?php echo $subj['id']; ?>">
                            <?php echo htmlspecialchars($subj['course_code'] . " - " . $subj['description']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- TEACHER -->
                <label>Teacher</label>
                <select name="teacher" required>
                    <option value="">Select a teacher</option>
                    <?php while ($teach = mysqli_fetch_assoc($teachersResult)): ?>
                        <option value="<?php echo $teach['instructor_id']; ?>">
                            <?php echo htmlspecialchars($teach['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <!-- DEPARTMENT -->
                <label>Department</label>
                <select name="department" id="departmentSelect" required onchange="filterCourses()">
                    <option value="">Select a department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>">
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- COURSE / PROGRAM -->
                <label>Course / Program</label>
                <div class="autocomplete-wrapper">
                    <input type="text" name="course_program" id="courseProgramInput"
                        placeholder="Select department first..." autocomplete="off" required disabled>
                    <div class="autocomplete-list" id="courseProgramList"></div>
                </div>
                <p class="no-courses-msg" id="noCoursesMsg" style="display:none;">
                    No courses yet for this department. You can type one manually.
                </p>

                <!-- CLASSROOM -->
                <label>Classroom</label>
                <select name="classroom" required>
                    <option value="">Select a room</option>
                    <?php while ($room = mysqli_fetch_assoc($roomsResult)): ?>
                        <option value="<?php echo $room['id']; ?>">
                            <?php echo htmlspecialchars($room['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <!-- SEMESTER -->
                <label>Semester</label>
                <select name="semester" required>
                    <option value="">Select semester</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                </select>

                <!-- DAYS & TIMES -->
                <span class="section-label" style="margin-top: 6px;">Schedule Days & Times</span>
                <div id="daysContainer"></div>
                <button type="button" class="add-day-btn" onclick="addDayEntry()">+ Add Day</button>

                <div class="btn-row">
                    <button type="submit" name="clear" class="clear">Clear</button>
                    <button type="submit" name="create" class="create">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('user_id_field').value = localStorage.getItem('user_id') || '';

        // ---- COURSE/PROGRAM DATA FROM PHP ----
        const coursesByDepartment = <?php echo json_encode($coursesByDepartment); ?>;

        const cpInput  = document.getElementById('courseProgramInput');
        const cpList   = document.getElementById('courseProgramList');
        const deptSelect = document.getElementById('departmentSelect');
        const noCoursesMsg = document.getElementById('noCoursesMsg');

        let currentCourses = [];

        function filterCourses() {
            const dept = deptSelect.value;
            cpInput.value = '';
            cpList.innerHTML = '';
            cpList.style.display = 'none';

            if (!dept) {
                cpInput.disabled = true;
                cpInput.placeholder = 'Select department first...';
                noCoursesMsg.style.display = 'none';
                return;
            }

            currentCourses = coursesByDepartment[dept] || [];
            cpInput.disabled = false;

            if (currentCourses.length === 0) {
                cpInput.placeholder = 'Type course manually...';
                noCoursesMsg.style.display = 'block';
            } else {
                cpInput.placeholder = 'Type to search course...';
                noCoursesMsg.style.display = 'none';
            }
        }

        cpInput.addEventListener('input', function () {
            const val = this.value.trim().toLowerCase();
            cpList.innerHTML = '';

            if (!val || currentCourses.length === 0) {
                cpList.style.display = 'none';
                return;
            }

            const matches = currentCourses.filter(p => p.toLowerCase().includes(val));
            if (matches.length === 0) {
                cpList.style.display = 'none';
                return;
            }

            matches.forEach(p => {
                const div = document.createElement('div');
                div.textContent = p;
                div.onclick = () => {
                    cpInput.value = p;
                    cpList.style.display = 'none';
                };
                cpList.appendChild(div);
            });
            cpList.style.display = 'block';
        });

        // Show all courses on focus
        cpInput.addEventListener('focus', function () {
            if (currentCourses.length === 0) return;
            const val = this.value.trim().toLowerCase();
            if (val) return;

            cpList.innerHTML = '';
            currentCourses.forEach(p => {
                const div = document.createElement('div');
                div.textContent = p;
                div.onclick = () => {
                    cpInput.value = p;
                    cpList.style.display = 'none';
                };
                cpList.appendChild(div);
            });
            cpList.style.display = 'block';
        });

        document.addEventListener('click', function (e) {
            if (!cpInput.contains(e.target) && !cpList.contains(e.target)) {
                cpList.style.display = 'none';
            }
        });

        // ---- DAYS & TIMES ----
        const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        let dayCount = 0;

        function addDayEntry() {
            dayCount++;
            const index = dayCount - 1;
            const container = document.getElementById('daysContainer');

            const div = document.createElement('div');
            div.className = 'day-entry';
            div.id = 'day-entry-' + index;

            div.innerHTML = `
                <button type="button" class="remove-day-btn" onclick="removeDayEntry(${index})">✕ Remove</button>
                <label>Day</label>
                <select name="schedule_days[${index}][day]" required>
                    <option value="">Select a day</option>
                    ${days.map(d => `<option value="${d}">${d}</option>`).join('')}
                </select>
                <div class="time-row">
                    <div>
                        <label>Start Time</label>
                        <input type="time" name="schedule_days[${index}][start]" required>
                    </div>
                    <div>
                        <label>End Time</label>
                        <input type="time" name="schedule_days[${index}][end]" required>
                    </div>
                </div>
            `;

            container.appendChild(div);
        }

        function removeDayEntry(index) {
            const el = document.getElementById('day-entry-' + index);
            if (el) el.remove();
        }

        // Add one day row by default
        addDayEntry();

        // ---- LOGOUT ----
        function logout() {
            localStorage.removeItem('user_id');
            localStorage.removeItem('role');
            localStorage.removeItem('full_name');
            window.location.href = '/api/administrator_logout.php';
        }
    </script>
</body>
</html>