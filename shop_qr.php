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

/*
    讀取目前車行代碼
*/
$shop_code = "";

$sql = "
    SELECT shop_code
    FROM shops
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $shop_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if (
        $result &&
        $result->num_rows > 0
    ) {
        $row =
            $result->fetch_assoc();

        $shop_code =
            $row["shop_code"] ?? "";
    }

    $stmt->close();
}

/*
    自動判斷目前網站基本網址
*/
$isHttps =
    (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    );

$scheme =
    $isHttps
        ? "https"
        : "http";

$host =
    $_SERVER["HTTP_HOST"] ?? "localhost";

/*
    目前檔案路徑：
    /repair-system/shop_qr.php
    轉成：
    /repair-system
*/
$scriptDir =
    rtrim(
        str_replace(
            "\\",
            "/",
            dirname(
                $_SERVER["SCRIPT_NAME"]
                ?? "/repair-system/shop_qr.php"
            )
        ),
        "/"
    );

$autoBaseUrl =
    $scheme .
    "://" .
    $host .
    $scriptDir;

/*
    使用者若輸入公開網址就優先使用
*/
$baseUrl =
    trim(
        $_GET["base_url"]
        ?? $autoBaseUrl
    );

$baseUrl =
    rtrim(
        $baseUrl,
        "/"
    );

$customerUrl =
    $baseUrl .
    "/index.php?shop=" .
    urlencode(
        $shop_code
    );

$isLocalhost =
    (
        stripos(
            $baseUrl,
            "localhost"
        ) !== false
        ||
        stripos(
            $baseUrl,
            "127.0.0.1"
        ) !== false
    );
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>車行專屬 QR Code</title>

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

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:12px;
            padding:14px 16px;
        }

        #qrcode {
            width:260px;
            height:260px;
            margin:25px auto;
            padding:10px;
            background:white;
            border-radius:14px;
            box-shadow:
                0 4px 16px
                rgba(0,0,0,.10);

            display:flex;
            align-items:center;
            justify-content:center;
        }

        #qrcode img,
        #qrcode canvas {
            max-width:100%;
            height:auto !important;
        }

        .url-box {
            word-break:break-all;
            background:#f8f9fa;
            border-radius:10px;
            padding:14px;
            border:1px solid #dee2e6;
        }

        .warning-box {
            background:#fff3cd;
            border:1px solid #ffe69c;
            border-radius:12px;
            padding:14px;
        }

        .success-box {
            background:#d1e7dd;
            border:1px solid #badbcc;
            border-radius:12px;
            padding:14px;
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-dark shadow-sm">

    <div class="container">

        <span class="navbar-brand fw-bold">
            📱 車行專屬 QR Code
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

            <div class="shop-box mb-4">

                <div>
                    <strong>目前車行：</strong>

                    <?php
                    echo htmlspecialchars(
                        $shop_name,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </div>

                <div class="mt-1">
                    <strong>車行代碼：</strong>

                    <?php
                    echo htmlspecialchars(
                        $shop_code,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </div>

            </div>

            <?php if ($shop_code === "") { ?>

                <div class="alert alert-danger">

                    找不到目前車行的 shop_code，
                    請先確認 shops 資料表中的 shop_code 已設定。

                </div>

            <?php } else { ?>

                <h3 class="fw-bold mb-3">
                    1. 設定網站網址
                </h3>

                <p class="text-muted">

                    如果只是電腦自己測試，
                    可以先用 localhost。

                    如果要用手機掃描，
                    請改成 ngrok 公開網址或電腦區網網址。

                </p>

                <form
                    method="get"
                    class="mb-4"
                >

                    <label class="form-label fw-bold">
                        網站基本網址
                    </label>

                    <div class="input-group">

                        <input
                            type="text"
                            name="base_url"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $baseUrl,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            placeholder="例如：https://xxxx.ngrok-free.app/repair-system"
                            required
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            重新產生 QR
                        </button>

                    </div>

                </form>

                <?php if ($isLocalhost) { ?>

                    <div class="warning-box mb-4">

                        <strong>⚠️ 目前是 localhost</strong>

                        <br>

                        QR Code 可以產生，
                        但手機掃描後通常無法開啟，
                        因為手機的 localhost 是「手機自己」，
                        不是你的電腦。

                    </div>

                <?php } else { ?>

                    <div class="success-box mb-4">

                        ✅ 目前網址不是 localhost，
                        可以拿手機實際掃描測試。

                    </div>

                <?php } ?>

                <h3 class="fw-bold mb-3">
                    2. 客戶專屬網址
                </h3>

                <div class="url-box">

                    <?php
                    echo htmlspecialchars(
                        $customerUrl,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

                <div
                    class="d-flex gap-2 flex-wrap mt-3"
                >

                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        onclick="copyUrl()"
                    >
                        📋 複製網址
                    </button>

                    <a
                        href="<?php
                            echo htmlspecialchars(
                                $customerUrl,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        target="_blank"
                        class="btn btn-outline-success"
                    >
                        🔗 測試開啟
                    </a>

                </div>

                <hr class="my-4">

                <h3 class="fw-bold text-center">
                    3. 車行 QR Code
                </h3>

                <div id="qrcode"></div>

                <p class="text-center text-muted">

                    客戶掃描後，
                    會自動帶入目前車行
                    <?php
                    echo htmlspecialchars(
                        $shop_code,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>。

                </p>

                <div class="text-center">

                    <button
                        type="button"
                        class="btn btn-dark"
                        onclick="downloadQR()"
                    >
                        ⬇️ 儲存 QR Code 圖片
                    </button>

                </div>

            <?php } ?>

        </div>

    </div>

</div>

<?php if ($shop_code !== "") { ?>

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
></script>

<script>

const customerUrl =
    <?php
    echo json_encode(
        $customerUrl,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    ?>;

function makeQR() {

    const box =
        document.getElementById(
            "qrcode"
        );

    box.innerHTML = "";

    if (
        typeof QRCode ===
        "undefined"
    ) {

        box.innerHTML =
            "<div class='text-danger text-center'>" +
            "QR Code 套件載入失敗，請確認電腦有網路。" +
            "</div>";

        return;
    }

    new QRCode(
        box,
        {
            text:
                customerUrl,

            width:
                240,

            height:
                240,

            correctLevel:
                QRCode
                .CorrectLevel
                .H
        }
    );
}

function copyUrl() {

    navigator
        .clipboard
        .writeText(
            customerUrl
        )
        .then(
            function() {
                alert(
                    "網址已複製"
                );
            }
        );
}

function downloadQR() {

    const box =
        document.getElementById(
            "qrcode"
        );

    const canvas =
        box.querySelector(
            "canvas"
        );

    const img =
        box.querySelector(
            "img"
        );

    let dataUrl = "";

    if (canvas) {

        dataUrl =
            canvas.toDataURL(
                "image/png"
            );

    } else if (img) {

        dataUrl =
            img.src;

    }

    if (!dataUrl) {

        alert(
            "QR Code 尚未產生"
        );

        return;
    }

    const link =
        document.createElement(
            "a"
        );

    link.href =
        dataUrl;

    link.download =
        <?php
        echo json_encode(
            $shop_code .
            "_QR.png"
        );
        ?>;

    document
        .body
        .appendChild(
            link
        );

    link.click();

    document
        .body
        .removeChild(
            link
        );
}

makeQR();

</script>

<?php } ?>

</body>
</html>
