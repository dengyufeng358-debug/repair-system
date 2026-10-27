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

/*
    建立安全權杖
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/*
    取得案件 ID
*/
$id = isset($_POST["id"])
    ? (int)$_POST["id"]
    : (
        isset($_GET["id"])
        ? (int)$_GET["id"]
        : 0
    );

if ($id <= 0) {
    header("Location: maintenance_list.php");
    exit();
}

/*
    只讀取目前車行自己的保養案件
*/
$record_sql = "
    SELECT
        id,
        plate_number,
        customer_name,
        item_name,
        status
    FROM maintenance_records
    WHERE id = ?
      AND shop_id = ?
    LIMIT 1
";

$record_stmt = $conn->prepare($record_sql);

if (!$record_stmt) {
    die("案件讀取失敗：" . $conn->error);
}

$record_stmt->bind_param(
    "ii",
    $id,
    $shop_id
);

$record_stmt->execute();

$record_result =
    $record_stmt->get_result();

if (
    !$record_result ||
    $record_result->num_rows <= 0
) {
    $record_stmt->close();

    header("Location: maintenance_list.php?status=invalid");
    exit();
}

$record =
    $record_result->fetch_assoc();

$record_stmt->close();

$message = "";

$progress_text = "";

$progress_status =
    $record["status"];

/*
    可選進度狀態
*/
$status_options = [
    "待處理",
    "處理中",
    "待交車",
    "已完成"
];

/*
    如果目前狀態不在下拉選單中，
    預設顯示「待處理」
*/
if (
    !in_array(
        $progress_status,
        $status_options,
        true
    )
) {
    $progress_status = "待處理";
}

/*
    送出進度回報
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit"])
) {

    $csrf_token =
        $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $csrf_token
        )
    ) {
        $message =
            "操作驗證失敗，請重新整理頁面";
    }

    $progress_text =
        trim($_POST["progress_text"] ?? "");

    $progress_status =
        trim($_POST["progress_status"] ?? "");

    if ($message === "") {

        if ($progress_text === "") {

            $message =
                "請填寫進度說明";

        } elseif (
            !in_array(
                $progress_status,
                $status_options,
                true
            )
        ) {

            $message =
                "進度狀態不正確";
        }
    }

    if ($message === "") {

        try {

            /*
                使用交易：
                新增進度 + 更新案件狀態
                必須一起成功
            */
            $conn->begin_transaction();

            /*
                再次確認案件屬於目前車行
            */
            $check_sql = "
                SELECT id
                FROM maintenance_records
                WHERE id = ?
                  AND shop_id = ?
                FOR UPDATE
            ";

            $check_stmt =
                $conn->prepare($check_sql);

            if (!$check_stmt) {
                throw new Exception(
                    "無法確認案件資料"
                );
            }

            $check_stmt->bind_param(
                "ii",
                $id,
                $shop_id
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            if (
                !$check_result ||
                $check_result->num_rows <= 0
            ) {
                $check_stmt->close();

                throw new Exception(
                    "找不到案件，或案件不屬於目前車行"
                );
            }

            $check_stmt->close();

            /*
                寫入進度紀錄
                ★ 一定寫入 shop_id
            */
            $insert_sql = "
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

            $insert_stmt =
                $conn->prepare($insert_sql);

            if (!$insert_stmt) {
                throw new Exception(
                    "無法建立進度紀錄"
                );
            }

            $insert_stmt->bind_param(
                "iiss",
                $shop_id,
                $id,
                $progress_text,
                $progress_status
            );

            if (!$insert_stmt->execute()) {

                $error =
                    $insert_stmt->error;

                $insert_stmt->close();

                throw new Exception(
                    "回報進度失敗：" .
                    $error
                );
            }

            $insert_stmt->close();

            /*
                更新目前車行案件狀態
            */
            $update_sql = "
                UPDATE maintenance_records
                SET status = ?
                WHERE id = ?
                  AND shop_id = ?
            ";

            $update_stmt =
                $conn->prepare($update_sql);

            if (!$update_stmt) {
                throw new Exception(
                    "無法更新案件狀態"
                );
            }

            $update_stmt->bind_param(
                "sii",
                $progress_status,
                $id,
                $shop_id
            );

            if (!$update_stmt->execute()) {

                $error =
                    $update_stmt->error;

                $update_stmt->close();

                throw new Exception(
                    "案件狀態更新失敗：" .
                    $error
                );
            }

            $update_stmt->close();

            $conn->commit();

            /*
                view_progress.php 現在用車牌查詢，
                所以成功後直接帶車牌回去。
            */
            $plate_url =
                urlencode(
                    $record["plate_number"]
                );

            header(
                "Location: view_progress.php?plate=" .
                $plate_url
            );

            exit();

        } catch (Throwable $error) {

            try {
                $conn->rollback();
            } catch (Throwable $rollback_error) {
            }

            $message =
                $error->getMessage();
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

    <title>回報保養進度</title>

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
        }

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }

        .case-card {
            border-radius:16px;
        }

    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-primary shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            回報保養進度
        </span>

        <a
            href="maintenance_list.php"
            class="btn btn-light btn-sm"
        >
            回保養列表
        </a>

    </div>

</nav>

<div class="container py-4">

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

    <!-- 案件資訊 -->
    <div class="card shadow-sm border-0 mb-4 case-card">

        <div class="card-body">

            <h4 class="fw-bold mb-3">
                案件資訊
            </h4>

            <p class="mb-2">

                <strong>
                    案件編號：
                </strong>

                <?php
                echo (int)$record["id"];
                ?>

            </p>

            <p class="mb-2">

                <strong>
                    車牌號碼：
                </strong>

                <?php
                echo htmlspecialchars(
                    $record["plate_number"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </p>

            <p class="mb-2">

                <strong>
                    客戶姓名：
                </strong>

                <?php
                echo htmlspecialchars(
                    $record["customer_name"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </p>

            <p class="mb-2">

                <strong>
                    保養項目：
                </strong>

                <?php
                echo htmlspecialchars(
                    $record["item_name"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </p>

            <p class="mb-0">

                <strong>
                    目前狀態：
                </strong>

                <?php
                echo htmlspecialchars(
                    $record["status"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </p>

        </div>

    </div>

    <div class="row g-4">

        <!-- 左側表單 -->
        <div class="col-lg-7">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4">

                    <h4 class="fw-bold mb-4">
                        填寫進度回報
                    </h4>

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
                        id="progressForm"
                    >

                        <input
                            type="hidden"
                            name="id"
                            value="<?php echo $id; ?>"
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
                                目前進度狀態
                            </label>

                            <select
                                name="progress_status"
                                id="progress_status"
                                class="form-select"
                                required
                            >

                                <?php foreach (
                                    $status_options
                                    as $option
                                ) { ?>

                                    <option
                                        value="<?php
                                            echo htmlspecialchars(
                                                $option,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        <?php
                                        echo (
                                            $progress_status ===
                                            $option
                                        )
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $option,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </option>

                                <?php } ?>

                            </select>

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                進度說明
                            </label>

                            <textarea
                                name="progress_text"
                                id="progress_text"
                                class="form-control"
                                rows="5"
                                placeholder="例如：已完成機油更換，目前檢查煞車系統中"
                                required
                            ><?php
                                echo htmlspecialchars(
                                    $progress_text,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?></textarea>

                        </div>

                        <button
                            type="submit"
                            name="submit"
                            class="btn btn-primary w-100"
                        >
                            送出進度回報
                        </button>

                    </form>

                </div>

            </div>

        </div>

        <!-- 右側語音 -->
        <div class="col-lg-5">

            <div class="voice-box shadow-sm">

                <h4 class="fw-bold mb-3">
                    🎤 語音輸入進度
                </h4>

                <p class="text-muted">

                    按下開始後直接說進度內容，
                    例如：已完成換機油，目前待交車

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
                        onclick="clearVoiceText()"
                    >
                        清空內容
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-success"
                        onclick="submitProgressForm()"
                    >
                        送出回報
                    </button>

                </div>

                <p class="mb-2">

                    <strong>
                        辨識狀態：
                    </strong>

                    <span id="voice_status">
                        待命中
                    </span>

                </p>

                <p class="mb-2">

                    <strong>
                        辨識結果：
                    </strong>

                </p>

                <div
                    class="voice-result"
                    id="voice_result"
                >
                </div>

                <hr>

                <div class="small text-muted">

                    建議說法：
                    <br>
                    已完成換機油，目前處理中
                    <br>
                    已完成煞車檢查，待交車
                    <br>
                    保養已完成

                </div>

            </div>

        </div>

    </div>

</div>

<script>

function speak(text) {

    if (
        "speechSynthesis" in window
    ) {

        const utterance =
            new SpeechSynthesisUtterance(
                text
            );

        utterance.lang = "zh-TW";

        window
            .speechSynthesis
            .speak(utterance);
    }
}

function detectStatus(text) {

    /*
        先判斷待交車，
        避免句子同時包含「完成」時
        被誤判成已完成。
    */
    if (
        text.includes("待交車") ||
        text.includes("領車")
    ) {
        return "待交車";
    }

    if (
        text.includes("處理中") ||
        text.includes("檢查中") ||
        text.includes("維修中")
    ) {
        return "處理中";
    }

    if (
        text.includes("已完成") ||
        text.includes("完成")
    ) {
        return "已完成";
    }

    return "待處理";
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

    recognition.lang =
        "zh-TW";

    recognition.interimResults =
        false;

    recognition.maxAlternatives =
        1;

    document
        .getElementById("voice_status")
        .textContent =
        "正在聆聽...";

    document
        .getElementById("voice_result")
        .textContent =
        "";

    recognition.onresult =
        function(event) {

            const text =
                event
                    .results[0][0]
                    .transcript;

            const status =
                detectStatus(text);

            document
                .getElementById(
                    "voice_result"
                )
                .textContent =
                text;

            document
                .getElementById(
                    "progress_text"
                )
                .value =
                text;

            document
                .getElementById(
                    "progress_status"
                )
                .value =
                status;

            document
                .getElementById(
                    "voice_status"
                )
                .textContent =
                "辨識完成";

            speak(
                "已填入進度回報"
            );
        };

    recognition.onerror =
        function(event) {

            document
                .getElementById(
                    "voice_status"
                )
                .textContent =
                "辨識失敗";

            document
                .getElementById(
                    "voice_result"
                )
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
                    .getElementById(
                        "voice_status"
                    )
                    .textContent ===
                "正在聆聽..."
            ) {

                document
                    .getElementById(
                        "voice_status"
                    )
                    .textContent =
                    "待命中";
            }
        };

    recognition.start();
}

function clearVoiceText() {

    document
        .getElementById(
            "progress_text"
        )
        .value =
        "";

    document
        .getElementById(
            "voice_result"
        )
        .textContent =
        "";

    document
        .getElementById(
            "voice_status"
        )
        .textContent =
        "已清空";
}

function submitProgressForm() {

    const progressText =
        document
            .getElementById(
                "progress_text"
            )
            .value
            .trim();

    if (progressText === "") {

        speak(
            "請先輸入或辨識進度內容"
        );

        return;
    }

    speak(
        "正在送出進度回報"
    );

    setTimeout(
        function() {

            /*
                requestSubmit 可以保留
                submit 按鈕名稱，
                確保 PHP 的 isset($_POST["submit"])
                仍會成立。
            */
            document
                .getElementById(
                    "progressForm"
                )
                .requestSubmit();

        },
        500
    );
}

</script>

</body>
</html>
