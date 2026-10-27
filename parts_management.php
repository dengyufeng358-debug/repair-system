<?php
session_start();

/*
    舊的零件庫存管理頁面已整合到 stock.php。

    多車行版本除了 username，
    也必須確認登入 Session 裡有 shop_id。
*/
if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    header("Location: login.php");
    exit();
}

header("Location: stock.php");
exit();
?>
