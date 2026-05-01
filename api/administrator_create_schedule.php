<?php
session_start();
include 'db.php';

$message = '';
$messageType = '';

// Fix: Get user_id from SESSION or POST (for Vercel serverless)
$created_by = $_SESSION['user_id'] ?? $_POST['user_id'] ?? null;

/* =========================
   LOAD DROPDOWNS FROM DB
========================= */
$subjectsResult = mysqli_query($conn, "SELECT * FROM subjects");

$teachersResult = mysqli_query($conn, "
    SELECT i.instructor_id, u.full_name 
    FROM instructor i
    INNER JOIN users u ON i.user_id = u.user_id
");

$roomsResult = mysqli_query($conn, "SELECT * FROM rooms");

$sectionsResult = mysqli_query($conn, "SELECT DISTINCT section FROM student_profile WHERE section IS NOT NULL ORDER BY section");

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/* =========================
   POST HANDLER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create'])) {

        $subject   = mysqli_real_escape_string($conn, $_POST['subject']    ?? '');
        $teacher   = mysqli_real_escape_string($conn, $_POST['teacher']    ?? '');
        $day       = mysqli_real_escape_string($conn, $_POST['day']        ?? '');
        $classroom = mysqli_real_escape_string($conn, $_POST['classroom']  ?? '');
        $startTime = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
        $endTime   = mysqli_real_escape_string($conn, $_POST['end_time']   ?? '');
        $section   = mysqli_real_escape_string($conn, $_POST['section']    ?? '');

        if (!$created_by) {
            $message = "You must be logged in.";
            $messageType = "error";

        } elseif (empty($subject) || empty($teacher) || empty($day) || empty($classroom) || empty($startTime) || empty($endTime) || empty($section)) {
            $message = "Please complete all fields.";
            $messageType = "error";

        } elseif ($startTime >= $endTime) {
            $message = "End time must be after start time.";
            $messageType = "error";

        } else {

            $sql = "INSERT INTO schedule (
                subject_id, room_id, instructor_id, day,
                start_time, end_time, created_by, section
            ) VALUES (
                '$subject', '$classroom', '$teacher', '$day',
                '$startTime', '$endTime', '$created_by', '$section'
            )";

            if (mysqli_query($conn, $sql)) {
                $newScheduleId = mysqli_insert_id($conn);

                $enrollSql = "
                    INSERT INTO enrollment (user_id, schedule_id)
                    SELECT sp.user_id, $newScheduleId
                    FROM student_profile sp
                    WHERE sp.section = '$section'
                ";
                mysqli_query($conn, $enrollSql);

                $message = "Schedule created successfully!";
                $messageType = "success";
            } else {
                $message = "Error: " . mysqli_error($conn);
                $messageType = "error";
            }
        }
    }

    if (isset($_POST['clear'])) {
        header("Location: administrator_create_schedule.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .top-bar { position: fixed; top: 0; width: 100%; height: 45px; background: #0b0f3b; }
    body { padding-top: 40px; background: #fdfdfd; display: flex; height: 100vh; overflow: hidden; }
    .sidebar { width: 280px; background: #e9ecef; display: flex; flex-direction: column; padding: 25px 15px; border-right: 1px solid #dee2e6; }
    .sidebar .header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
    .sidebar .logo { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-top: 5px; }
    .sidebar .school-text h1 { font-size: 13px; font-weight: 700; color: #333; line-height: 1.2; margin-top: -5px; }
    .sidebar .school-text p { font-size: 11px; color: #666; margin-top: 1px; }
    .sidebar h2 { font-size: 18px; margin-bottom: 25px; color: #000; font-weight: 700; text-align: center; }
    .sidebar nav { display: flex; flex-direction: column; gap: 8px; margin-top: -5px; }
    .sidebar nav a { text-decoration: none; background: #0a0a3c; color: white; padding: 12px 15px; border-radius: 4px; font-size: 18px; font-weight: 700; text-align: center; transition: background 0.2s; }
    .sidebar nav a:hover { background: #2a2a7c; }
    .sidebar nav a.active { background: #2a2a7c; box-shadow: inset 0 0 0 2px #fff; }
    .content { flex: 1; padding: 40px; overflow-y: auto; }
    .container { display: flex; height: calc(100vh - 45px); }
    .title { text-align: center; margin-bottom: 20px; }
    .form-box { background: #d9d9d9; padding: 25px; border-radius: 10px; width: 600px; margin: auto; }
    .form-box select, .form-box input { width: 100%; padding: 10px; margin: 8px 0 15px 0; border-radius: 6px; border: 1px solid #aaa; }
    .time-row { display: flex; gap: 20px; }
    .time-row div { flex: 1; }
    .btn-row { display: flex; justify-content: flex-end; gap: 10px; }
    .btn-row button { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; }
    .btn-row .create { background: #0a0a3c; color: white; }
    .btn-row .create:hover { background: #3f5191; }
    .btn-row .clear { background: #ccc; }
    .message { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-weight: 500; }
    .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .btn-logout { background-color: #1e235e; color: #fff; border: none; padding: 12px 3px; border-radius: 5px; font-weight: 700; cursor: pointer; width: 100%; text-align: center; margin-top: 30px; transition: 0.3s; }
    .btn-logout:hover { background-color: #d32f2f; }
</style>
<head><title>Dashboard - Create Schedule</title></head>
<body>
    <div class="top-bar"></div>
    <aside class="sidebar">
        <div class="header">
            <!-- Fix: Use absolute path for image -->
            <img src="/PSU.png" alt="University Logo" class="logo">
            <div class="school-text">
                <h1>Partido State University</h1>
                <p>Goa, Camarines Sur</p>
            </div>
        </div>
        <h2>Schedule Management</h2>
        <nav id="sidebarNav">
            <!-- Fix: Use absolute paths for links -->
            <a href="/api/administrator_assign_subject.php">Assign subject / teacher / classroom</a>
            <a href="/api/administrator_create_schedule.php" class="active">Create Schedule</a>
            <a href="/api/administrator_view_schedule.php">View Schedule</a>
            <a href="/api/administrator_validate_schedule.php">Validate Schedule</a>
            <a href="/api/administrator_update_schedule.php">Update Schedule</a>
            <a href="/api/administrator_delete_schedule.php">Delete Schedule</a>
            <button class="btn-logout" onclick="window.location.href='/api/administrator_logout.php'">Log Out</button>
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

            <!-- Fix: Add user_id hidden field for session workaround -->
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="user_id_field">
                <script>
                    document.getElementById('user_id_field').value = localStorage.getItem('user_id') || '';
                </script>

                <label>Subject</label>
                <select name="subject" required>
                    <option value="">Select a subject</option>
                    <?php while ($subj = mysqli_fetch_assoc($subjectsResult)): ?>
                        <option value="<?php echo $subj['id']; ?>">
                            <?php echo htmlspecialchars($subj['course_code'] . " - " . $subj['description']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Teacher</label>
                <select name="teacher" required>
                    <option value="">Select a teacher</option>
                    <?php while ($teach = mysqli_fetch_assoc($teachersResult)): ?>
                        <option value="<?php echo $teach['instructor_id']; ?>">
                            <?php echo htmlspecialchars($teach['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Section</label>
                <select name="section" required>
                    <option value="">Select a section</option>
                    <?php while ($sec = mysqli_fetch_assoc($sectionsResult)): ?>
                        <option value="<?php echo htmlspecialchars($sec['section']); ?>">
                            <?php echo htmlspecialchars($sec['section']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Day</label>
                <select name="day" required>
                    <option value="">Select a day</option>
                    <?php foreach ($days as $d): ?>
                        <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Classroom</label>
                <select name="classroom" required>
                    <option value="">Select a room</option>
                    <?php while ($room = mysqli_fetch_assoc($roomsResult)): ?>
                        <option value="<?php echo $room['id']; ?>">
                            <?php echo htmlspecialchars($room['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div class="time-row">
                    <div>
                        <label>Start Time</label>
                        <input type="time" name="start_time" required>
                    </div>
                    <div>
                        <label>End Time</label>
                        <input type="time" name="end_time" required>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" name="clear" class="clear">Clear</button>
                    <button type="submit" name="create" class="create">Create Schedule</button>
                </div>

            </form>
        </div>
    </div>
</body>
</html>