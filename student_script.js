document.addEventListener('DOMContentLoaded', () => {

    loadSchedule();
    loadStudentInfo();
    initButtons();
});


// ==================== LOAD SCHEDULE ====================
function loadSchedule() {

    fetch("student_get_schedule.php", {
        credentials: "include"
    })
    .then(res => res.json())
    .then(data => {

        console.log("SCHEDULE RESPONSE:", data);

        const tbody = document.querySelector("tbody");
        tbody.innerHTML = "";

        if (!Array.isArray(data)) {
            console.error("Invalid schedule response:", data);
            return;
        }

        if (data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center;">
                        No enrolled classes found
                    </td>
                </tr>
            `;
            return;
        }

        data.forEach(row => {
    tbody.innerHTML += `
        <tr>
            <td>${row.course_code || ""}</td>
            <td>${row.description || ""}</td>
            <td style="text-align:center;">${row.unit || ""}</td>
            <td>${row.schedule || ""}</td>
            <td>${row.room_name || ""}</td>
            <td>${row.instructor_name || ""}</td>
            <td>${row.section || ""}</td>
        </tr>
    `;
});

        addRowEvents();
    })
    .catch(err => {
        console.error("Schedule fetch error:", err);
    });
}


// ==================== LOAD STUDENT INFO ====================
function loadStudentInfo() {

    fetch("student_get_student.php", {
        credentials: "include"
    })
    .then(res => res.json())
    .then(data => {

        console.log("STUDENT RESPONSE:", data);

        if (!data || data.status !== "success") {
            document.getElementById("studentName").innerText = "Not logged in";
            return;
        }

        document.getElementById("studentName").innerText =
            "Name: " + data.name;

        document.getElementById("studentSection").innerText =
            "Course & Section: " + data.section;

        document.getElementById("academicYear").innerText =
            "Academic Year: " + data.academic_year;

        document.getElementById("period").innerText =
            "Period: " + data.period;
    })
    .catch(err => {
        console.error("Student info error:", err);
    });
}


// ==================== ROW HOVER EFFECT ====================
function addRowEvents() {

    const tableRows = document.querySelectorAll('tbody tr');

    tableRows.forEach(row => {

        row.addEventListener('mouseenter', () => {
            row.style.backgroundColor = '#f1f5ff';
        });

        row.addEventListener('mouseleave', () => {
            row.style.backgroundColor = 'transparent';
        });
    });
}


// ==================== BUTTONS ====================
function initButtons() {

    const logoutBtn = document.querySelector('.btn-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            if (confirm("Are you sure you want to Logout?")) {
                window.location.href = 'role_selection.html';
            }
        });
    }

    const pdfBtn = document.querySelector('.btn-pdf');
    if (pdfBtn) {
        pdfBtn.addEventListener('click', () => {
            window.print();
        });
    }

    const scheduleBtn = document.querySelector('.btn-schedule');
    if (scheduleBtn) {
        scheduleBtn.addEventListener('click', () => {
            console.log("Already in schedule view.");
        });
    }
}