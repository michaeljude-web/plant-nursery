<?php
session_start();
session_unset();
session_destroy();
header('Location: /plant/login.php');
exit();