<?php
session_start();
include "db.php";

if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    header("Location: login.php");
    exit();
}

$shop_id = (int)$_SESSION["shop_id"];

/*
    只允許 POST 方式刪除，
    避免直接輸入網址就刪除資料。
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: repair_list.php");
    exit();
}

/*
    接收要刪除的維修紀錄 ID
*/
$repair_id = isset($_POST["id"])
    ? (int)$_POST["id"]
    : 0;

/*
    保留原本的日期篩選條件
*/
$start_date = isset($_POST["start_date"])
    ? trim($_POST["start_date"])
    : "";

$end_date = isset($_POST["end_date"])
    ? trim($_POST["end_date"])
    : "";

/*
    驗證安全權杖
*/
$csrf_token = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $csrf_token
    )
) {
    redirectBack(
        "invalid_request",
        $start_date,
        $end_date
    );
}

if ($repair_id <= 0) {
    redirectBack(
        "invalid_id",
        $start_date,
        $end_date
    );
}

try {

    /*
        啟動交易：
        加回庫存與刪除紀錄必須一起成功。
    */
    $conn->begin_transaction();

    /*
        ★ 多車行隔離核心：
        只能找到目前登入車行自己的維修紀錄。
        即使手動竄改 id，也不能刪除別家車行資料。
    */
    $find_sql = "
        SELECT
            part_id,
            quantity
        FROM repair_records
        WHERE id = ?
          AND shop_id = ?
        FOR UPDATE
    ";

    $find_stmt =
        $conn->prepare($find_sql);

    if (!$find_stmt) {
        throw new Exception(
            "無法建立查詢"
        );
    }

    $find_stmt->bind_param(
        "ii",
        $repair_id,
        $shop_id
    );

    $find_stmt->execute();

    $find_stmt->bind_result(
        $part_id,
        $quantity
    );

    if (!$find_stmt->fetch()) {

        $find_stmt->close();

        $conn->rollback();

        redirectBack(
            "not_found",
            $start_date,
            $end_date
        );
    }

    $find_stmt->close();

    $part_id = (int)$part_id;
    $quantity = (int)$quantity;

    /*
        將原本使用的零件數量加回庫存。

        ★ 只允許加回目前登入車行自己的零件。
    */
    $stock_sql = "
        UPDATE parts
        SET stock = stock + ?
        WHERE id = ?
          AND shop_id = ?
    ";

    $stock_stmt =
        $conn->prepare($stock_sql);

    if (!$stock_stmt) {
        throw new Exception(
            "無法建立庫存更新"
        );
    }

    $stock_stmt->bind_param(
        "iii",
        $quantity,
        $part_id,
        $shop_id
    );

    if (!$stock_stmt->execute()) {

        $error =
            $stock_stmt->error;

        $stock_stmt->close();

        throw new Exception(
            "庫存恢復失敗：" .
            $error
        );
    }

    /*
        如果原本零件仍存在且屬於目前車行，
        affected_rows 會大於 0。
    */
    $stock_restored =
        $stock_stmt->affected_rows > 0;

    $stock_stmt->close();

    /*
        刪除維修紀錄。

        ★ 再次限制 shop_id，
        避免跨車行刪除。
    */
    $delete_sql = "
        DELETE FROM repair_records
        WHERE id = ?
          AND shop_id = ?
    ";

    $delete_stmt =
        $conn->prepare($delete_sql);

    if (!$delete_stmt) {
        throw new Exception(
            "無法建立刪除指令"
        );
    }

    $delete_stmt->bind_param(
        "ii",
        $repair_id,
        $shop_id
    );

    $delete_stmt->execute();

    if (
        $delete_stmt->affected_rows <= 0
    ) {

        $delete_stmt->close();

        throw new Exception(
            "刪除失敗"
        );
    }

    $delete_stmt->close();

    /*
        全部成功後才正式儲存。
    */
    $conn->commit();

    if ($stock_restored) {

        redirectBack(
            "deleted",
            $start_date,
            $end_date
        );

    } else {

        /*
            原本的零件資料已被刪除，
            或該零件已不存在時，
            維修紀錄仍可刪除，
            但無法恢復庫存。
        */
        redirectBack(
            "deleted_no_part",
            $start_date,
            $end_date
        );
    }

} catch (Throwable $error) {

    /*
        其中任何一步失敗就全部取消。
    */
    try {
        $conn->rollback();
    } catch (Throwable $rollback_error) {
        // rollback 失敗時不再額外中斷
    }

    redirectBack(
        "delete_failed",
        $start_date,
        $end_date
    );
}

/*
    返回維修紀錄頁，
    並保留原本的日期篩選。
*/
function redirectBack(
    $status,
    $start_date,
    $end_date
) {
    $parameters = [
        "status" => $status
    ];

    if (
        preg_match(
            "/^\d{4}-\d{2}-\d{2}$/",
            $start_date
        )
    ) {
        $parameters["start_date"] =
            $start_date;
    }

    if (
        preg_match(
            "/^\d{4}-\d{2}-\d{2}$/",
            $end_date
        )
    ) {
        $parameters["end_date"] =
            $end_date;
    }

    $url =
        "repair_list.php?" .
        http_build_query(
            $parameters
        );

    header(
        "Location: " . $url
    );

    exit();
}
?>