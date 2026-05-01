<?php
session_start();
include 'administrator_db.php';

$message = '';
$messageType = '';
$selectedSchedule = null;

/* =========================
   DROPDOWNS (FIXED)
========================= */

// SUBJECTS
$subjects = [];
$res = mysqli_query($conn, "SELECT id, course_code FROM subjects");
while ($row = mysqli_fetch_assoc($res)) {
    $subjects[] = $row;
}

// ROOMS
$classrooms = [];
$res = mysqli_query($conn, "SELECT id, room_name FROM rooms");
while ($row = mysqli_fetch_assoc($res)) {
    $classrooms[] = $row;
}

// TEACHERS (REAL)
$teachers = [];
$res = mysqli_query($conn, "
    SELECT i.instructor_id, u.full_name
    FROM instructor i
    JOIN users u ON i.user_id = u.user_id
");
while ($row = mysqli_fetch_assoc($res)) {
    $teachers[] = $row;
}

// DAYS
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/* =========================
   GET SCHEDULE (FIXED JOIN)
========================= */

if (isset($_GET['id'])) {

    $id = mysqli_real_escape_string($conn, $_GET['id']);

    $result = mysqli_query($conn, "
        SELECT s.*
        FROM schedule s
        WHERE s.id = '$id'
    ");

    if ($result && mysqli_num_rows($result) > 0) {

        $selectedSchedule = mysqli_fetch_assoc($result);

        $selectedSchedule['subject'] = $selectedSchedule['subject_id'];
        $selectedSchedule['classroom'] = $selectedSchedule['room_id'];
        $selectedSchedule['teacher'] = $selectedSchedule['instructor_id'];

    } else {
        $message = "Schedule not found.";
        $messageType = "error";
    }
}

/* =========================
   UPDATE SCHEDULE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $scheduleId = $_POST['schedule_id'] ?? '';

    $subject = $_POST['subject'] ?? '';
    $teacher = $_POST['teacher'] ?? '';
    $day = $_POST['day'] ?? '';
    $classroom = $_POST['classroom'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    if ($scheduleId == '' || $subject == '' || $teacher == '' || $day == '' || $classroom == '') {
        $message = "Please complete all fields.";
        $messageType = "error";

    } elseif ($startTime >= $endTime) {
        $message = "Invalid time range.";
        $messageType = "error";

    } else {

        $update = mysqli_query($conn, "
            UPDATE schedule SET
                subject_id = '$subject',
                instructor_id = '$teacher',
                room_id = '$classroom',
                day = '$day',
                start_time = '$startTime',
                end_time = '$endTime'
            WHERE id = '$scheduleId'
        ");

        if ($update) {
            $message = "Schedule updated successfully!";
            $messageType = "success";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Update Schedule</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .top-bar {
            position: fixed;
            top: 0;
            width: 100%;
            height: 45px;
            background: #0b0f3b;
        }

        .container {
            margin-top: 45px;
        }

        body {
            padding-top: 45px;
            background: #fdfdfd;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 280px;
            background: #e9ecef;
            display: flex;
            flex-direction: column;
            padding: 25px 15px;
            border-right: 1px solid #dee2e6;
        }

        .sidebar .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .sidebar .logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
        }

        .sidebar .school-text h1 {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }

        .sidebar .school-text p {
            font-size: 11px;
            color: #666;
        }

        .sidebar h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #000;
            font-weight: 700;
            text-align: center;
            padding-left: 0;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar nav a {
            text-decoration: none;
            background: #0a0a3c;
            color: white;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            transition: background 0.2s ease-in-out;
        }

        .sidebar nav a:hover {
            background: #2a2a7c;
            transform: none;
        }

        .sidebar nav a.active {
            background: #2a2a7c;
            box-shadow: inset 0 0 0 2px #fff;
        }

        .main {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .main h2 {
            font-size: 20px;
            margin-bottom: 25px;
            color: #000;
            font-weight: 700;
        }

        .update-box {
            background: #e9ecef;
            border-radius: 8px;
            padding: 50px 40px;
            text-align: left;
            color: #555;
            max-width: 1000px;
        }

        .update-box p {
            font-size: 16px;
            margin-bottom: 30px;
            color: #333;
        }

        .btn-back {
            display: inline-block;
            background: #cccccc;
            color: #333;
            padding: 10px 35px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background 0.2s;
        }

        .btn-back:hover {
            background: #bbbbbb;
        }

        /* Form styles */
        .form-box {
            background: #d9d9d9;
            padding: 25px;
            border-radius: 10px;
            max-width: 600px;
        }

        .form-box label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-box select,
        .form-box input {
            width: 100%;
            padding: 10px;
            margin: 8px 0 15px 0;
            border-radius: 6px;
            border: 1px solid #aaa;
        }

        .time-row {
            display: flex;
            gap: 20px;
        }

        .time-row div {
            flex: 1;
        }

        .btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-row button,
        .btn-row a {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-row .update-btn {
            background: #5a6fb3;
            color: white;
        }

        .btn-row .update-btn:hover {
            background: #3f5191;
        }

        .btn-row .cancel-btn {
            background: #ccc;
            color: #333;
        }

        .message {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-logout {
            background-color: #1e235e; 
            color: #fff;
            border: none;
            padding: 12px 3px;
            border-radius: 5px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            text-align: center;
            margin-top: 30px;
            transition: 0.3s; 
        }

    .btn-logout:hover {
        background-color: #d32f2f; 
    }
    </style>
</head>

<body>
    <div class="top-bar"></div>
    <aside class="sidebar">
        <div class="header">
            <img src="PSU.png" alt="University Logo" class="logo">
            <div class="school-text">
                <h1>Partido State University</h1>
                <p>Goa, Camarines Sur</p>
            </div>
        </div>

        <h2>Schedule Management</h2>

        <nav id="sidebarNav">
            <a href="administrator_assign_subject.php">Assign subject / teacher / classroom</a>
            <a href="administrator_create_schedule.php">Create Schedule</a>
            <a href="administrator_view_schedule.php">View Schedule</a>
            <a href="administrator_validate_schedule.php">Validate Schedule</a>
            <a href="administrator_update_schedule.php" class="active">Update Schedule</a>
            <a href="administrator_delete_schedule.php">Delete Schedule</a>
        </nav>

      
          <button class="btn-logout" onclick="window.location.href='administrator_logout.php'">Log Out</button>
      
    </aside>


    <main class="main">
        <h2>Update Schedule</h2>

        <?php if ($selectedSchedule === null): ?>
            <div class="update-box">
                <p>No schedule selected. Please select a schedule from the View Schedule page.</p>
                <a href="administrator_view_schedule.php" class="btn-back">Back</a>
            </div>
        <?php else: ?>
            <div class="form-box">
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($selectedSchedule['id']); ?>">

                    <label>Subject</label>
                    <select name="subject">
<?php foreach ($subjects as $subj): ?>
    <option value="<?php echo $subj['id']; ?>"
        <?php echo ($selectedSchedule['subject'] == $subj['id']) ? 'selected' : ''; ?>>
        <?php echo $subj['course_code']; ?>
    </option>
<?php endforeach; ?>
</select>

                   <select name="teacher">
<?php foreach ($teachers as $t): ?>
    <option value="<?php echo $t['instructor_id']; ?>"
        <?php echo ($selectedSchedule['teacher'] == $t['instructor_id']) ? 'selected' : ''; ?>>
        <?php echo $t['full_name']; ?>
    </option>
<?php endforeach; ?>
</select>

                    <label>Day</label>
                    <select name="day">
                        <option>Select a day</option>
                        <?php foreach ($days as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $selectedSchedule['day'] === $d ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="classroom">
<?php foreach ($classrooms as $r): ?>
    <option value="<?php echo $r['id']; ?>"
        <?php echo ($selectedSchedule['classroom'] == $r['id']) ? 'selected' : ''; ?>>
        <?php echo $r['room_name']; ?>
    </option>
<?php endforeach; ?>
</select>

                    <div class="time-row">
                        <div>
                            <label>Start Time</label>
<input type="time" name="start_time"
value="<?php echo htmlspecialchars($selectedSchedule['start_time'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>End Time</label>
<input type="time" name="end_time"
value="<?php echo htmlspecialchars($selectedSchedule['end_time'] ?? ''); ?>">                        </div>
                    </div>

                    <div class="btn-row">
                        <a href="administrator_view_schedule.php" class="cancel-btn">Cancel</a>
                        <button type="submit" name="update" class="update-btn">Update Schedule</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>
