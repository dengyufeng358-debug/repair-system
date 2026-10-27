<?php
session_start();

/*
    清除所有 Session 資料
*/
$_SESSION = [];

/*
    清除瀏覽器 Session Cookie
*/
if (ini_get("session.use_cookies")) {

    $params =
        session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
    完全銷毀 Session
*/
session_destroy();

/*
    回到系統首頁
*/
header("Location: index.php");
exit();
?>