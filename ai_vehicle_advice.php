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
$shop_name = $_SESSION["shop_name"] ?? "目前車行";

$apiKey = getenv("OPENAI_API_KEY");

$message = "";
$plate = "";
$records = [];
$advice = "";

/*
    從 OpenAI Responses API 取出文字
*/
function getResponseText($data)
{
    $answer = "";

    if (
        !isset($data["output"]) ||
        !is_array($data["output"])
    ) {
        return "";
    }

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
                    $answer .= $content["text"] ?? "";
                }
            }
        }
    }

    return trim($answer);
}

/*
    將 AI 純文字安全顯示成 HTML
*/
function renderAdvice($text)
{
    return nl2br(
        htmlspecialchars(
            $text,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $plate =
        strtoupper(
            trim(
                $_POST["plate"] ?? ""
            )
        );

    $plate =
        str_replace(
            [" ", "-"],
            "",
            $plate
        );

    if ($plate === "") {

        $message = "請輸入車牌號碼";

    } elseif (
        !preg_match(
            "/^[A-Z0-9]{4,7}$/",
            $plate
        )
    ) {

        $message = "車牌格式不正確";

    } elseif (!$apiKey) {

        $message =
            "系統讀不到 OPENAI_API_KEY";

    } elseif (
        !function_exists("curl_init")
    ) {

        $message =
            "PHP cURL 尚未啟用";

    } else {

        /*
            =================================================
            只讀取「目前登入車行」自己的保養案件
            =================================================
        */
        $sql = "
            SELECT
                id,
                plate_number,
                customer_name,
                item_name,
                status,
                created_at
            FROM maintenance_records
            WHERE shop_id = ?
              AND plate_number = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 30
        ";

        $stmt =
            $conn->prepare($sql);

        if (!$stmt) {

            $message =
                "無法讀取保養紀錄：" .
                $conn->error;

        } else {

            $stmt->bind_param(
                "is",
                $shop_id,
                $plate
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            while (
                $row =
                $result->fetch_assoc()
            ) {
                $records[] = $row;
            }

            $stmt->close();

            if (count($records) === 0) {

                $message =
                    "目前車行查不到 " .
                    $plate .
                    " 的保養紀錄";

            } else {

                /*
                    =========================================
                    整理要交給 AI 的歷史資料
                    =========================================
                */
                $historyLines = [];

                foreach ($records as $index => $row) {

                    $historyLines[] =
                        ($index + 1) .
                        ". 日期：" .
                        ($row["created_at"] ?? "未知") .
                        "｜項目：" .
                        ($row["item_name"] ?? "") .
                        "｜狀態：" .
                        ($row["status"] ?? "");
                }

                $historyText =
                    implode(
                        "\n",
                        $historyLines
                    );

                /*
                    =========================================
                    AI 維修建議 Prompt
                    =========================================
                */
                $prompt = <<<PROMPT
你是機車維修智慧管理系統中的「AI 維修輔助分析」功能。

請根據下面提供的實際保養紀錄，協助機車行技師整理維修與保養建議。

重要規則：
1. 只能根據提供的紀錄分析，不要自行假設里程、車齡、故障碼、零件磨耗程度或未提供的資訊。
2. 不要把建議寫成確定診斷。
3. 如果資料不足，請明確寫「需由技師現場檢查確認」。
4. 涉及煞車、輪胎、電系等安全項目時，優先提醒技師實際檢查。
5. 不要建議客戶自行進行高風險維修。
6. 使用繁體中文。
7. 內容要簡潔，適合直接顯示在機車行後台。

固定輸出格式：

【車況紀錄摘要】
用 2～4 句整理目前從歷史紀錄看得到的情況。

【建議優先檢查】
1. ...
2. ...
3. ...

【近期保養建議】
- ...
- ...

【技師提醒】
用 1～3 句提醒哪些部分仍需要現場檢查確認。

目前車行：{$shop_name}
車牌：{$plate}

歷史保養紀錄：
{$historyText}
PROMPT;

                $requestData = [
                    "model" =>
                        "gpt-5.6-luna",

                    "input" =>
                        $prompt,

                    "max_output_tokens" =>
                        700
                ];

                $ch =
                    curl_init(
                        "https://api.openai.com/v1/responses"
                    );

                curl_setopt_array(
                    $ch,
                    [
                        CURLOPT_RETURNTRANSFER =>
                            true,

                        CURLOPT_POST =>
                            true,

                        CURLOPT_HTTPHEADER =>
                            [
                                "Authorization: Bearer " .
                                $apiKey,

                                "Content-Type: application/json"
                            ],

                        CURLOPT_POSTFIELDS =>
                            json_encode(
                                $requestData,
                                JSON_UNESCAPED_UNICODE
                            ),

                        CURLOPT_TIMEOUT =>
                            40
                    ]
                );

                $response =
                    curl_exec($ch);

                $httpCode =
                    curl_getinfo(
                        $ch,
                        CURLINFO_HTTP_CODE
                    );

                $curlError =
                    curl_error($ch);

                curl_close($ch);

                if ($response === false) {

                    $message =
                        "AI 連線失敗：" .
                        $curlError;

                } else {

                    $data =
                        json_decode(
                            $response,
                            true
                        );

                    if (
                        $httpCode < 200 ||
                        $httpCode >= 300
                    ) {

                        $apiError =
                            $data["error"]["message"]
                            ?? ("HTTP " . $httpCode);

                        $message =
                            "OpenAI API 錯誤：" .
                            $apiError;

                    } else {

                        $advice =
                            getResponseText(
                                $data
                            );

                        if ($advice === "") {

                            $message =
                                "AI 有回應，但沒有取得分析文字";
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AI 車輛維修建議</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .main-card,
        .history-card,
        .ai-card {
            border:0;
            border-radius:20px;
        }

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:12px;
            padding:12px 15px;
        }

        .ai-card {
            background:
                linear-gradient(
                    135deg,
                    #eef6ff,
                    #f8fbff
                );
            border:1px solid #cfe2ff;
        }

        .ai-content {
            font-size:17px;
            line-height:1.9;
        }

        .plate-badge {
            font-size:18px;
            padding:8px 14px;
        }

        .notice-box {
            background:#fff3cd;
            border:1px solid #ffe69c;
            border-radius:12px;
            padding:14px;
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">

        <span class="navbar-brand fw-bold">
            🤖 AI 車輛維修建議
        </span>

        <a
            href="dashboard.php"
            class="btn btn-light btn-sm"
        >
            回首頁
        </a>

    </div>
</nav>

<div class="container py-5">

    <div class="shop-box mb-4">

        目前車行：

        <strong>
            <?php
            echo htmlspecialchars(
                $shop_name,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </strong>

    </div>

    <div class="card shadow-sm main-card mb-4">

        <div class="card-body p-4">

            <h3 class="fw-bold mb-3">
                查詢車輛並產生 AI 建議
            </h3>

            <p class="text-muted">
                AI 會讀取目前車行中該車牌的保養紀錄，
                整理成技師可參考的檢查與保養建議。
            </p>

            <?php if ($message !== "") { ?>

                <div class="alert alert-danger">

                    <?php
                    echo htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php } ?>

            <form method="post">

                <div class="input-group input-group-lg">

                    <input
                        type="text"
                        name="plate"
                        class="form-control"
                        placeholder="輸入車牌，例如 ABC1234"
                        value="<?php
                            echo htmlspecialchars(
                                $plate,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        required
                    >

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        🤖 AI 分析
                    </button>

                </div>

            </form>

        </div>

    </div>

    <?php if (count($records) > 0) { ?>

        <div class="card shadow-sm history-card mb-4">

            <div class="card-body p-4">

                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >

                    <h3 class="fw-bold mb-0">
                        維修 / 保養歷史
                    </h3>

                    <span
                        class="badge bg-dark plate-badge"
                    >
                        <?php
                        echo htmlspecialchars(
                            $plate,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </span>

                </div>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover align-middle mb-0"
                    >

                        <thead class="table-light">

                            <tr>
                                <th>日期</th>
                                <th>保養項目</th>
                                <th>狀態</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($records as $row) { ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["created_at"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["item_name"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["status"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php } ?>

    <?php if ($advice !== "") { ?>

        <div class="card shadow-sm ai-card mb-4">

            <div class="card-body p-4 p-md-5">

                <h2 class="fw-bold mb-4">
                    🧠 AI 維修輔助分析
                </h2>

                <div class="ai-content">

                    <?php
                    echo renderAdvice(
                        $advice
                    );
                    ?>

                </div>

            </div>

        </div>

        <div class="notice-box">

            <strong>⚠️ 使用提醒：</strong>

            AI 建議僅作為技師整理歷史紀錄與檢查方向的輔助，
            實際維修仍應以現場檢查、原廠規範及技師專業判斷為準。

        </div>

    <?php } ?>

</div>

</body>

</html>
