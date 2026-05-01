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

        fetch("login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                username,
                password
            })
        })
        .then(res => res.json())
        .then(data => {

            console.log("Login response:", data);

            if (data.status === "success") {

                if (data.role === "admin") {
                    window.location.href = "administrator_assign_subject.php";
                }

                else if (data.role === "instructor") {
                    window.location.href = "instructor_portal.php";
                }

                else if (data.role === "student") {
                    window.location.href = "student_portal.html";
                }

                else {
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