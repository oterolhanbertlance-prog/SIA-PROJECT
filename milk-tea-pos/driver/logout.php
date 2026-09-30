<?php
session_start();
unset($_SESSION['rider_id'], $_SESSION['rider_name'], $_SESSION['rider_phone']);
header('Location: login.php');
exit;
