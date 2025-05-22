<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
    echo json_encode(['isLoggedIn' => true]);
} else {
    echo json_encode(['isLoggedIn' => false]);
}
exit();
?>
