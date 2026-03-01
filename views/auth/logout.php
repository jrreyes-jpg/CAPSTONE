<?php
session_start();
session_destroy();
header("Location: /codesamplecaps/public/login.php");
exit();
?>
