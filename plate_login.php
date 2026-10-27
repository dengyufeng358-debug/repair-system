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
$shop_name = $_SESSION["shop_name"] ?? "未設定車行";

$message = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit"])
) {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $message = "操作驗證失敗，請重新整理頁面";
    }

    $plate_number = strtoupper(trim($_POST["plate_number"] ?? ""));
    $plate_number = str_replace([" ", "-"], "", $plate_number);

    $note = trim($_POST["note"] ?? "");

    if ($message === "") {

        if ($plate_number === "") {

            $message = "請輸入車牌號碼";

        } elseif (!preg_match("/^[A-Z0-9]{4,7}$/", $plate_number)) {

            $message = "車牌格式不正確";

        } else {

            /*
                先檢查「目前車行」是否已經登記過同一車牌。
                同一車行不要重複新增 plate_records。
            */
            $check_sql = "
                SELECT id
                FROM plate_records
                WHERE shop_id = ?
                  AND plate_number = ?
                ORDER BY id DESC
                LIMIT 1
            ";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {

                $message = "無法檢查車牌資料：" . $conn->error;

            } else {

                $check_stmt->bind_param(
                    "is",
                    $shop_id,
                    $plate_number
                );

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                $already_exists =
                    $check_result &&
                    $check_result->num_rows > 0;

                $check_stmt->close();

                /*
                    尚未登記才新增。
                */
                if (!$already_exists) {

                    $sql = "
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

                    $stmt = $conn->prepare($sql);

                    if (!$stmt) {

                        $message = "無法建立登記指令：" . $conn->error;

                    } else {

                        $stmt->bind_param(
                            "iss",
                            $shop_id,
                            $plate_number,
                            $note
                        );

                        if (!$stmt->execute()) {

                            $message =
                                "新增失敗：" .
                                $stmt->error;
                        }

                        $stmt->close();
                    }
                }

                /*
                    車牌登記成功後，不停在這一頁。

                    直接把車牌帶到新增保養案件，
                    使用者接著填「客戶姓名、保養項目、狀態」。

                    真正建立 maintenance_records 後，
                    保養案件與客戶查詢才會有資料。
                */
                if ($message === "") {

                    header(
                        "Location: add_maintenance.php?plate=" .
                        urlencode($plate_number) .
                        "&registered=1"
                    );

                    exit();
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

    <title>語音登記車牌</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .voice-box {
            border:2px dashed #0d6efd;
            background:#f8fbff;
            border-radius:16px;
            padding:20px;
        }

        .voice-result {
            min-height:90px;
            border-radius:12px;
            background:#fff;
            border:1px solid #dee2e6;
            padding:12px;
            font-size:18px;
        }

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-dark shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            語音登記車牌
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

    <div class="row g-4">

        <div class="col-lg-7">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4">

                    <h3 class="fw-bold mb-4">
                        車牌登入表單
                    </h3>

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

                    <form
                        method="post"
                        id="plateForm"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                                echo htmlspecialchars(
                                    $_SESSION["csrf_token"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                        >

                        <div class="mb-3">

                            <label class="form-label">
                                車牌格式
                            </label>

                            <select
                                id="plate_mode"
                                class="form-select"
                            >

                                <option value="7">
                                    7碼（ABC-1234）
                                </option>

                                <option value="6">
                                    6碼（ABC-123）
                                </option>

                                <option value="4">
                                    4碼（AB-12）
                                </option>

                                <option value="auto">
                                    自動
                                </option>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                車牌號碼
                            </label>

                            <input
                                type="text"
                                name="plate_number"
                                id="plate_number"
                                class="form-control form-control-lg"
                                placeholder="例如 ABC-1234"
                                required
                            >

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                備註
                            </label>

                            <input
                                type="text"
                                name="note"
                                id="note"
                                class="form-control"
                                placeholder="可不填"
                            >

                        </div>

                        <button
                            type="submit"
                            name="submit"
                            class="btn btn-dark w-100"
                        >
                            登記車牌並建立保養案件
                        </button>

                    </form>

                </div>

            </div>

        </div>

        <div class="col-lg-5">

            <div class="voice-box shadow-sm">

                <h4 class="fw-bold mb-3">
                    🎤 Google 語音輸入
                </h4>

                <p class="text-muted">
                    按下按鈕後直接說車牌，例如：A B C 1 2 3 4
                </p>

                <div class="d-grid gap-2 mb-3">

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="startVoiceInput()"
                    >
                        開始語音輸入
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        onclick="clearPlate()"
                    >
                        清空車牌
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-success"
                        onclick="submitPlateForm()"
                    >
                        送出登記
                    </button>

                </div>

                <p class="mb-2">
                    <strong>辨識狀態：</strong>
                    <span id="voice_status">待命中</span>
                </p>

                <p class="mb-2">
                    <strong>原始結果：</strong>
                </p>

                <div
                    class="voice-result mb-3"
                    id="voice_raw"
                ></div>

                <p class="mb-2">
                    <strong>修正結果：</strong>
                </p>

                <div
                    class="voice-result"
                    id="voice_result"
                ></div>

                <hr>

                <div class="small text-muted">
                    建議說法：
                    <br>
                    A B C 1 2 3 4
                    <br>
                    B E N 8 8 8
                    <br>
                    A B 1 2
                    <br><br>
                    7碼固定：ABC-1234
                    <br>
                    6碼固定：ABC-123
                    <br>
                    4碼固定：AB-12
                </div>

            </div>

        </div>

    </div>

</div>

<script>

function speak(text) {

    if (!("speechSynthesis" in window)) {
        return;
    }

    const utterance =
        new SpeechSynthesisUtterance(text);

    utterance.lang = "zh-TW";

    window.speechSynthesis.speak(
        utterance
    );
}

function basicClean(text) {

    return text
        .toUpperCase()
        .replace(/車牌/g, "")
        .replace(/號碼/g, "")
        .replace(/號/g, "")
        .replace(/登記/g, "")
        .replace(/-/g, "")
        .replace(/\s+/g, "")
        .replace(/一/g, "1")
        .replace(/二/g, "2")
        .replace(/三/g, "3")
        .replace(/四/g, "4")
        .replace(/五/g, "5")
        .replace(/六/g, "6")
        .replace(/七/g, "7")
        .replace(/八/g, "8")
        .replace(/九/g, "9")
        .replace(/零/g, "0");
}

function toLetter(ch) {

    const map = {
        "0":"O",
        "1":"E",
        "2":"Z",
        "5":"S",
        "6":"G",
        "7":"C",
        "8":"B"
    };

    if (/[A-Z]/.test(ch)) {
        return ch;
    }

    return map[ch] || ch;
}

function toDigit(ch) {

    const map = {
        "O":"0",
        "Q":"0",
        "D":"0",
        "I":"1",
        "L":"1",
        "E":"1",
        "Z":"2",
        "S":"5",
        "G":"6",
        "B":"8",
        "C":"7"
    };

    if (/[0-9]/.test(ch)) {
        return ch;
    }

    return map[ch] || ch;
}

function formatPlateWithDash(
    letters,
    numbers
) {
    return letters + "-" + numbers;
}

function smartNormalize7(raw) {

    let text =
        basicClean(raw)
        .replace(/[^A-Z0-9]/g, "")
        .substring(0, 7);

    let arr =
        text.split("");

    for (
        let i = 0;
        i < arr.length;
        i++
    ) {

        if (i <= 2) {
            arr[i] = toLetter(arr[i]);
        } else {
            arr[i] = toDigit(arr[i]);
        }
    }

    let letters =
        arr.slice(0, 3).join("");

    let numbers =
        arr.slice(3, 7).join("");

    return formatPlateWithDash(
        letters,
        numbers
    );
}

function smartNormalize6(raw) {

    let text =
        basicClean(raw)
        .replace(/[^A-Z0-9]/g, "")
        .substring(0, 6);

    let arr =
        text.split("");

    for (
        let i = 0;
        i < arr.length;
        i++
    ) {

        if (i <= 2) {
            arr[i] = toLetter(arr[i]);
        } else {
            arr[i] = toDigit(arr[i]);
        }
    }

    let letters =
        arr.slice(0, 3).join("");

    let numbers =
        arr.slice(3, 6).join("");

    return formatPlateWithDash(
        letters,
        numbers
    );
}

function smartNormalize4(raw) {

    let text =
        basicClean(raw)
        .replace(/[^A-Z0-9]/g, "")
        .substring(0, 4);

    let arr =
        text.split("");

    for (
        let i = 0;
        i < arr.length;
        i++
    ) {

        if (i <= 1) {
            arr[i] = toLetter(arr[i]);
        } else {
            arr[i] = toDigit(arr[i]);
        }
    }

    let letters =
        arr.slice(0, 2).join("");

    let numbers =
        arr.slice(2, 4).join("");

    return formatPlateWithDash(
        letters,
        numbers
    );
}

function autoNormalize(raw) {

    let text =
        basicClean(raw)
        .replace(/[^A-Z0-9]/g, "");

    if (text.length >= 7) {
        return smartNormalize7(text);
    }

    if (text.length === 6) {
        return smartNormalize6(text);
    }

    if (text.length === 4) {
        return smartNormalize4(text);
    }

    return text;
}

function normalizeByMode(
    raw,
    mode
) {

    if (mode === "7") {
        return smartNormalize7(raw);
    }

    if (mode === "6") {
        return smartNormalize6(raw);
    }

    if (mode === "4") {
        return smartNormalize4(raw);
    }

    return autoNormalize(raw);
}

function validatePlate(
    plate,
    mode
) {

    if (mode === "7") {
        return /^[A-Z]{3}-[0-9]{4}$/.test(
            plate
        );
    }

    if (mode === "6") {
        return /^[A-Z]{3}-[0-9]{3}$/.test(
            plate
        );
    }

    if (mode === "4") {
        return /^[A-Z]{2}-[0-9]{2}$/.test(
            plate
        );
    }

    return (
        /^[A-Z]{3}-[0-9]{4}$/.test(plate) ||
        /^[A-Z]{3}-[0-9]{3}$/.test(plate) ||
        /^[A-Z]{2}-[0-9]{2}$/.test(plate)
    );
}

function startVoiceInput() {

    const SpeechRecognition =
        window.SpeechRecognition ||
        window.webkitSpeechRecognition;

    if (!SpeechRecognition) {

        alert(
            "你的瀏覽器不支援語音輸入，請使用 Chrome 或 Edge"
        );

        return;
    }

    const recognition =
        new SpeechRecognition();

    recognition.lang = "zh-TW";
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    document
        .getElementById("voice_status")
        .textContent =
        "正在聆聽...";

    document
        .getElementById("voice_raw")
        .textContent =
        "";

    document
        .getElementById("voice_result")
        .textContent =
        "";

    recognition.onresult =
        function(event) {

            const rawText =
                event.results[0][0].transcript;

            const mode =
                document
                    .getElementById("plate_mode")
                    .value;

            const plate =
                normalizeByMode(
                    rawText,
                    mode
                );

            document
                .getElementById("voice_raw")
                .textContent =
                rawText;

            document
                .getElementById("voice_result")
                .textContent =
                plate;

            document
                .getElementById("plate_number")
                .value =
                plate;

            document
                .getElementById("voice_status")
                .textContent =
                "辨識完成";

            if (
                validatePlate(
                    plate,
                    mode
                )
            ) {

                speak(
                    "已辨識車牌 " +
                    plate
                );

            } else {

                speak(
                    "我已辨識，但建議你再確認一次"
                );
            }
        };

    recognition.onerror =
        function(event) {

            document
                .getElementById("voice_status")
                .textContent =
                "辨識失敗";

            document
                .getElementById("voice_result")
                .textContent =
                "錯誤：" +
                event.error;

            speak(
                "語音辨識失敗，請再試一次"
            );
        };

    recognition.onend =
        function() {

            if (
                document
                    .getElementById("voice_status")
                    .textContent ===
                "正在聆聽..."
            ) {

                document
                    .getElementById("voice_status")
                    .textContent =
                    "待命中";
            }
        };

    recognition.start();
}

function clearPlate() {

    document
        .getElementById("plate_number")
        .value =
        "";

    document
        .getElementById("voice_raw")
        .textContent =
        "";

    document
        .getElementById("voice_result")
        .textContent =
        "";

    document
        .getElementById("voice_status")
        .textContent =
        "已清空";
}

function submitPlateForm() {

    const plate =
        document
            .getElementById("plate_number")
            .value
            .trim();

    const mode =
        document
            .getElementById("plate_mode")
            .value;

    if (plate === "") {

        speak(
            "請先輸入或辨識車牌"
        );

        return;
    }

    if (
        !validatePlate(
            plate,
            mode
        )
    ) {

        if (
            !confirm(
                "目前車牌格式看起來可能不正確，還要送出嗎？"
            )
        ) {
            return;
        }
    }

    speak(
        "正在送出車牌登記"
    );

    setTimeout(
        function() {

            document
                .getElementById("plateForm")
                .requestSubmit();

        },
        500
    );
}

</script>

</body>

</html>
