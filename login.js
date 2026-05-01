document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("loginForm");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value.trim();
        const messageBox = document.getElementById("message");
        messageBox.innerText = "";

        if (!username || !password) {
            messageBox.innerText = "Please fill in all fields.";
            return;
        }

        // FIX: Use absolute path so it works on Vercel regardless of which login page calls this
        fetch("/api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({ username, password })
        })
        .then(res => res.json())
        .then(data => {
            console.log("Login response:", data);

            if (data.status === "success") {
                // FIX: Save user_id and full_name (now returned by login.php)
                localStorage.setItem('user_id',   data.user_id);
                localStorage.setItem('role',       data.role);
                localStorage.setItem('full_name',  data.full_name);

                if (data.role === "admin") {
                    window.location.href = "/api/administrator_assign_subject.php";
                } else if (data.role === "instructor") {
                    window.location.href = "/instructor_portal.html";
                } else if (data.role === "student") {
                    window.location.href = "/student_portal.html";
                } else {
                    messageBox.innerText = "Unknown role: " + data.role;
                }
            } else {
                messageBox.innerText = data.message;
            }
        })
        .catch(err => {
            console.error(err);
            messageBox.innerText = "Server error.";
        });
    });
});