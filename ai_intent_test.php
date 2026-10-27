<?php
session_start();

header("Content-Type: text/html; charset=UTF-8");

$apiKey = getenv("OPENAI_API_KEY");

$message = "";
$inputText = "";
$rawAnswer = "";
$parsed = null;

function getResponseText($data)
{
    $answer = "";

    if (!isset($data["output"]) || !is_array($data["output"])) {
        return "";
    }

    foreach ($data["output"] as $outputItem) {

        if (
            isset($outputItem["type"]) &&
            $outputItem["type"] === "message" &&
            isset($outputItem["content"]) &&
            is_array($outputItem["content"])
        ) {

            foreach ($outputItem["content"] as $content) {

                if (
                    isset($content["type"]) &&
                    $content["type"] === "output_text"
                ) {
                    $answer .= $content["text"] ?? "";
                }
            }
        }
    }

    return trim($answer);
}

function cleanJsonText($text)
{
    $text = trim($text);

    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    if (strlen($text) > 0 && $text[0] !== "{") {
        if (preg_match('/\{.*\}/s', $text, $match)) {
            $text = $match[0];
        }
    }

    return trim($text);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $inputText = trim($_POST["text"] ?? "");

    if (!$apiKey) {

        $message = "PHP 讀不到 OPENAI_API_KEY，請重新確認環境變數。";

    } elseif (!function_exists("curl_init")) {

        $message = "PHP cURL 尚未啟用。";

    } elseif ($inputText === "") {

        $message = "請先輸入一句話。";

    } else {

        $prompt = <<<PROMPT
你是「機車維修智慧管理系統」的自然語言指令解析器。

你的工作只有一件事：
把使用者說的中文自然語言，轉成一個 JSON 物件。

只輸出 JSON，不要 Markdown，不要解釋，不要多餘文字。

JSON 格式固定如下：
{
  "action": "",
  "plate": "",
  "item": "",
  "status": "",
  "page": ""
}

action 只能是以下其中一個：
- add_plate：新增或登記車牌
- report：回報某台車的維修/保養進度
- query：查詢某台車的進度或狀態
- open_page：要求開啟系統頁面
- unknown：無法判斷

plate：
- 有車牌就填入
- 統一轉成大寫
- 移除空白與破折號
- 沒有車牌就填空字串

item 只能使用以下名稱之一：
- 機油更換
- 齒輪油更換
- 煞車檢查
- 煞車皮更換
- 輪胎更換
- 電瓶更換
- 火星塞更換
- 傳動保養
- 皮帶更換
- 前總成維修
- 其他檢查
- 若沒有明確項目就填空字串

常見口語對應：
- 換機油、機油好了 → 機油更換
- 換齒輪油 → 齒輪油更換
- 換煞車皮、換剎車皮 → 煞車皮更換
- 檢查煞車、檢查剎車 → 煞車檢查
- 換輪胎 → 輪胎更換
- 換電瓶 → 電瓶更換
- 換火星塞 → 火星塞更換
- 傳動、保養傳動 → 傳動保養
- 換皮帶 → 皮帶更換

status 只能使用以下名稱之一：
- 待處理
- 處理中
- 待交車
- 維修完畢
- 已通知領車
- 若沒有明確狀態就填空字串

常見語意對應：
- 還沒做、還沒處理 → 待處理
- 正在修、正在處理、正在檢查 → 處理中
- 可以準備交車、等等可以牽、準備給客人牽 → 待交車
- 做好了、修好了、完成了 → 維修完畢
- 已經通知客人、通知領車 → 已通知領車

page 只在 action=open_page 時使用，只能是：
- dashboard：首頁總覽
- maintenance：保養案件
- inventory：零件庫存
- repairs：維修紀錄
- plate：車牌登記
- voice：語音控制
- customer：客戶查詢
- 不是開頁指令時填空字串

判斷原則：
1. 不要因為句子裡出現「完成」就忽略後面的「準備交車」。
2. 「機油弄好了，可以準備給客人牽」應判定 status=待交車。
3. 不確定的欄位寧可留空，不要自行猜測。
4. 不要執行任何操作，只解析語意。

範例：
使用者：新增車牌 ABC-1234
輸出：
{"action":"add_plate","plate":"ABC1234","item":"","status":"","page":""}

使用者：幫我查 ABC1234 現在好了沒
輸出：
{"action":"query","plate":"ABC1234","item":"","status":"","page":""}

使用者：ABC1234 那台機油弄好了，可以準備給客人牽了
輸出：
{"action":"report","plate":"ABC1234","item":"機油更換","status":"待交車","page":""}

使用者：幫我打開零件庫存
輸出：
{"action":"open_page","plate":"","item":"","status":"","page":"inventory"}

現在解析這句話：
{$inputText}
PROMPT;

        $requestData = [
            "model" => "gpt-5.6-luna",
            "input" => $prompt,
            "max_output_tokens" => 300
        ];

        $ch = curl_init("https://api.openai.com/v1/responses");

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

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {

            $message = "AI 連線失敗：" . $curlError;

        } else {

            $data = json_decode($response, true);

            if ($httpCode < 200 || $httpCode >= 300) {

                if (isset($data["error"]["message"])) {
                    $message = "OpenAI API 錯誤：" . $data["error"]["message"];
                } else {
                    $message = "OpenAI API 錯誤，HTTP：" . $httpCode;
                }

            } else {

                $rawAnswer = getResponseText($data);
                $jsonText = cleanJsonText($rawAnswer);
                $parsed = json_decode($jsonText, true);

                if (!is_array($parsed)) {
                    $message = "AI 有回答，但 JSON 解析失敗。";
                }
            }
        }
    }
}

function showValue($value)
{
    if ($value === null || $value === "") {
        return "—";
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
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
    <title>AI 自然語言理解測試</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .main-card {
            max-width:900px;
            margin:auto;
            border:0;
            border-radius:20px;
        }

        .result-card {
            border-radius:16px;
            border:1px solid #dee2e6;
        }

        .value-box {
            background:#f8f9fa;
            border-radius:10px;
            padding:12px;
            min-height:50px;
        }

        .json-box {
            background:#111827;
            color:#e5e7eb;
            border-radius:12px;
            padding:18px;
            white-space:pre-wrap;
            word-break:break-word;
        }

        .example-btn {
            text-align:left;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold">
            🧠 AI 自然語言理解測試
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

    <div class="card shadow-sm main-card">

        <div class="card-body p-4 p-md-5">

            <h2 class="fw-bold mb-2">
                第一步：讓 AI 理解技師說的話
            </h2>

            <p class="text-muted mb-4">
                目前只做語意解析測試，不會修改資料庫。
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

                <div class="mb-3">

                    <label class="form-label fw-bold">
                        輸入一句自然語言
                    </label>

                    <textarea
                        name="text"
                        id="text"
                        class="form-control"
                        rows="4"
                        placeholder="例如：ABC1234 那台機油弄好了，可以準備給客人牽了"
                        required
                    ><?php echo htmlspecialchars(
                        $inputText,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?></textarea>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    🤖 交給 AI 理解
                </button>

            </form>

            <hr class="my-4">

            <div class="mb-3 fw-bold">
                快速測試：
            </div>

            <div class="d-grid gap-2">

                <button
                    type="button"
                    class="btn btn-outline-secondary example-btn"
                    onclick="setExample('ABC1234 那台機油弄好了，可以準備給客人牽了')"
                >
                    ABC1234 那台機油弄好了，可以準備給客人牽了
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary example-btn"
                    onclick="setExample('幫我查 ABC1234 現在維修到哪了')"
                >
                    幫我查 ABC1234 現在維修到哪了
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary example-btn"
                    onclick="setExample('幫我打開零件庫存')"
                >
                    幫我打開零件庫存
                </button>

            </div>

            <?php if (is_array($parsed)) { ?>

                <hr class="my-4">

                <h3 class="fw-bold mb-3">
                    ✅ AI 理解結果
                </h3>

                <div class="result-card p-3 p-md-4">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <strong>操作 action</strong>
                            <div class="value-box mt-1">
                                <?php echo showValue(
                                    $parsed["action"] ?? ""
                                ); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <strong>車牌 plate</strong>
                            <div class="value-box mt-1">
                                <?php echo showValue(
                                    $parsed["plate"] ?? ""
                                ); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <strong>項目 item</strong>
                            <div class="value-box mt-1">
                                <?php echo showValue(
                                    $parsed["item"] ?? ""
                                ); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <strong>狀態 status</strong>
                            <div class="value-box mt-1">
                                <?php echo showValue(
                                    $parsed["status"] ?? ""
                                ); ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <strong>頁面 page</strong>
                            <div class="value-box mt-1">
                                <?php echo showValue(
                                    $parsed["page"] ?? ""
                                ); ?>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="mt-4">
                    <strong>AI 原始 JSON：</strong>

                    <div class="json-box mt-2"><?php
                    echo htmlspecialchars(
                        json_encode(
                            $parsed,
                            JSON_PRETTY_PRINT |
                            JSON_UNESCAPED_UNICODE
                        ),
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?></div>
                </div>

            <?php } ?>

        </div>
    </div>

</div>

<script>
function setExample(text) {
    document.getElementById("text").value = text;
    document.getElementById("text").focus();
}
</script>

</body>
</html>
