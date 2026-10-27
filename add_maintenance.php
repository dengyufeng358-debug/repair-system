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

$plate_number = "";
$customer_name = "";
$item_name = "";
$status = "待處理";

/*
    如果從車牌登記頁跳過來，
    自動帶入剛剛登記的車牌。
*/
if (
    $_SERVER["REQUEST_METHOD"] !== "POST" &&
    isset($_GET["plate"])
) {

    $plate_number =
        strtoupper(
            trim($_GET["plate"])
        );

    $plate_number =
        str_replace(
            [" ", "-"],
            "",
            $plate_number
        );
}

$from_plate_registration =
    isset($_GET["registered"]) &&
    $_GET["registered"] === "1";

$item_options = [
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

$status_options = [
    "待處理",
    "處理中",
    "待交車",
    "維修完畢"
];

if (isset($_POST["submit"])) {

    $plate_number =
        strtoupper(
            trim(
                $_POST["plate_number"] ?? ""
            )
        );

    $plate_number =
        str_replace(
            [" ", "-"],
            "",
            $plate_number
        );

    $customer_name =
        trim(
            $_POST["customer_name"] ?? ""
        );

    $item_name =
        trim(
            $_POST["item_name"] ?? ""
        );

    $status =
        trim(
            $_POST["status"] ?? ""
        );

    if (
        $plate_number === "" ||
        $item_name === "" ||
        $status === ""
    ) {

        $message =
            "請填寫完整資料";

    } elseif (
        !preg_match(
            "/^[A-Z0-9]{4,7}$/",
            $plate_number
        )
    ) {

        $message =
            "車牌格式不正確";

    } elseif (
        !in_array(
            $item_name,
            $item_options,
            true
        )
    ) {

        $message =
            "保養項目不正確";

    } elseif (
        !in_array(
            $status,
            $status_options,
            true
        )
    ) {

        $message =
            "保養狀態不正確";

    } else {

        try {

            /*
                建立保養案件時，
                同時確保 plate_records 也有這台車。

                因此：
                - 先從車牌登記進來可以
                - 直接從新增保養案件進來也可以
            */
            $conn->begin_transaction();

            $check_plate_sql = "
                SELECT id
                FROM plate_records
                WHERE shop_id = ?
                  AND plate_number = ?
                LIMIT 1
            ";

            $check_plate_stmt =
                $conn->prepare(
                    $check_plate_sql
                );

            if (!$check_plate_stmt) {
                throw new Exception(
                    "無法確認車牌資料"
                );
            }

            $check_plate_stmt->bind_param(
                "is",
                $shop_id,
                $plate_number
            );

            $check_plate_stmt->execute();

            $check_plate_result =
                $check_plate_stmt->get_result();

            $plate_exists =
                $check_plate_result &&
                $check_plate_result->num_rows > 0;

            $check_plate_stmt->close();

            /*
                車牌還沒登記時自動建立。
            */
            if (!$plate_exists) {

                $plate_note =
                    "建立保養案件時自動登記";

                $plate_sql = "
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

                $plate_stmt =
                    $conn->prepare(
                        $plate_sql
                    );

                if (!$plate_stmt) {
                    throw new Exception(
                        "無法建立車牌紀錄"
                    );
                }

                $plate_stmt->bind_param(
                    "iss",
                    $shop_id,
                    $plate_number,
                    $plate_note
                );

                if (!$plate_stmt->execute()) {

                    $plate_error =
                        $plate_stmt->error;

                    $plate_stmt->close();

                    throw new Exception(
                        "車牌登記失敗：" .
                        $plate_error
                    );
                }

                $plate_stmt->close();
            }

            /*
                真正建立保養案件。
            */
            $sql = "
                INSERT INTO maintenance_records
                (
                    shop_id,
                    plate_number,
                    customer_name,
                    item_name,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt =
                $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "無法建立新增指令"
                );
            }

            $stmt->bind_param(
                "issss",
                $shop_id,
                $plate_number,
                $customer_name,
                $item_name,
                $status
            );

            if (!$stmt->execute()) {

                $insert_error =
                    $stmt->error;

                $stmt->close();

                throw new Exception(
                    "保養案件新增失敗：" .
                    $insert_error
                );
            }

            $stmt->close();

            $conn->commit();

            header(
                "Location: maintenance_list.php?status=added"
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

    <title>新增保養案件</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
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

<nav class="navbar navbar-dark bg-success shadow-sm">

    <div class="container">

        <span class="navbar-brand fw-bold">
            新增保養案件
        </span>

        <a
            href="maintenance_list.php"
            class="btn btn-light btn-sm"
        >
            回保養列表
        </a>

    </div>

</nav>

<div class="container py-5">

    <div
        class="card shadow-sm border-0 p-4 mx-auto"
        style="max-width:700px;"
    >

        <h3 class="mb-3 fw-bold">
            建立保養案件
        </h3>

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

        <?php if (
            $from_plate_registration
        ) { ?>

            <div class="alert alert-info">

                ✅ 車牌已完成登記。
                現在請填寫客戶姓名、保養項目與狀態。

                <br>

                <strong>
                    按下「新增保養案件」後，
                    才會出現在保養列表與客戶查詢。
                </strong>

            </div>

        <?php } ?>

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

                <label class="form-label">
                    車牌號碼
                </label>

                <input
                    type="text"
                    name="plate_number"
                    class="form-control"
                    placeholder="例如：ABC1234"
                    value="<?php
                        echo htmlspecialchars(
                            $plate_number,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label class="form-label">
                    客戶姓名
                </label>

                <input
                    type="text"
                    name="customer_name"
                    class="form-control"
                    placeholder="請輸入客戶姓名"
                    value="<?php
                        echo htmlspecialchars(
                            $customer_name,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                >

            </div>

            <div class="mb-3">

                <label class="form-label">
                    保養項目
                </label>

                <select
                    name="item_name"
                    class="form-select"
                    required
                >

                    <option value="">
                        請選擇保養項目
                    </option>

                    <?php foreach (
                        $item_options
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
                                $item_name ===
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
                    目前狀態
                </label>

                <select
                    name="status"
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
                                $status ===
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

            <button
                type="submit"
                name="submit"
                class="btn btn-success w-100"
            >
                新增保養案件
            </button>

        </form>

    </div>

</div>

</body>

</html>
