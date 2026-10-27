<?php
session_start();

if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    header("Location: login.php");
    exit();
}

$username =
    $_SESSION["username"];

$shop_name =
    $_SESSION["shop_name"]
    ?? "目前車行";
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AI 語音控制系統</title>

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
            border:0;
            border-radius:20px;
        }

        .voice-box,
        .response-box,
        .ai-box {
            min-height:110px;
            border-radius:14px;
            padding:16px;
            font-size:18px;
            white-space:pre-wrap;
        }

        .voice-box {
            background:white;
            border:1px solid #dee2e6;
        }

        .response-box {
            background:#eef8f0;
            border:1px solid #b8dfc1;
        }

        .ai-box {
            background:#eef6ff;
            border:1px solid #b9d7ff;
        }

        .shop-box {
            background:#fff8e6;
            border-left:5px solid #ffc107;
            border-radius:12px;
            padding:12px 15px;
        }

        .list-group-item {
            cursor:pointer;
        }

        .list-group-item:hover {
            background:#f8f9fa;
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold">
            🤖 AI 語音控制系統
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
        <strong>目前車行：</strong>
        <?php
        echo htmlspecialchars(
            $shop_name,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>

        ／

        <strong>登入：</strong>
        <?php
        echo htmlspecialchars(
            $username,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>
    </div>

    <div class="card shadow-sm main-card">

        <div class="card-body p-4 p-md-5">

            <h2 class="fw-bold mb-2">
                🎤 用自然語言操作系統
            </h2>

            <p class="text-muted mb-4">
                不需要背固定指令，直接像平常講話即可。
            </p>

            <div class="d-grid gap-2 d-md-flex mb-4">

                <button
                    type="button"
                    class="btn btn-primary flex-fill"
                    onclick="startVoice()"
                >
                    🎤 開始語音
                </button>

                <button
                    type="button"
                    class="btn btn-outline-primary flex-fill"
                    onclick="sendTypedText()"
                >
                    🤖 送給 AI
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary flex-fill"
                    onclick="resetAll()"
                >
                    清空
                </button>

            </div>

            <div class="mb-4">

                <label class="form-label fw-bold">
                    也可以直接打字測試
                </label>

                <textarea
                    id="manualText"
                    class="form-control"
                    rows="3"
                    placeholder="例如：ABC1234 那台機油弄好了，可以準備給客人牽了"
                ></textarea>

            </div>

            <div class="mb-4">
                <strong>🎤 語音 / 輸入內容：</strong>

                <div
                    id="result"
                    class="voice-box mt-2"
                ></div>
            </div>

            <div class="mb-4">
                <strong>🧠 AI 理解：</strong>

                <div
                    id="aiResult"
                    class="ai-box mt-2"
                >
                    尚未執行
                </div>
            </div>

            <div>
                <strong>✅ 系統回應：</strong>

                <div
                    id="response"
                    class="response-box mt-2"
                >
                    尚未執行
                </div>
            </div>

        </div>

    </div>

    <div class="card shadow-sm border-0 mt-4">

        <div class="card-body">

            <h4 class="fw-bold mb-3">
                可以這樣說
            </h4>

            <div class="list-group">

                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    onclick="useExample('新增車牌 ABC1234')"
                >
                    「新增車牌 ABC1234」
                </button>

                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    onclick="useExample('ABC1234 那台機油弄好了，可以準備給客人牽了')"
                >
                    「ABC1234 那台機油弄好了，可以準備給客人牽了」
                </button>

                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    onclick="useExample('幫我查 ABC1234 現在維修到哪了')"
                >
                    「幫我查 ABC1234 現在維修到哪了」
                </button>

                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    onclick="useExample('幫我打開零件庫存')"
                >
                    「幫我打開零件庫存」
                </button>

                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    onclick="useExample('幫我打開保養案件')"
                >
                    「幫我打開保養案件」
                </button>

            </div>

        </div>

    </div>

</div>

<script>
function speak(text) {

    if (!("speechSynthesis" in window)) {
        return;
    }

    window
        .speechSynthesis
        .cancel();

    const utterance =
        new SpeechSynthesisUtterance(
            text
        );

    utterance.lang =
        "zh-TW";

    window
        .speechSynthesis
        .speak(
            utterance
        );
}

function startVoice() {

    const SpeechRecognition =
        window.SpeechRecognition ||
        window.webkitSpeechRecognition;

    if (!SpeechRecognition) {

        alert(
            "瀏覽器不支援語音辨識，請使用 Chrome 或 Edge"
        );

        return;
    }

    const recognition =
        new SpeechRecognition();

    recognition.lang =
        "zh-TW";

    recognition.interimResults =
        false;

    recognition.maxAlternatives =
        1;

    document
        .getElementById("result")
        .textContent =
        "🎤 正在聆聽...";

    recognition.onresult =
        function(event) {

            const text =
                event
                    .results[0][0]
                    .transcript;

            document
                .getElementById("result")
                .textContent =
                text;

            document
                .getElementById("manualText")
                .value =
                text;

            executeAI(
                text
            );
        };

    recognition.onerror =
        function(event) {

            document
                .getElementById("result")
                .textContent =
                "❌ 語音辨識失敗：" +
                event.error;
        };

    recognition.start();
}

function sendTypedText() {

    const text =
        document
            .getElementById("manualText")
            .value
            .trim();

    if (text === "") {

        alert(
            "請先輸入一句話"
        );

        return;
    }

    document
        .getElementById("result")
        .textContent =
        text;

    executeAI(
        text
    );
}

function executeAI(text) {

    document
        .getElementById("aiResult")
        .textContent =
        "🧠 AI 正在理解...";

    document
        .getElementById("response")
        .textContent =
        "⏳ 正在執行...";

    fetch(
        "voice_api.php",
        {
            method:"POST",

            headers:{
                "Content-Type":
                    "application/x-www-form-urlencoded"
            },

            body:
                "text=" +
                encodeURIComponent(
                    text
                )
        }
    )
    .then(
        function(response) {
            return response.json();
        }
    )
    .then(
        function(data) {

            const intent =
                data.data || {};

            document
                .getElementById("aiResult")
                .textContent =
                "操作：" +
                (intent.action || "—") +
                "\n車牌：" +
                (intent.plate || "—") +
                "\n項目：" +
                (intent.item || "—") +
                "\n狀態：" +
                (intent.status || "—") +
                "\n頁面：" +
                (intent.page || "—");

            document
                .getElementById("response")
                .textContent =
                data.message;

            speak(
                data.message
            );

            if (
                data.ok &&
                data.redirect
            ) {

                setTimeout(
                    function() {

                        window.location.href =
                            data.redirect;

                    },
                    1200
                );
            }
        }
    )
    .catch(
        function(error) {

            console.error(
                error
            );

            document
                .getElementById("aiResult")
                .textContent =
                "❌ API 回傳格式錯誤";

            document
                .getElementById("response")
                .textContent =
                "AI 操作失敗，請再試一次";
        }
    );
}

function useExample(text) {

    document
        .getElementById("manualText")
        .value =
        text;

    document
        .getElementById("result")
        .textContent =
        text;

    executeAI(
        text
    );
}

function resetAll() {

    document
        .getElementById("manualText")
        .value =
        "";

    document
        .getElementById("result")
        .textContent =
        "";

    document
        .getElementById("aiResult")
        .textContent =
        "尚未執行";

    document
        .getElementById("response")
        .textContent =
        "尚未執行";
}
</script>

</body>
</html>
