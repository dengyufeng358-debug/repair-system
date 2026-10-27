<?php
include "db.php";

header("Content-Type: application/json; charset=UTF-8");

function replyJson($statusCode, $data)
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    replyJson(
        405,
        [
            "ok" => false,
            "message" => "只允許 POST"
        ]
    );
}

/*
    =========================================
    API Key 驗證
    Copilot Studio 會透過 X-API-Key Header 傳入
    =========================================
*/
$serverApiKey =
    getenv("REPAIR_REMOTE_API_KEY");

if (!$serverApiKey) {
    replyJson(
        500,
        [
            "ok" => false,
            "message" => "伺服器尚未設定 REPAIR_REMOTE_API_KEY"
        ]
    );
}

$clientApiKey =
    $_SERVER["HTTP_X_API_KEY"]
    ?? "";

if (
    $clientApiKey === "" ||
    !hash_equals(
        $serverApiKey,
        $clientApiKey
    )
) {
    replyJson(
        401,
        [
            "ok" => false,
            "message" => "API Key 驗證失敗"
        ]
    );
}

/*
    =========================================
    讀取 JSON / Form 資料
    =========================================
*/
$raw =
    file_get_contents(
        "php://input"
    );

$input =
    json_decode(
        $raw,
        true
    );

if (!is_array($input)) {
    $input = $_POST;
}

$shopCode =
    strtoupper(
        trim(
            $input["shop_code"]
            ?? ""
        )
    );

$pageCode =
    strtoupper(
        trim(
            $input["page_code"]
            ?? ""
        )
    );

/*
    =========================================
    只允許固定頁面代碼
    AI / Copilot 不可以自己指定 PHP 路徑
    =========================================
*/
$allowedPages = [
    "DASHBOARD",
    "MAINTENANCE",
    "INVENTORY",
    "REPAIRS",
    "CUSTOMER",
    "VOICE",
    "PLATE",
    "AI_VEHICLE",
    "AI_INVENTORY",
    "QR"
];

if (
    $shopCode === "" ||
    !preg_match(
        "/^[A-Z0-9_-]{2,30}$/",
        $shopCode
    )
) {
    replyJson(
        400,
        [
            "ok" => false,
            "message" => "shop_code 格式錯誤"
        ]
    );
}

if (
    !in_array(
        $pageCode,
        $allowedPages,
        true
    )
) {
    replyJson(
        400,
        [
            "ok" => false,
            "message" => "不支援的 page_code"
        ]
    );
}

/*
    =========================================
    用 shop_code 找車行
    =========================================
*/
$shopSql = "
    SELECT
        id,
        shop_name
    FROM shops
    WHERE shop_code = ?
    LIMIT 1
";

$shopStmt =
    $conn->prepare(
        $shopSql
    );

if (!$shopStmt) {
    replyJson(
        500,
        [
            "ok" => false,
            "message" => "車行查詢準備失敗"
        ]
    );
}

$shopStmt->bind_param(
    "s",
    $shopCode
);

$shopStmt->execute();

$shopResult =
    $shopStmt->get_result();

if (
    !$shopResult ||
    $shopResult->num_rows <= 0
) {
    $shopStmt->close();

    replyJson(
        404,
        [
            "ok" => false,
            "message" => "找不到此車行代碼"
        ]
    );
}

$shop =
    $shopResult->fetch_assoc();

$shopStmt->close();

$shopId =
    (int)$shop["id"];

$shopName =
    $shop["shop_name"];

/*
    =========================================
    寫入「導頁命令」
    不修改任何維修、客戶、庫存資料
    =========================================
*/
$insertSql = "
    INSERT INTO navigation_commands
    (
        shop_id,
        page_code
    )
    VALUES
    (
        ?,
        ?
    )
";

$insertStmt =
    $conn->prepare(
        $insertSql
    );

if (!$insertStmt) {
    replyJson(
        500,
        [
            "ok" => false,
            "message" => "導頁命令建立失敗，請確認 navigation_commands 資料表已建立"
        ]
    );
}

$insertStmt->bind_param(
    "is",
    $shopId,
    $pageCode
);

if (!$insertStmt->execute()) {

    $error =
        $insertStmt->error;

    $insertStmt->close();

    replyJson(
        500,
        [
            "ok" => false,
            "message" => "導頁命令寫入失敗",
            "detail" => $error
        ]
    );
}

$commandId =
    $insertStmt->insert_id;

$insertStmt->close();

replyJson(
    200,
    [
        "ok" => true,
        "message" =>
            "已通知 " .
            $shopName .
            " 開啟 " .
            $pageCode,
        "command_id" =>
            $commandId,
        "shop_code" =>
            $shopCode,
        "page_code" =>
            $pageCode
    ]
);
?>
