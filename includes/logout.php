<?php
session_start();
session_unset();
session_destroy();

header("Location: /hfc_inventory/Frontend/index.php");
exit();
?>
