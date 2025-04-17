<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: /hfc_inventory/Frontend/index.html");
    exit();
}
?>