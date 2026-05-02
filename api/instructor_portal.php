<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Portal - PSU</title>
    <link rel="stylesheet" href="/instructor_style.css">
</head>

<body>
    <div class="top-bar"></div>

    <div class="container">
        <aside class="sidebar">

            <div class="brand-container">
                <img src="/PSU.png" alt="Logo" class="logo-img">
                <div class="brand-text">
                    <strong>Partido State University</strong>
                    <span>Goa, Camarines Sur</span>
                </div>
            </div>

            <div class="portal-title">Instructor Portal</div>

            <nav class="nav-menu">

                <button id="btn-dashboard"
                    class="nav-btn active"
                    onclick="showSection('dashboard')">
                    Dashboard
                </button>

                <button id="btn-assigned"
                    class="nav-btn"
                    onclick="showSection('assigned')">
                    View Assigned Class
                </button>

                <!-- Fix: Clear localStorage on logout -->
                <button class="btn-logout" onclick="
                    localStorage.removeItem('user_id');
                    localStorage.removeItem('role');
                    localStorage.removeItem('full_name');
                    window.location.href='/api/instructor_logout.php'
                ">Log Out</button>

            </nav>

        </aside>

        <main class="main-content">

            <div id="back-nav"
                class="back-arrow"
                style="visibility:hidden;"
                onclick="showSection('dashboard')">
                ←
            </div>

            <!-- DASHBOARD -->
            <section id="dashboard" class="content-section">

                <div class="page-title">Dashboard</div>

                <div class="welcome-box">
                    <h2>Welcome to Instructor Portal</h2>

                    <p>
                        Use the sidebar menu to navigate through your class schedules,
                        subject information, and classroom assignments.
                    </p>

                    <div class="stats-container">

                        <div class="card">
                            <span class="card-label">Assigned Classes</span>
                            <span id="assignedClasses" class="card-value">0</span>
                        </div>

                        <div class="card">
                            <span class="card-label">Total Subjects</span>
                            <span id="totalSubjects" class="card-value">0</span>
                        </div>

                        <div class="card">
                            <span class="card-label">Classrooms</span>
                            <span id="classrooms" class="card-value">0</span>
                        </div>

                    </div>
                </div>
            </section>

            <!-- ASSIGNED CLASS -->
            <section id="assigned" class="content-section">

                <div class="page-title">View Assigned Class</div>

                <div class="table-area-container">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Subject code</th>
                                <th>Subject name</th>
                                <th>Section</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                            </tr>
                        </thead>

                        <tbody id="assignedTable">
                            <tr>
                                <td colspan="6" style="text-align:center; color:gray;">
                                    Loading assigned classes...
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>
            </section>

        </main>
    </div>

    <script src="/instructor_script.js"></script>
</body>
</html>