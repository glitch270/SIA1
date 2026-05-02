<?php
session_start();
session_unset();
session_destroy();

// Fix: Clear localStorage handled by JS, redirect to root
header("Location: /role_selection.html");
exit;
?>