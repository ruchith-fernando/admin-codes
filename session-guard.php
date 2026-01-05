<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo 'SESSION_EXPIRED';
        exit;
    }

    header('Location: /pages/index.php');
    exit;
}
?>
