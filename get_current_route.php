<?php
session_start();
include "db.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function replyJson($data)
{
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit();
}

if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    http_response_code(401);

    replyJson(
        [
            "ok" => false,
            "action" => "LOGIN_REQUIRED"
        ]
    );
}

$shopId =
    (int)$_SESSION["shop_id"];

/*
    =========================================
    第一次啟動監聽時：
    把「目前已存在的舊命令」設為已看過，
    避免登入後突然執行昨天的命令。
    =========================================
*/
if (
    !isset(
        $_SESSION["last_nav_command_id"]
    )
) {

    $maxSql = "
        SELECT
            COALESCE(MAX(id), 0) AS max_id
        FROM navigation_commands
        WHERE shop_id = ?
    ";

    $maxStmt =
        $conn->prepare(
            $maxSql
        );

    if ($maxStmt) {

        $maxStmt->bind_param(
            "i",
            $shopId
        );

        $maxStmt->execute();

        $maxResult =
            $maxStmt->get_result();

        $maxRow =
            $maxResult
            ? $maxResult->fetch_assoc()
            : null;

        $_SESSION["last_nav_command_id"] =
            (int)(
                $maxRow["max_id"]
                ?? 0
            );

        $maxStmt->close();

    } else {

        $_SESSION["last_nav_command_id"] =
            0;
    }
}

/*
    init=1 只啟動監聽，不執行導頁
*/
if (
    isset($_GET["init"]) &&
    $_GET["init"] === "1"
) {
    replyJson(
        [
            "ok" => true,
            "action" => "READY",
            "last_id" =>
                (int)$_SESSION[
                    "last_nav_command_id"
                ]
        ]
    );
}

$lastId =
    (int)$_SESSION[
        "last_nav_command_id"
    ];

/*
    找目前車行「最新的新命令」
*/
$sql = "
    SELECT
        id,
        page_code
    FROM navigation_commands
    WHERE shop_id = ?
      AND id > ?
    ORDER BY id DESC
    LIMIT 1
";

$stmt =
    $conn->prepare(
        $sql
    );

if (!$stmt) {
    replyJson(
        [
            "ok" => false,
            "action" => "ERROR"
        ]
    );
}

$stmt->bind_param(
    "ii",
    $shopId,
    $lastId
);

$stmt->execute();

$result =
    $stmt->get_result();

if (
    !$result ||
    $result->num_rows <= 0
) {
    $stmt->close();

    replyJson(
        [
            "ok" => true,
            "action" => "NONE"
        ]
    );
}

$row =
    $result->fetch_assoc();

$stmt->close();

$commandId =
    (int)$row["id"];

$pageCode =
    strtoupper(
        trim(
            $row["page_code"]
        )
    );

$_SESSION["last_nav_command_id"] =
    $commandId;

/*
    =========================================
    白名單：
    資料庫只存 page_code，
    真正 PHP 路徑由伺服器固定映射
    =========================================
*/
$pageMap = [

    "DASHBOARD" =>
        "dashboard.php",

    "MAINTENANCE" =>
        "maintenance_list.php",

    "INVENTORY" =>
        "stock.php",

    "REPAIRS" =>
        "repair_list.php",

    "CUSTOMER" =>
        "view_progress.php",

    "VOICE" =>
        "voice_command.php",

    "PLATE" =>
        "plate_login.php",

    "AI_VEHICLE" =>
        "ai_vehicle_advice.php",

    "AI_INVENTORY" =>
        "ai_inventory_advice.php",

    "QR" =>
        "shop_qr.php"
];

if (
    !isset(
        $pageMap[$pageCode]
    )
) {
    replyJson(
        [
            "ok" => false,
            "action" => "UNKNOWN_PAGE",
            "page_code" => $pageCode
        ]
    );
}

replyJson(
    [
        "ok" => true,
        "action" => "NAVIGATE",
        "command_id" => $commandId,
        "page_code" => $pageCode,
        "target" =>
            $pageMap[$pageCode]
    ]
);
?>
