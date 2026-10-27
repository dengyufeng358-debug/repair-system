<?php
/*
    舊的 admin_login.php 已停用。

    系統目前改成多車行登入，
    統一由 login.php 驗證帳號、角色與 shop_id。

    避免舊的硬編碼 admin / 1234
    繞過多車行資料隔離。
*/
header("Location: login.php");
exit();
?>
