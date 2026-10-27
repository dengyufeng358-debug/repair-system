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
$parts = [];
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

/*
    讀取目前登入車行的庫存，
    同時統計近 30 天、90 天實際維修使用量。
*/
$sql = "
    SELECT
        p.id,
        p.name,
        p.type,
        p.stock,
        p.cost,
        p.price,

        COALESCE(
            SUM(
                CASE
                    WHEN r.repair_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    THEN r.quantity
                    ELSE 0
                END
            ),
            0
        ) AS used_30,

        COALESCE(
            SUM(
                CASE
                    WHEN r.repair_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    THEN r.quantity
                    ELSE 0
                END
            ),
            0
        ) AS used_90

    FROM parts AS p

    LEFT JOIN repair_records AS r
        ON r.part_id = p.id
       AND r.shop_id = ?

    WHERE p.shop_id = ?

    GROUP BY
        p.id,
        p.name,
        p.type,
        p.stock,
        p.cost,
        p.price

    ORDER BY
        p.stock ASC,
        p.name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    $message =
        "無法讀取庫存資料：" .
        $conn->error;

} else {

    $stmt->bind_param(
        "ii",
        $shop_id,
        $shop_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $stock =
            (int)$row["stock"];

        $cost =
            (float)$row["cost"];

        $price =
            (float)$row["price"];

        $used30 =
            (int)$row["used_30"];

        $used90 =
            (int)$row["used_90"];

        $unitProfit =
            $price - $cost;

        /*
            近 90 天平均每月使用量
        */
        $avgMonthly =
            $used90 / 3;

        /*
            庫存大約可支撐幾個月。
            完全沒有使用紀錄時不計算。
        */
        $monthsCover =
            $avgMonthly > 0
                ? $stock / $avgMonthly
                : null;

        /*
            簡單、可解釋的「參考補貨量」：
            目標維持約 2 個月平均用量。
            這不是 AI 猜出來，而是系統先算好的基礎指標。
        */
        $targetStock =
            (int)ceil(
                $avgMonthly * 2
            );

        $suggestedReorder =
            max(
                0,
                $targetStock - $stock
            );

        $row["unit_profit"] =
            $unitProfit;

        $row["avg_monthly"] =
            $avgMonthly;

        $row["months_cover"] =
            $monthsCover;

        $row["suggested_reorder"] =
            $suggestedReorder;

        $parts[] = $row;
    }

    $stmt->close();
}

/*
    按下 AI 分析才呼叫 API，
    避免每次開頁面都產生費用。
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["analyze"])
) {

    if (!$apiKey) {

        $message =
            "系統讀不到 OPENAI_API_KEY";

    } elseif (
        !function_exists("curl_init")
    ) {

        $message =
            "PHP cURL 尚未啟用";

    } elseif (
        count($parts) === 0
    ) {

        $message =
            "目前車行沒有零件庫存資料";

    } else {

        $inventoryLines = [];

        foreach (
            $parts as $index => $part
        ) {

            $monthsText =
                $part["months_cover"] === null
                    ? "無足夠使用紀錄"
                    : number_format(
                        $part["months_cover"],
                        1
                    ) . " 個月";

            $inventoryLines[] =
                ($index + 1) .
                ". 零件：" .
                $part["name"] .
                "｜類型：" .
                $part["type"] .
                "｜目前庫存：" .
                $part["stock"] .
                "｜成本：" .
                number_format(
                    $part["cost"],
                    0
                ) .
                "｜售價：" .
                number_format(
                    $part["price"],
                    0
                ) .
                "｜單件毛利：" .
                number_format(
                    $part["unit_profit"],
                    0
                ) .
                "｜近30天使用量：" .
                $part["used_30"] .
                "｜近90天使用量：" .
                $part["used_90"] .
                "｜近90天平均每月使用量：" .
                number_format(
                    $part["avg_monthly"],
                    1
                ) .
                "｜庫存可支撐：" .
                $monthsText .
                "｜系統參考補貨量：" .
                $part["suggested_reorder"];
        }

        $inventoryText =
            implode(
                "\n",
                $inventoryLines
            );

        $prompt = <<<PROMPT
你是「機車維修智慧管理系統」中的 AI 庫存與補貨分析功能。

請根據下面的真實庫存與近期零件使用資料，
協助機車行判斷哪些零件應優先補貨、哪些暫時不需要補貨。

重要規則：
1. 只能根據提供的資料分析，不要自行假設不存在的銷售量、供應商交期或市場價格。
2. 「系統參考補貨量」是以近 90 天平均每月使用量，目標維持約 2 個月庫存所計算的基礎值，可作為參考，但不要把它描述成唯一正確答案。
3. 若某零件近 90 天沒有使用紀錄，請明確說明資料不足，不要亂預測需求。
4. 優先考慮：
   - 庫存偏低
   - 近期使用量高
   - 庫存可支撐月份短
5. 毛利可以作為經營參考，但不能因毛利高就忽略實際使用量。
6. 使用繁體中文。
7. 回答要簡潔、清楚，適合直接顯示在機車行後台。

固定輸出格式：

【庫存風險摘要】
用 2～4 句說明目前整體庫存狀況。

【優先補貨建議】
1. 零件名稱：原因；參考補貨量：約 X 個
2. ...
3. ...

【暫緩補貨項目】
- 零件名稱：原因
- ...

【經營提醒】
用 2～4 句說明技師或老闆還需要人工確認的事項。

目前車行：{$shop_name}

庫存與使用資料：
{$inventoryText}
PROMPT;

        $requestData = [
            "model" =>
                "gpt-5.6-luna",

            "input" =>
                $prompt,

            "max_output_tokens" =>
                900
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
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AI 庫存補貨建議</title>

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
                    #eef8f0,
                    #f8fffa
                );
            border:1px solid #badbcc;
        }

        .ai-content {
            font-size:17px;
            line-height:1.9;
        }

        .money {
            white-space:nowrap;
        }

        .notice-box {
            background:#fff3cd;
            border:1px solid #ffe69c;
            border-radius:12px;
            padding:14px;
        }

        .risk-high {
            background:#f8d7da !important;
        }

        .risk-medium {
            background:#fff3cd !important;
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-success shadow-sm">

    <div class="container">

        <span class="navbar-brand fw-bold">
            📦 AI 庫存補貨建議
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

            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
            >

                <div>

                    <h2 class="fw-bold mb-2">
                        庫存與近期使用分析
                    </h2>

                    <p class="text-muted mb-0">
                        系統先計算近 30 / 90 天用量，
                        再由 AI 綜合庫存、使用速度、成本與毛利提供補貨建議。
                    </p>

                </div>

                <form method="post">

                    <button
                        type="submit"
                        name="analyze"
                        class="btn btn-success btn-lg"
                    >
                        🤖 AI 分析庫存
                    </button>

                </form>

            </div>

            <?php if ($message !== "") { ?>

                <div class="alert alert-danger mt-4 mb-0">

                    <?php
                    echo htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php } ?>

        </div>

    </div>

    <?php if (count($parts) > 0) { ?>

        <div class="card shadow-sm main-card mb-4">

            <div class="card-body p-4">

                <h3 class="fw-bold mb-3">
                    庫存分析資料
                </h3>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover align-middle mb-0"
                    >

                        <thead class="table-light">

                            <tr>
                                <th>零件</th>
                                <th>庫存</th>
                                <th>近30天使用</th>
                                <th>近90天使用</th>
                                <th>月均使用</th>
                                <th>可支撐月份</th>
                                <th>單件毛利</th>
                                <th>參考補貨量</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($parts as $part) { ?>

                                <?php
                                $rowClass = "";

                                if (
                                    $part["months_cover"] !== null &&
                                    $part["months_cover"] < 1
                                ) {
                                    $rowClass = "risk-high";
                                } elseif (
                                    $part["months_cover"] !== null &&
                                    $part["months_cover"] < 2
                                ) {
                                    $rowClass = "risk-medium";
                                } elseif (
                                    (int)$part["stock"] < 5
                                ) {
                                    $rowClass = "risk-high";
                                } elseif (
                                    (int)$part["stock"] < 10
                                ) {
                                    $rowClass = "risk-medium";
                                }
                                ?>

                                <tr class="<?php echo $rowClass; ?>">

                                    <td>
                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $part["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                            ?>
                                        </strong>

                                        <div class="small text-muted">
                                            <?php
                                            echo htmlspecialchars(
                                                $part["type"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                            ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int)$part["stock"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int)$part["used_30"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int)$part["used_90"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo number_format(
                                            $part["avg_monthly"],
                                            1
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        if (
                                            $part["months_cover"] === null
                                        ) {
                                            echo "資料不足";
                                        } else {
                                            echo number_format(
                                                $part["months_cover"],
                                                1
                                            ) . " 個月";
                                        }
                                        ?>
                                    </td>

                                    <td class="money">
                                        NT$
                                        <?php
                                        echo number_format(
                                            $part["unit_profit"],
                                            0
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        if (
                                            (int)$part["suggested_reorder"] > 0
                                        ) {
                                        ?>
                                            <span class="badge bg-danger fs-6">
                                                約
                                                <?php
                                                echo (int)$part["suggested_reorder"];
                                                ?>
                                                個
                                            </span>
                                        <?php
                                        } else {
                                        ?>
                                            <span class="badge bg-success">
                                                暫不補貨
                                            </span>
                                        <?php
                                        }
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
                    🧠 AI 補貨分析結果
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

            AI 建議僅作為庫存管理與補貨決策的輔助。
            實際採購仍需由車行依供應商交期、資金、實際需求與現場庫存確認。

        </div>

    <?php } ?>

</div>

</body>

</html>
