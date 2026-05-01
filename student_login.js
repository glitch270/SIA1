document.getElementById("loginForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let username = document.getElementById("username").value;
    let password = document.getElementById("password").value;

    fetch("student_login.php", {
        method: "POST",
        credentials: "include",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            window.location.href = "student_portal.html";
        } else {
            document.getElementById("message").innerText = data.message;
        }
    })
    .catch(() => {
        document.getElementById("message").innerText = "Server error. Please try again.";
    });
});