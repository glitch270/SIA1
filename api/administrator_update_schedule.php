<?php
session_start();
include 'administrator_db.php';

$message = '';
$messageType = '';
$selectedSchedule = null;
$existingDays = [];

$subjects = [];
$res = mysqli_query($conn, "SELECT id, course_code, description FROM subjects ORDER BY course_code");
while ($row = mysqli_fetch_assoc($res)) $subjects[] = $row;

$classrooms = [];
$res = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name");
while ($row = mysqli_fetch_assoc($res)) $classrooms[] = $row;

// Load teachers grouped by department
$teachersResult = mysqli_query($conn, "
    SELECT i.instructor_id, i.department, u.full_name 
    FROM instructor i
    INNER JOIN users u ON i.user_id = u.user_id
    ORDER BY i.department, u.full_name
");
$teachersByDepartment = [];
while ($row = mysqli_fetch_assoc($teachersResult)) {
    $dept = $row['department'] ?? 'Other';
    $teachersByDepartment[$dept][] = [
        'id'   => $row['instructor_id'],
        'name' => $row['full_name']
    ];
}

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

// Find the department of the current schedule's course_program
$currentDepartment = '';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = mysqli_query($conn, "SELECT s.* FROM schedule s WHERE s.id = '$id'");
    if ($result && mysqli_num_rows($result) > 0) {
        $selectedSchedule = mysqli_fetch_assoc($result);
        $daysRes = mysqli_query($conn, "SELECT * FROM schedule_days WHERE schedule_id = '$id' ORDER BY id");
        while ($d = mysqli_fetch_assoc($daysRes)) $existingDays[] = $d;

        // Find department from course_program table
        $cp = mysqli_real_escape_string($conn, $selectedSchedule['course_program'] ?? '');
        $deptRes = mysqli_query($conn, "SELECT department FROM course_program WHERE program_name = '$cp' LIMIT 1");
        if ($deptRes && mysqli_num_rows($deptRes) > 0) {
            $deptRow = mysqli_fetch_assoc($deptRes);
            $currentDepartment = $deptRow['department'] ?? '';
        }
    } else {
        $message = "Schedule not found.";
        $messageType = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $scheduleId     = mysqli_real_escape_string($conn, $_POST['schedule_id']    ?? '');
    $subject        = mysqli_real_escape_string($conn, $_POST['subject']        ?? '');
    $teacher        = mysqli_real_escape_string($conn, $_POST['teacher']        ?? '');
    $classroom      = mysqli_real_escape_string($conn, $_POST['classroom']      ?? '');
    $semester       = mysqli_real_escape_string($conn, $_POST['semester']       ?? '');
    $course_program = mysqli_real_escape_string($conn, $_POST['course_program'] ?? '');
    $department     = mysqli_real_escape_string($conn, $_POST['department']     ?? '');
    $daysData       = $_POST['schedule_days'] ?? [];

    if (empty($scheduleId) || empty($subject) || empty($teacher) || empty($classroom) || empty($semester) || empty($course_program)) {
        $message = "Please complete all fields.";
        $messageType = "error";
    } elseif (empty($daysData)) {
        $message = "Please add at least one day and time.";
        $messageType = "error";
    } else {
        $update = mysqli_query($conn, "
            UPDATE schedule SET
                subject_id = '$subject',
                instructor_id = '$teacher',
                room_id = '$classroom',
                semester = '$semester',
                course_program = '$course_program'
            WHERE id = '$scheduleId'
        ");

        if ($update) {
            mysqli_query($conn, "DELETE FROM schedule_days WHERE schedule_id = '$scheduleId'");

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
                    VALUES ('$scheduleId', '$d', '$st', '$et')
                ");
            }

            if (!$hasError) {
                $message = "Schedule updated successfully!";
                $messageType = "success";
                $existingDays = [];
                $daysRes = mysqli_query($conn, "SELECT * FROM schedule_days WHERE schedule_id = '$scheduleId' ORDER BY id");
                while ($d = mysqli_fetch_assoc($daysRes)) $existingDays[] = $d;
            }
        } else {
            $message = "Error: " . mysqli_error($conn);
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Schedule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .top-bar { position: fixed; top: 0; width: 100%; height: 45px; background: #0b0f3b; z-index: 1000; }
        body { padding-top: 45px; background: #fdfdfd; display: flex; height: 100vh; overflow: hidden; }
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
        .main { flex: 1; padding: 40px; overflow-y: auto; }
        .main h2 { font-size: 20px; margin-bottom: 25px; color: #000; font-weight: 700; }
        .update-box { background: #e9ecef; border-radius: 8px; padding: 50px 40px; max-width: 1000px; }
        .btn-back { display: inline-block; background: #ccc; color: #333; padding: 10px 35px; border-radius: 12px; text-decoration: none; font-size: 14px; font-weight: 600; }
        .form-box { background: #d9d9d9; padding: 25px; border-radius: 10px; max-width: 620px; }
        .form-box label { display: block; margin-bottom: 4px; font-weight: 600; color: #333; }
        .form-box select,
        .form-box input[type="text"],
        .form-box input[type="time"] { width: 100%; padding: 10px; margin: 4px 0 14px 0; border-radius: 6px; border: 1px solid #aaa; font-size: 14px; background: white; }
        .form-box select:disabled,
        .form-box input:disabled { background: #eee; cursor: not-allowed; }
        .autocomplete-wrapper { position: relative; margin-bottom: 14px; }
        .autocomplete-wrapper input { margin-bottom: 0; width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #aaa; font-size: 14px; background: white; }
        .autocomplete-wrapper input:disabled { background: #eee; cursor: not-allowed; }
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #aaa; border-top: none; border-radius: 0 0 6px 6px; max-height: 200px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .autocomplete-list div { padding: 10px; cursor: pointer; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .autocomplete-list div:last-child { border-bottom: none; }
        .autocomplete-list div:hover { background: #e9ecef; }
        .blocked-msg { font-size: 12px; color: #c0392b; font-style: italic; margin-top: -10px; margin-bottom: 14px; }
        .day-entry { background: white; border-radius: 8px; padding: 15px; margin-bottom: 10px; position: relative; }
        .day-entry select, .day-entry input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #aaa; margin-bottom: 10px; font-size: 14px; }
        .time-row { display: flex; gap: 15px; }
        .time-row div { flex: 1; }
        .time-row label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px; }
        .remove-day-btn { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
        .remove-day-btn:hover { background: #a71d2a; }
        .add-day-btn { display: block; width: 100%; padding: 10px; background: #4a66a0; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; margin-bottom: 15px; }
        .add-day-btn:hover { background: #2a2a7c; }
        .btn-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        .btn-row button, .btn-row a { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn-row .update-btn { background: #5a6fb3; color: white; }
        .btn-row .update-btn:hover { background: #3f5191; }
        .btn-row .cancel-btn { background: #ccc; color: #333; }
        .message { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; font-weight: 500; }
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
            <a href="/api/administrator_create_schedule.php">Create Schedule</a>
            <a href="/api/administrator_view_schedule.php">View Schedule</a>
            <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php" class="active">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
            <button class="btn-logout" onclick="logout()">Log Out</button>
        </nav>
    </aside>

    <main class="main">
        <h2>Update Schedule</h2>

        <?php if ($selectedSchedule === null): ?>
            <div class="update-box">
                <p>No schedule selected. Please select a schedule from the View Schedule page.</p>
                <a href="/api/administrator_view_schedule.php" class="btn-back">Back</a>
            </div>
        <?php else: ?>
            <div class="form-box">
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="updateForm">
                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($selectedSchedule['id']); ?>">

                    <!-- DEPARTMENT -->
                    <label>Department</label>
                    <select name="department" id="departmentSelect" required onchange="onDepartmentChange()">
                        <option value="">Select a department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"
                                <?php echo ($currentDepartment === $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- COURSE / PROGRAM -->
                    <label>Course / Program</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" name="course_program" id="courseProgramInput"
                            placeholder="Select department first..." autocomplete="off" required
                            value="<?php echo htmlspecialchars($selectedSchedule['course_program'] ?? ''); ?>">
                        <div class="autocomplete-list" id="courseProgramList"></div>
                    </div>
                    <p class="blocked-msg" id="noCoursesMsg" style="display:none;">
                        No courses yet for this department.
                    </p>

                    <!-- SUBJECT -->
                    <label>Subject</label>
                    <input type="hidden" name="subject" id="subjectHidden"
                        value="<?php echo htmlspecialchars($selectedSchedule['subject_id'] ?? ''); ?>">
                    <div class="autocomplete-wrapper">
                        <input type="text" id="subjectInput" autocomplete="off"
                            placeholder="Type subject code or name..."
                            value="<?php
                                foreach ($subjects as $subj) {
                                    if ($subj['id'] == $selectedSchedule['subject_id']) {
                                        echo htmlspecialchars($subj['course_code'] . ' - ' . $subj['description']);
                                        break;
                                    }
                                }
                            ?>">
                        <div class="autocomplete-list" id="subjectList"></div>
                    </div>

                    <!-- TEACHER -->
                    <label>Teacher</label>
                    <select name="teacher" id="teacherSelect" required>
                        <option value="">Select a teacher</option>
                    </select>

                    <!-- CLASSROOM -->
                    <label>Classroom</label>
                    <select name="classroom" required>
                        <?php foreach ($classrooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>"
                                <?php echo ($selectedSchedule['room_id'] == $r['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['room_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- SEMESTER -->
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="">Select semester</option>
                        <option value="1st Semester" <?php echo ($selectedSchedule['semester'] ?? '') === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                        <option value="2nd Semester" <?php echo ($selectedSchedule['semester'] ?? '') === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
                    </select>

                    <!-- DAYS & TIMES -->
                    <span class="section-label" style="margin-top:6px;">Schedule Days & Times</span>
                    <div id="daysContainer"></div>
                    <button type="button" class="add-day-btn" onclick="addDayEntry()">+ Add Day</button>

                    <div class="btn-row">
                        <a href="/api/administrator_view_schedule.php" class="cancel-btn">Cancel</a>
                        <button type="submit" name="update" class="update-btn">Update Schedule</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const coursesByDepartment  = <?php echo json_encode($coursesByDepartment); ?>;
        const teachersByDepartment = <?php echo json_encode($teachersByDepartment); ?>;
        const subjectData          = <?php echo json_encode($subjects); ?>;

        const deptSelect    = document.getElementById('departmentSelect');
        const cpInput       = document.getElementById('courseProgramInput');
        const cpList        = document.getElementById('courseProgramList');
        const noCoursesMsg  = document.getElementById('noCoursesMsg');
        const subjectInput  = document.getElementById('subjectInput');
        const subjectList   = document.getElementById('subjectList');
        const subjectHidden = document.getElementById('subjectHidden');
        const teacherSelect = document.getElementById('teacherSelect');

        let currentCourses = [];

        // Pre-fill teachers and courses based on current department on page load
        const currentDept    = <?php echo json_encode($currentDepartment); ?>;
        const currentTeacher = <?php echo json_encode($selectedSchedule['instructor_id'] ?? ''); ?>;

        function populateTeachers(dept, selectedId = null) {
            teacherSelect.innerHTML = '<option value="">Select a teacher</option>';
            const teachers = teachersByDepartment[dept] || Object.values(teachersByDepartment).flat();
            teachers.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                if (selectedId && t.id == selectedId) opt.selected = true;
                teacherSelect.appendChild(opt);
            });
        }

        function populateCourses(dept) {
            currentCourses = coursesByDepartment[dept] || [];
            if (currentCourses.length === 0) {
                cpInput.disabled = true;
                cpInput.placeholder = 'No courses available...';
                noCoursesMsg.style.display = 'block';
            } else {
                cpInput.disabled = false;
                cpInput.placeholder = 'Type to search course...';
                noCoursesMsg.style.display = 'none';
            }
        }

        // On page load, pre-fill department, courses, teachers
        if (currentDept) {
            populateCourses(currentDept);
            populateTeachers(currentDept, currentTeacher);
        }

        function onDepartmentChange() {
            const dept = deptSelect.value;
            cpInput.value = '';
            cpList.style.display = 'none';

            if (!dept) {
                cpInput.disabled = true;
                cpInput.placeholder = 'Select department first...';
                noCoursesMsg.style.display = 'none';
                teacherSelect.innerHTML = '<option value="">Select a teacher</option>';
                return;
            }

            populateCourses(dept);
            populateTeachers(dept);
        }

        // Course/Program autocomplete
        function showCourseMatches(val) {
            cpList.innerHTML = '';
            const matches = val
                ? currentCourses.filter(p => p.toLowerCase().includes(val))
                : currentCourses;
            if (matches.length === 0) { cpList.style.display = 'none'; return; }
            matches.forEach(p => {
                const div = document.createElement('div');
                div.textContent = p;
                div.onclick = () => { cpInput.value = p; cpList.style.display = 'none'; };
                cpList.appendChild(div);
            });
            cpList.style.display = 'block';
        }

        if (cpInput) {
            cpInput.addEventListener('input', function () { showCourseMatches(this.value.trim().toLowerCase()); });
            cpInput.addEventListener('focus', function () { showCourseMatches(this.value.trim().toLowerCase()); });
        }

        // Subject autocomplete
        function showSubjectMatches(val) {
            subjectList.innerHTML = '';
            const matches = val
                ? subjectData.filter(s =>
                    s.course_code.toLowerCase().includes(val) ||
                    s.description.toLowerCase().includes(val))
                : subjectData;
            if (matches.length === 0) { subjectList.style.display = 'none'; return; }
            matches.forEach(s => {
                const div = document.createElement('div');
                div.textContent = s.course_code + ' - ' + s.description;
                div.onclick = () => {
                    subjectInput.value  = s.course_code + ' - ' + s.description;
                    subjectHidden.value = s.id;
                    subjectList.style.display = 'none';
                };
                subjectList.appendChild(div);
            });
            subjectList.style.display = 'block';
        }

        if (subjectInput) {
            subjectInput.addEventListener('input', function () {
                subjectHidden.value = '';
                showSubjectMatches(this.value.trim().toLowerCase());
            });
            subjectInput.addEventListener('focus', function () {
                showSubjectMatches(this.value.trim().toLowerCase());
            });
        }

        // Close dropdowns on outside click
        document.addEventListener('click', function (e) {
            if (subjectInput && !subjectInput.contains(e.target) && !subjectList.contains(e.target)) subjectList.style.display = 'none';
            if (cpInput && !cpInput.contains(e.target) && !cpList.contains(e.target)) cpList.style.display = 'none';
        });

        // Days & Times
        const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        let dayCount = 0;

        function addDayEntry(existingDay = null, existingStart = null, existingEnd = null) {
            const index = dayCount++;
            const container = document.getElementById('daysContainer');
            const div = document.createElement('div');
            div.className = 'day-entry';
            div.id = 'day-entry-' + index;
            div.innerHTML = `
                <button type="button" class="remove-day-btn" onclick="removeDayEntry(${index})">✕ Remove</button>
                <label>Day</label>
                <select name="schedule_days[${index}][day]" required>
                    <option value="">Select a day</option>
                    ${days.map(d => `<option value="${d}" ${existingDay === d ? 'selected' : ''}>${d}</option>`).join('')}
                </select>
                <div class="time-row">
                    <div>
                        <label>Start Time</label>
                        <input type="time" name="schedule_days[${index}][start]" value="${existingStart || ''}" required>
                    </div>
                    <div>
                        <label>End Time</label>
                        <input type="time" name="schedule_days[${index}][end]" value="${existingEnd || ''}" required>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        function removeDayEntry(index) {
            const el = document.getElementById('day-entry-' + index);
            if (el) el.remove();
        }

        // Load existing days
        const existingDays = <?php echo json_encode($existingDays); ?>;
        if (existingDays.length > 0) {
            existingDays.forEach(d => addDayEntry(d.day, d.start_time, d.end_time));
        } else {
            addDayEntry();
        }

        // Form validation
        document.getElementById('updateForm').addEventListener('submit', function (e) {
            if (!subjectHidden.value) {
                e.preventDefault();
                alert('Please select a subject from the list.');
                subjectInput.focus();
            }
        });

        function logout() {
            if (confirm('Are you sure you want to log out?')) {
                localStorage.removeItem('user_id');
                localStorage.removeItem('role');
                localStorage.removeItem('full_name');
                window.location.href = '/api/administrator_logout.php';
            }
        }
    </script>
</body>
</html>