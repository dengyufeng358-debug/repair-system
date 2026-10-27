<?php

header("Content-Type: text/html; charset=UTF-8");

/*
    =========================================
    1. 確認 OpenAI API Key
    =========================================
*/

$apiKey = getenv("OPENAI_API_KEY");

echo "<h2>🤖 OpenAI API 測試</h2>";

if (!$apiKey) {

    echo "<p style='color:red;'>";
    echo "❌ PHP 讀不到 OPENAI_API_KEY";
    echo "</p>";

    echo "<p>";
    echo "請確認 Windows 環境變數已設定，";
    echo "並重新啟動 XAMPP。";
    echo "</p>";

    exit();
}

echo "<p style='color:green;'>";
echo "✅ 環境變數讀取成功";
echo "</p>";


/*
    =========================================
    2. 確認 PHP cURL
    =========================================
*/

if (!function_exists("curl_init")) {

    echo "<p style='color:red;'>";
    echo "❌ PHP cURL 尚未啟用";
    echo "</p>";

    exit();
}

echo "<p style='color:green;'>";
echo "✅ PHP cURL 正常";
echo "</p>";


/*
    =========================================
    3. 準備送給 AI 的資料
    =========================================
*/

$url = "https://api.openai.com/v1/responses";

$requestData = [

    "model" => "gpt-5.6-luna",

    "input" =>
        "請只回答這一句：AI API 連線成功"

];


/*
    =========================================
    4. 呼叫 OpenAI API
    =========================================
*/

$ch = curl_init($url);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [

        "Authorization: Bearer " . $apiKey,

        "Content-Type: application/json"

    ],

    CURLOPT_POSTFIELDS =>
        json_encode(
            $requestData,
            JSON_UNESCAPED_UNICODE
        ),

    CURLOPT_TIMEOUT => 30

]);

$response = curl_exec($ch);

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

$curlError =
    curl_error($ch);

curl_close($ch);


/*
    =========================================
    5. 檢查連線錯誤
    =========================================
*/

if ($response === false) {

    echo "<p style='color:red;'>";
    echo "❌ API 連線失敗";
    echo "</p>";

    echo "<pre>";
    echo htmlspecialchars($curlError);
    echo "</pre>";

    exit();
}


/*
    =========================================
    6. 解析 API 回傳
    =========================================
*/

$data =
    json_decode(
        $response,
        true
    );

if ($httpCode < 200 || $httpCode >= 300) {

    echo "<p style='color:red;'>";
    echo "❌ OpenAI API 回傳錯誤";
    echo "</p>";

    echo "<p>";
    echo "HTTP 狀態碼：";
    echo $httpCode;
    echo "</p>";

    if (isset($data["error"]["message"])) {

        echo "<pre>";
        echo htmlspecialchars(
            $data["error"]["message"]
        );
        echo "</pre>";

    } else {

        echo "<pre>";
        echo htmlspecialchars($response);
        echo "</pre>";

    }

    exit();
}


/*
    =========================================
    7. 找出 AI 回答文字
    =========================================
*/

$answer = "";

if (isset($data["output"])) {

    foreach ($data["output"] as $outputItem) {

        if (
            isset($outputItem["type"]) &&
            $outputItem["type"] === "message" &&
            isset($outputItem["content"])
        ) {

            foreach (
                $outputItem["content"]
                as $content
            ) {

                if (
                    isset($content["type"]) &&
                    $content["type"] === "output_text"
                ) {

                    $answer .=
                        $content["text"] ?? "";
                }
            }
        }
    }
}


/*
    =========================================
    8. 顯示測試結果
    =========================================
*/

if ($answer !== "") {

    echo "<hr>";

    echo "<h3>AI 回答：</h3>";

    echo "<div style='
        padding:20px;
        background:#eef6ff;
        border-radius:12px;
        font-size:22px;
        font-weight:bold;
    '>";

    echo htmlspecialchars($answer);

    echo "</div>";

} else {

    echo "<p style='color:red;'>";
    echo "⚠️ API 有回應，但找不到 AI 文字";
    echo "</p>";

    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

?>