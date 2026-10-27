<?php
session_start();
include "db.php";

header("Content-Type: application/json; charset=UTF-8");

/*
    =========================================================
    AI 語音控制 API
    =========================================================
    流程：
    語音文字
      ↓
    OpenAI 理解自然語言
      ↓
    PHP 驗證 AI 結果
      ↓
    依目前 shop_id 執行資料庫操作

    重要：
    AI 只負責「理解」。
    真正寫入資料庫仍由 PHP 白名單邏輯控制。
*/

function jsonReply(
    $ok,
    $message,
    $data = [],
    $redirect = ""
) {
    echo json_encode(
        [
            "ok" => $ok,
            "message" => $message,
            "data" => $data,
            "redirect" => $redirect
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit();
}

if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    http_response_code(401);

    jsonReply(
        false,
        "尚未登入，請重新登入系統"
    );
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    jsonReply(
        false,
        "不允許的操作方式"
    );
}

$shop_id = (int)$_SESSION["shop_id"];
$shop_name = $_SESSION["shop_name"] ?? "目前車行";

$text = trim($_POST["text"] ?? "");

if ($text === "") {
    jsonReply(
        false,
        "沒有收到語音內容"
    );
}

$apiKey = getenv("OPENAI_API_KEY");

if (!$apiKey) {
    jsonReply(
        false,
        "系統讀不到 OpenAI API Key"
    );
}

if (!function_exists("curl_init")) {
    jsonReply(
        false,
        "PHP cURL 尚未啟用"
    );
}

/*
    =========================================================
    允許 AI 輸出的固定值
    =========================================================
*/
$allowedActions = [
    "add_plate",
    "report",
    "query",
    "open_page",
    "unknown"
];

$allowedItems = [
    "",
    "機油更換",
    "齒輪油更換",
    "煞車檢查",
    "煞車皮更換",
    "輪胎更換",
    "電瓶更換",
    "火星塞更換",
    "傳動保養",
    "皮帶更換",
    "前總成維修",
    "其他檢查"
];

$allowedStatuses = [
    "",
    "待處理",
    "處理中",
    "待交車",
    "維修完畢",
    "已通知領車"
];

$allowedPages = [
    "",
    "dashboard",
    "maintenance",
    "inventory",
    "repairs",
    "plate",
    "voice",
    "customer"
];

/*
    =========================================================
    AI Prompt
    =========================================================
*/
$prompt = <<<PROMPT
你是「機車維修智慧管理系統」的自然語言指令解析器。

你的工作只有一件事：
把技師說的中文自然語言轉成 JSON。

只輸出 JSON。
不要 Markdown。
不要解釋。
不要加入其他文字。

固定格式：
{
  "action": "",
  "plate": "",
  "item": "",
  "status": "",
  "page": ""
}

action 只能是：
add_plate
report
query
open_page
unknown

action 規則：
- 新增、登記車牌 → add_plate
- 回報維修、更新進度 → report
- 查詢車牌進度 → query
- 打開、前往某個系統頁面 → open_page
- 無法確定 → unknown

plate：
- 有車牌就轉成大寫並移除空白與 -
- 沒有就 ""

item 只能是：
機油更換
齒輪油更換
煞車檢查
煞車皮更換
輪胎更換
電瓶更換
火星塞更換
傳動保養
皮帶更換
前總成維修
其他檢查
或 ""

常見口語：
換機油、機油好了 → 機油更換
換齒輪油 → 齒輪油更換
換煞車皮、換剎車皮 → 煞車皮更換
檢查煞車、檢查剎車 → 煞車檢查
換輪胎 → 輪胎更換
換電瓶 → 電瓶更換
換火星塞 → 火星塞更換
傳動、保養傳動 → 傳動保養
換皮帶 → 皮帶更換

status 只能是：
待處理
處理中
待交車
維修完畢
已通知領車
或 ""

常見語意：
還沒做、還沒處理 → 待處理
正在修、正在處理、正在檢查 → 處理中
準備交車、可以給客人牽、等等可以牽 → 待交車
做完了、修好了、完成了 → 維修完畢
通知客人領車、已通知領車 → 已通知領車

page 只在 open_page 時使用：
dashboard = 首頁總覽
maintenance = 保養案件
inventory = 零件庫存
repairs = 維修紀錄
plate = 車牌登記
voice = 語音控制
customer = 客戶查詢
其他 action 的 page 填 ""

特別注意：
「ABC1234 那台機油弄好了，可以準備給客人牽了」
應輸出：
{"action":"report","plate":"ABC1234","item":"機油更換","status":"待交車","page":""}

不確定的欄位留空，不要亂猜。

現在解析：
{$text}
PROMPT;

$requestData = [
    "model" => "gpt-5.6-luna",
    "input" => $prompt,
    "max_output_tokens" => 300
];

$ch = curl_init(
    "https://api.openai.com/v1/responses"
);

curl_setopt_array(
    $ch,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode(
            $requestData,
            JSON_UNESCAPED_UNICODE
        ),
        CURLOPT_TIMEOUT => 30
    ]
);

$response = curl_exec($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);

if ($response === false) {
    jsonReply(
        false,
        "AI 連線失敗：" . $curlError
    );
}

$data = json_decode(
    $response,
    true
);

if (
    $httpCode < 200 ||
    $httpCode >= 300
) {
    $errorMessage =
        $data["error"]["message"]
        ?? ("HTTP " . $httpCode);

    jsonReply(
        false,
        "OpenAI API 錯誤：" . $errorMessage
    );
}

/*
    =========================================================
    取出 Responses API 文字
    =========================================================
*/
$answer = "";

if (
    isset($data["output"]) &&
    is_array($data["output"])
) {
    foreach ($data["output"] as $outputItem) {

        if (
            ($outputItem["type"] ?? "") === "message" &&
            isset($outputItem["content"]) &&
            is_array($outputItem["content"])
        ) {

            foreach ($outputItem["content"] as $content) {

                if (
                    ($content["type"] ?? "") === "output_text"
                ) {
                    $answer .=
                        $content["text"] ?? "";
                }
            }
        }
    }
}

$answer = trim($answer);

/*
    清理 ```json ... ```
*/
$answer = preg_replace(
    '/^```(?:json)?\s*/i',
    '',
    $answer
);

$answer = preg_replace(
    '/\s*```$/',
    '',
    $answer
);

if (
    strlen($answer) > 0 &&
    $answer[0] !== "{"
) {
    if (
        preg_match(
            '/\{.*\}/s',
            $answer,
            $match
        )
    ) {
        $answer =
            $match[0];
    }
}

$intent = json_decode(
    $answer,
    true
);

if (!is_array($intent)) {
    jsonReply(
        false,
        "AI 有回應，但無法解析指令"
    );
}

/*
    =========================================================
    驗證 AI 結果
    =========================================================
*/
$action =
    trim(
        $intent["action"] ?? ""
    );

$plate =
    strtoupper(
        trim(
            $intent["plate"] ?? ""
        )
    );

$plate =
    str_replace(
        [" ", "-"],
        "",
        $plate
    );

$item =
    trim(
        $intent["item"] ?? ""
    );

$status =
    trim(
        $intent["status"] ?? ""
    );

$page =
    trim(
        $intent["page"] ?? ""
    );

if (
    !in_array(
        $action,
        $allowedActions,
        true
    )
) {
    $action = "unknown";
}

if (
    !in_array(
        $item,
        $allowedItems,
        true
    )
) {
    $item = "";
}

if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {
    $status = "";
}

if (
    !in_array(
        $page,
        $allowedPages,
        true
    )
) {
    $page = "";
}

$intentData = [
    "action" => $action,
    "plate" => $plate,
    "item" => $item,
    "status" => $status,
    "page" => $page
];

/*
    =========================================================
    1. 開啟頁面
    =========================================================
*/
if ($action === "open_page") {

    $pageMap = [
        "dashboard" =>
            "dashboard.php",

        "maintenance" =>
            "maintenance_list.php",

        "inventory" =>
            "stock.php",

        "repairs" =>
            "repair_list.php",

        "plate" =>
            "plate_login.php",

        "voice" =>
            "voice_command.php",

        "customer" =>
            "view_progress.php"
    ];

    if (
        $page === "" ||
        !isset($pageMap[$page])
    ) {
        jsonReply(
            false,
            "AI 有理解到開啟頁面，但不知道要開哪一頁",
            $intentData
        );
    }

    jsonReply(
        true,
        "正在開啟指定頁面",
        $intentData,
        $pageMap[$page]
    );
}

/*
    =========================================================
    2. 新增車牌
    =========================================================
*/
if ($action === "add_plate") {

    if (
        $plate === "" ||
        !preg_match(
            "/^[A-Z0-9]{4,7}$/",
            $plate
        )
    ) {
        jsonReply(
            false,
            "AI 判斷要新增車牌，但沒有辨識到正確車牌",
            $intentData
        );
    }

    /*
        同一車行不要重複登記。
    */
    $checkSql = "
        SELECT id
        FROM plate_records
        WHERE shop_id = ?
          AND plate_number = ?
        LIMIT 1
    ";

    $checkStmt =
        $conn->prepare(
            $checkSql
        );

    $checkStmt->bind_param(
        "is",
        $shop_id,
        $plate
    );

    $checkStmt->execute();

    $checkResult =
        $checkStmt->get_result();

    if (
        $checkResult &&
        $checkResult->num_rows > 0
    ) {
        $checkStmt->close();

        jsonReply(
            true,
            "車牌 " . $plate . " 已經登記過了",
            $intentData
        );
    }

    $checkStmt->close();

    $note =
        "AI 語音登記";

    $insertSql = "
        INSERT INTO plate_records
        (
            shop_id,
            plate_number,
            note
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ";

    $stmt =
        $conn->prepare(
            $insertSql
        );

    $stmt->bind_param(
        "iss",
        $shop_id,
        $plate,
        $note
    );

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        jsonReply(
            false,
            "車牌新增失敗：" . $error,
            $intentData
        );
    }

    $stmt->close();

    jsonReply(
        true,
        $shop_name .
        " 已新增車牌 " .
        $plate,
        $intentData
    );
}

/*
    =========================================================
    3. 查詢維修進度
    =========================================================
*/
if ($action === "query") {

    if ($plate === "") {
        jsonReply(
            false,
            "AI 判斷要查詢，但沒有辨識到車牌",
            $intentData
        );
    }

    $sql = "
        SELECT
            item_name,
            status
        FROM maintenance_records
        WHERE shop_id = ?
          AND plate_number = ?
        ORDER BY id DESC
    ";

    $stmt =
        $conn->prepare($sql);

    $stmt->bind_param(
        "is",
        $shop_id,
        $plate
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if (
        !$result ||
        $result->num_rows <= 0
    ) {
        $stmt->close();

        jsonReply(
            false,
            "目前車行查不到 " .
            $plate .
            " 的保養案件",
            $intentData
        );
    }

    $hasPending = false;
    $hasProcessing = false;
    $hasWaiting = false;
    $hasNotified = false;

    $count = 0;

    while (
        $row =
        $result->fetch_assoc()
    ) {
        $count++;

        $currentStatus =
            $row["status"];

        if (
            $currentStatus === "待處理"
        ) {
            $hasPending = true;
        }

        if (
            $currentStatus === "處理中"
        ) {
            $hasProcessing = true;
        }

        if (
            $currentStatus === "待交車"
        ) {
            $hasWaiting = true;
        }

        if (
            $currentStatus === "已通知領車"
        ) {
            $hasNotified = true;
        }
    }

    $stmt->close();

    if ($hasPending) {

        $mainStatus =
            "待處理";

    } elseif ($hasProcessing) {

        $mainStatus =
            "處理中";

    } elseif ($hasWaiting) {

        $mainStatus =
            "待交車";

    } elseif ($hasNotified) {

        $mainStatus =
            "可領車";

    } else {

        $mainStatus =
            "維修完畢";
    }

    jsonReply(
        true,
        $plate .
        " 目前整體狀態是 " .
        $mainStatus .
        "，共有 " .
        $count .
        " 個項目",
        $intentData
    );
}

/*
    =========================================================
    4. 回報維修進度
    =========================================================
*/
if ($action === "report") {

    if ($plate === "") {
        jsonReply(
            false,
            "AI 判斷要回報進度，但沒有辨識到車牌",
            $intentData
        );
    }

    if ($status === "") {
        jsonReply(
            false,
            "AI 判斷要回報進度，但沒有辨識到狀態",
            $intentData
        );
    }

    try {

        $conn->begin_transaction();

        /*
            有辨識到項目：
            精準找該車牌該項目。

            沒辨識到項目：
            更新該車牌最新案件。
        */
        if ($item !== "") {

            $findSql = "
                SELECT
                    id,
                    item_name,
                    status
                FROM maintenance_records
                WHERE shop_id = ?
                  AND plate_number = ?
                  AND item_name = ?
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ";

            $findStmt =
                $conn->prepare(
                    $findSql
                );

            $findStmt->bind_param(
                "iss",
                $shop_id,
                $plate,
                $item
            );

        } else {

            $findSql = "
                SELECT
                    id,
                    item_name,
                    status
                FROM maintenance_records
                WHERE shop_id = ?
                  AND plate_number = ?
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ";

            $findStmt =
                $conn->prepare(
                    $findSql
                );

            $findStmt->bind_param(
                "is",
                $shop_id,
                $plate
            );
        }

        $findStmt->execute();

        $findResult =
            $findStmt->get_result();

        if (
            !$findResult ||
            $findResult->num_rows <= 0
        ) {
            $findStmt->close();

            throw new Exception(
                "目前車行找不到要更新的保養案件"
            );
        }

        $record =
            $findResult->fetch_assoc();

        $maintenanceId =
            (int)$record["id"];

        $actualItem =
            $record["item_name"];

        $findStmt->close();

        /*
            寫入進度歷程
        */
        $progressSql = "
            INSERT INTO maintenance_progress
            (
                shop_id,
                maintenance_id,
                progress_text,
                progress_status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ";

        $progressStmt =
            $conn->prepare(
                $progressSql
            );

        $progressStmt->bind_param(
            "iiss",
            $shop_id,
            $maintenanceId,
            $text,
            $status
        );

        if (
            !$progressStmt->execute()
        ) {
            $progressError =
                $progressStmt->error;

            $progressStmt->close();

            throw new Exception(
                "進度紀錄新增失敗：" .
                $progressError
            );
        }

        $progressStmt->close();

        /*
            更新案件狀態
        */
        $updateSql = "
            UPDATE maintenance_records
            SET status = ?
            WHERE id = ?
              AND shop_id = ?
        ";

        $updateStmt =
            $conn->prepare(
                $updateSql
            );

        $updateStmt->bind_param(
            "sii",
            $status,
            $maintenanceId,
            $shop_id
        );

        if (
            !$updateStmt->execute()
        ) {
            $updateError =
                $updateStmt->error;

            $updateStmt->close();

            throw new Exception(
                "案件更新失敗：" .
                $updateError
            );
        }

        $updateStmt->close();

        $conn->commit();

        jsonReply(
            true,
            $plate .
            " 的 " .
            $actualItem .
            " 已更新為 " .
            $status,
            $intentData
        );

    } catch (Throwable $error) {

        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }

        jsonReply(
            false,
            $error->getMessage(),
            $intentData
        );
    }
}

/*
    =========================================================
    無法理解
    =========================================================
*/
jsonReply(
    false,
    "AI 無法確定你要執行的操作，請換個說法",
    $intentData
);
?>
