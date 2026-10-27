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
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

$message = "";

/*
    保留維修紀錄頁原本的日期篩選
*/
$start_date = isset($_REQUEST["start_date"])
    ? trim($_REQUEST["start_date"])
    : "";

$end_date = isset($_REQUEST["end_date"])
    ? trim($_REQUEST["end_date"])
    : "";

/*
    取得要修改的維修紀錄 ID
*/
$repair_id = isset($_POST["id"])
    ? (int)$_POST["id"]
    : (
        isset($_GET["id"])
        ? (int)$_GET["id"]
        : 0
    );

if ($repair_id <= 0) {
    redirectBack(
        "invalid_id",
        $start_date,
        $end_date
    );
}

/*
    =========================
    讀取目前車行自己的維修紀錄
    =========================
*/
$record_sql = "
    SELECT
        id,
        part_id,
        quantity,
        unit_cost,
        unit_price,
        repair_result,
        repair_date
    FROM repair_records
    WHERE id = ?
      AND shop_id = ?
    LIMIT 1
";

$record_stmt =
    $conn->prepare($record_sql);

if (!$record_stmt) {
    die(
        "維修紀錄讀取失敗：" .
        $conn->error
    );
}

$record_stmt->bind_param(
    "ii",
    $repair_id,
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

    redirectBack(
        "not_found",
        $start_date,
        $end_date
    );
}

$record =
    $record_result->fetch_assoc();

$record_stmt->close();

/*
    原本資料
*/
$original_part_id =
    (int)$record["part_id"];

$original_quantity =
    (int)$record["quantity"];

/*
    表單預設值
*/
$part_id =
    (int)$record["part_id"];

$quantity =
    (int)$record["quantity"];

$repair_result =
    $record["repair_result"];

$repair_date =
    $record["repair_date"];

/*
    =========================
    讀取目前車行自己的零件
    =========================
*/
$parts_sql = "
    SELECT
        id,
        name,
        type,
        stock,
        cost,
        price
    FROM parts
    WHERE shop_id = ?
    ORDER BY
        name ASC,
        type ASC,
        id ASC
";

$parts_stmt =
    $conn->prepare($parts_sql);

$parts_result = null;

if ($parts_stmt) {

    $parts_stmt->bind_param(
        "i",
        $shop_id
    );

    $parts_stmt->execute();

    $parts_result =
        $parts_stmt->get_result();
}

/*
    =========================
    按下儲存修改
    =========================
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
        安全權杖
    */
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

    /*
        取得修改後資料
    */
    $part_id = isset($_POST["part_id"])
        ? (int)$_POST["part_id"]
        : 0;

    $quantity = isset($_POST["quantity"])
        ? (int)$_POST["quantity"]
        : 0;

    $repair_result =
        isset($_POST["repair_result"])
        ? trim($_POST["repair_result"])
        : "";

    $repair_date =
        isset($_POST["repair_date"])
        ? trim($_POST["repair_date"])
        : "";

    if ($message === "") {

        if ($part_id <= 0) {

            $message =
                "請選擇零件";

        } elseif ($quantity <= 0) {

            $message =
                "使用數量必須大於 0";

        } elseif ($repair_result === "") {

            $message =
                "請選擇維修結果";

        } elseif (
            !preg_match(
                "/^\d{4}-\d{2}-\d{2}$/",
                $repair_date
            )
        ) {

            $message =
                "維修日期格式不正確";
        }
    }

    if ($message === "") {

        try {

            /*
                開始交易。
                原本庫存恢復、新庫存扣除、紀錄修改
                必須全部一起成功。
            */
            $conn->begin_transaction();

            /*
                再次鎖定並確認這筆維修紀錄
                確實屬於目前車行。
            */
            $lock_record_sql = "
                SELECT
                    part_id,
                    quantity
                FROM repair_records
                WHERE id = ?
                  AND shop_id = ?
                FOR UPDATE
            ";

            $lock_record_stmt =
                $conn->prepare(
                    $lock_record_sql
                );

            if (!$lock_record_stmt) {
                throw new Exception(
                    "無法鎖定維修紀錄"
                );
            }

            $lock_record_stmt->bind_param(
                "ii",
                $repair_id,
                $shop_id
            );

            $lock_record_stmt->execute();

            $lock_record_result =
                $lock_record_stmt->get_result();

            if (
                !$lock_record_result ||
                $lock_record_result->num_rows <= 0
            ) {
                $lock_record_stmt->close();

                throw new Exception(
                    "找不到維修紀錄，或紀錄不屬於目前車行"
                );
            }

            $locked_record =
                $lock_record_result->fetch_assoc();

            $old_part_id =
                (int)$locked_record["part_id"];

            $old_quantity =
                (int)$locked_record["quantity"];

            $lock_record_stmt->close();

            /*
                先把原本使用掉的庫存加回。
                如果原零件已被刪除，這一步可能影響 0 筆，
                但不阻止修改成其他現存零件。
            */
            $restore_sql = "
                UPDATE parts
                SET stock = stock + ?
                WHERE id = ?
                  AND shop_id = ?
            ";

            $restore_stmt =
                $conn->prepare($restore_sql);

            if (!$restore_stmt) {
                throw new Exception(
                    "無法恢復原本庫存"
                );
            }

            $restore_stmt->bind_param(
                "iii",
                $old_quantity,
                $old_part_id,
                $shop_id
            );

            if (!$restore_stmt->execute()) {

                $restore_error =
                    $restore_stmt->error;

                $restore_stmt->close();

                throw new Exception(
                    "恢復原本庫存失敗：" .
                    $restore_error
                );
            }

            $restore_stmt->close();

            /*
                鎖定修改後選擇的新零件。
                ★ 必須屬於目前車行。
            */
            $part_sql = "
                SELECT
                    stock,
                    cost,
                    price
                FROM parts
                WHERE id = ?
                  AND shop_id = ?
                FOR UPDATE
            ";

            $part_stmt =
                $conn->prepare($part_sql);

            if (!$part_stmt) {
                throw new Exception(
                    "無法讀取選擇的零件"
                );
            }

            $part_stmt->bind_param(
                "ii",
                $part_id,
                $shop_id
            );

            $part_stmt->execute();

            $part_result =
                $part_stmt->get_result();

            if (
                !$part_result ||
                $part_result->num_rows <= 0
            ) {
                $part_stmt->close();

                throw new Exception(
                    "找不到選擇的零件，或零件不屬於目前車行"
                );
            }

            $selected_part =
                $part_result->fetch_assoc();

            $current_stock =
                (int)$selected_part["stock"];

            $unit_cost =
                (float)$selected_part["cost"];

            $unit_price =
                (float)$selected_part["price"];

            $part_stmt->close();

            /*
                檢查加回舊數量後，
                現在是否足夠扣除修改後數量。
            */
            if (
                $current_stock < $quantity
            ) {
                throw new Exception(
                    "庫存不足，目前可使用庫存為：" .
                    $current_stock
                );
            }

            /*
                扣除修改後使用數量。
                ★ 只扣目前車行自己的零件。
            */
            $deduct_sql = "
                UPDATE parts
                SET stock = stock - ?
                WHERE id = ?
                  AND shop_id = ?
                  AND stock >= ?
            ";

            $deduct_stmt =
                $conn->prepare($deduct_sql);

            if (!$deduct_stmt) {
                throw new Exception(
                    "無法扣除新庫存"
                );
            }

            $deduct_stmt->bind_param(
                "iiii",
                $quantity,
                $part_id,
                $shop_id,
                $quantity
            );

            if (!$deduct_stmt->execute()) {

                $deduct_error =
                    $deduct_stmt->error;

                $deduct_stmt->close();

                throw new Exception(
                    "扣除庫存失敗：" .
                    $deduct_error
                );
            }

            if (
                $deduct_stmt->affected_rows <= 0
            ) {
                $deduct_stmt->close();

                throw new Exception(
                    "庫存不足，無法完成修改"
                );
            }

            $deduct_stmt->close();

            /*
                修改維修紀錄。
                成本與售價重新記錄目前所選零件的價格。
                ★ WHERE 同時限制 shop_id。
            */
            $update_sql = "
                UPDATE repair_records
                SET
                    part_id = ?,
                    quantity = ?,
                    unit_cost = ?,
                    unit_price = ?,
                    repair_result = ?,
                    repair_date = ?
                WHERE id = ?
                  AND shop_id = ?
            ";

            $update_stmt =
                $conn->prepare($update_sql);

            if (!$update_stmt) {
                throw new Exception(
                    "無法建立修改指令"
                );
            }

            $update_stmt->bind_param(
                "iiddssii",
                $part_id,
                $quantity,
                $unit_cost,
                $unit_price,
                $repair_result,
                $repair_date,
                $repair_id,
                $shop_id
            );

            if (!$update_stmt->execute()) {

                $update_error =
                    $update_stmt->error;

                $update_stmt->close();

                throw new Exception(
                    "維修紀錄修改失敗：" .
                    $update_error
                );
            }

            $update_stmt->close();

            /*
                三個步驟全部成功才正式儲存。
            */
            $conn->commit();

            redirectBack(
                "updated",
                $start_date,
                $end_date
            );

        } catch (Throwable $error) {

            try {
                $conn->rollback();
            } catch (Throwable $rollback_error) {
                // rollback 失敗時不額外中斷
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

    <title>修改維修紀錄</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .edit-card {
            max-width:760px;
            margin:0 auto;
            border:0;
            border-radius:18px;
        }

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }

        .record-box {
            background:#f1f5f9;
            border-radius:10px;
            padding:12px 15px;
            color:#475569;
        }

        .form-label {
            font-weight:bold;
        }

        .help-text {
            color:#64748b;
            font-size:14px;
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-warning shadow-sm">

    <div class="container">

        <span
            class="navbar-brand mb-0 h1 text-dark fw-bold"
        >
            修改維修紀錄
        </span>

        <a
            href="<?php
                echo htmlspecialchars(
                    buildReturnUrl(
                        $start_date,
                        $end_date
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                );
            ?>"
            class="btn btn-dark btn-sm"
        >
            回維修紀錄
        </a>

    </div>

</nav>

<div class="container py-5">

    <div class="card edit-card shadow-sm">

        <div class="card-body p-4 p-md-5">

            <h3 class="fw-bold mb-3">
                編輯維修資料
            </h3>

            <div class="shop-box mb-3">

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

            <div class="record-box mb-4">

                維修紀錄編號：

                <strong>
                    #<?php echo $repair_id; ?>
                </strong>

            </div>

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

            <?php if (
                !$parts_result ||
                $parts_result->num_rows <= 0
            ) { ?>

                <div class="alert alert-warning">

                    目前車行沒有可使用的零件，
                    請先建立零件庫存。

                </div>

                <a
                    href="add_part.php"
                    class="btn btn-primary w-100"
                >
                    前往新增零件
                </a>

            <?php } else { ?>

                <form method="post">

                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo $repair_id; ?>"
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

                    <input
                        type="hidden"
                        name="start_date"
                        value="<?php
                            echo htmlspecialchars(
                                $start_date,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="end_date"
                        value="<?php
                            echo htmlspecialchars(
                                $end_date,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                    >

                    <!-- 零件 -->
                    <div class="mb-3">

                        <label class="form-label">
                            零件名稱
                        </label>

                        <select
                            name="part_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                請選擇零件
                            </option>

                            <?php while (
                                $part =
                                $parts_result->fetch_assoc()
                            ) { ?>

                                <option
                                    value="<?php
                                        echo (int)$part["id"];
                                    ?>"
                                    <?php
                                    if (
                                        (int)$part["id"] ===
                                        (int)$part_id
                                    ) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $part["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                    ／類型：
                                    <?php
                                    echo htmlspecialchars(
                                        $part["type"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                    ／目前庫存：
                                    <?php
                                    echo (int)$part["stock"];
                                    ?>

                                    ／成本：
                                    NT$
                                    <?php
                                    echo number_format(
                                        $part["cost"],
                                        0
                                    );
                                    ?>

                                    ／售價：
                                    NT$
                                    <?php
                                    echo number_format(
                                        $part["price"],
                                        0
                                    );
                                    ?>

                                </option>

                            <?php } ?>

                        </select>

                        <div class="help-text mt-1">
                            只會顯示目前車行自己的零件。
                            修改後會自動恢復原本庫存，再扣除新的使用數量。
                        </div>

                    </div>

                    <!-- 使用數量 -->
                    <div class="mb-3">

                        <label class="form-label">
                            使用數量
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            class="form-control"
                            min="1"
                            step="1"
                            value="<?php echo (int)$quantity; ?>"
                            required
                        >

                    </div>

                    <!-- 維修結果 -->
                    <div class="mb-3">

                        <label class="form-label">
                            維修結果
                        </label>

                        <select
                            name="repair_result"
                            class="form-select"
                            required
                        >

                            <option value="">
                                請選擇維修結果
                            </option>

                            <?php
                            $result_options = [
                                "維修完成",
                                "更換完成",
                                "保養完成",
                                "待料中",
                                "檢查中",
                                "無法維修"
                            ];

                            foreach (
                                $result_options
                                as $option
                            ) {
                            ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $option,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                    ?>"
                                    <?php
                                    if (
                                        $repair_result ===
                                        $option
                                    ) {
                                        echo "selected";
                                    }
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

                    <!-- 維修日期 -->
                    <div class="mb-4">

                        <label class="form-label">
                            維修日期
                        </label>

                        <input
                            type="date"
                            name="repair_date"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $repair_date,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>

                    <div class="row g-2">

                        <div class="col-md-6">

                            <button
                                type="submit"
                                class="btn btn-warning w-100 fw-bold"
                            >
                                儲存修改
                            </button>

                        </div>

                        <div class="col-md-6">

                            <a
                                href="<?php
                                    echo htmlspecialchars(
                                        buildReturnUrl(
                                            $start_date,
                                            $end_date
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                class="btn btn-secondary w-100"
                            >
                                取消修改
                            </a>

                        </div>

                    </div>

                </form>

            <?php } ?>

        </div>

    </div>

</div>

</body>
</html>

<?php

/*
    建立返回 repair_list.php 的網址，
    保留日期篩選條件。
*/
function buildReturnUrl(
    $start_date,
    $end_date,
    $status = ""
) {
    $parameters = [];

    if ($status !== "") {
        $parameters["status"] =
            $status;
    }

    if (
        preg_match(
            "/^\d{4}-\d{2}-\d{2}$/",
            $start_date
        )
    ) {
        $parameters["start_date"] =
            $start_date;
    }

    if (
        preg_match(
            "/^\d{4}-\d{2}-\d{2}$/",
            $end_date
        )
    ) {
        $parameters["end_date"] =
            $end_date;
    }

    if (empty($parameters)) {
        return "repair_list.php";
    }

    return
        "repair_list.php?" .
        http_build_query(
            $parameters
        );
}

/*
    修改成功或錯誤時返回維修紀錄頁。
*/
function redirectBack(
    $status,
    $start_date,
    $end_date
) {
    $url =
        buildReturnUrl(
            $start_date,
            $end_date,
            $status
        );

    header(
        "Location: " . $url
    );

    exit();
}

?>
