document.addEventListener("DOMContentLoaded", function () {
    showSection('dashboard');
});

function showSection(sectionId) {

    document.querySelectorAll('.content-section')
        .forEach(sec => sec.classList.remove('active'));

    const activeSection = document.getElementById(sectionId);
    if (activeSection) activeSection.classList.add('active');

    document.querySelectorAll('.nav-btn')
        .forEach(btn => btn.classList.remove('active'));

    if (sectionId === 'dashboard') {
        document.getElementById('btn-dashboard')?.classList.add('active');
        document.getElementById('back-nav').style.visibility = 'hidden';
        loadDashboard();
    }

    else if (sectionId === 'assigned') {
        document.getElementById('btn-assigned')?.classList.add('active');
        document.getElementById('back-nav').style.visibility = 'visible';
        loadAssignedClasses();
    }
}

function loadDashboard() {
    // Fix: absolute path for API call
    fetch("/api/instructor_dashboard.php")
        .then(res => res.json())
        .then(data => {
            document.getElementById("assignedClasses").innerText =
                data.assignedClasses ?? 0;
            document.getElementById("totalSubjects").innerText =
                data.totalSubjects ?? 0;
            document.getElementById("classrooms").innerText =
                data.classrooms ?? 0;
        })
        .catch(err => console.error(err));
}

function loadAssignedClasses() {
    // Fix: absolute path for API call
    // Get instructor user_id from localStorage
    const userId = localStorage.getItem('user_id');

    fetch("/api/instructor_get_classes.php?user_id=" + userId)
        .then(res => res.json())
        .then(data => {
            let html = "";

            if (!data || data.length === 0) {
                html = `
                    <tr>
                        <td colspan="6" style="text-align:center;">
                            No assigned classes found
                        </td>
                    </tr>
                `;
            } else {
                data.forEach(row => {
                    html += `
                        <tr>
                            <td>${row.code || ''}</td>
                            <td>${row.name || ''}</td>
                            <td>${row.section || ''}</td>
                            <td>${row.day || ''}</td>
                            <td>${row.start_time || ''} - ${row.end_time || ''}</td>
                            <td>${row.room_name || ''}</td>
                        </tr>
                    `;
                });
            }

            document.getElementById("assignedTable").innerHTML = html;
        })
        .catch(err => console.error(err));
}