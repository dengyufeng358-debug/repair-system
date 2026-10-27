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

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";

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
    只允許讀取目前車行自己的案件
*/
$sql = "
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

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("資料讀取失敗：" . $conn->error);
}

$stmt->bind_param("ii", $id, $shop_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows <= 0) {
    $stmt->close();

    header("Location: maintenance_list.php?status=invalid");
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();

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
    "維修完畢",
    "已通知領車"
];

$plate_number = $row["plate_number"];
$customer_name = $row["customer_name"];
$item_name = $row["item_name"];
$status = $row["status"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        $message = "操作驗證失敗，請重新整理頁面";
    }

    $plate_number = strtoupper(trim($_POST["plate_number"] ?? ""));
    $plate_number = str_replace([" ", "-"], "", $plate_number);

    $customer_name = trim($_POST["customer_name"] ?? "");
    $item_name = trim($_POST["item_name"] ?? "");
    $status = trim($_POST["status"] ?? "");

    if ($message === "") {

        if ($plate_number === "") {

            $message = "請輸入車牌號碼";

        } elseif ($item_name === "") {

            $message = "請選擇保養項目";

        } elseif (!in_array($item_name, $item_options, true)) {

            $message = "保養項目不正確";

        } elseif (!in_array($status, $status_options, true)) {

            $message = "保養狀態不正確";
        }
    }

    if ($message === "") {

        /*
            修改時同時限制 id 與 shop_id
        */
        $update_sql = "
            UPDATE maintenance_records
            SET
                plate_number = ?,
                customer_name = ?,
                item_name = ?,
                status = ?
            WHERE id = ?
              AND shop_id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {

            $message = "無法建立修改指令：" . $conn->error;

        } else {

            $update_stmt->bind_param(
                "ssssii",
                $plate_number,
                $customer_name,
                $item_name,
                $status,
                $id,
                $shop_id
            );

            if ($update_stmt->execute()) {

                $update_stmt->close();

                header("Location: maintenance_list.php?status=updated");
                exit();

            } else {

                $message = "修改失敗：" . $update_stmt->error;
                $update_stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改保養案件</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .shop-box{
            background:#fff8db;
            border-left:5px solid #ffc107;
            border-radius:10px;
            padding:12px 15px;
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-warning shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold text-dark">修改保養案件</span>
        <a href="maintenance_list.php" class="btn btn-dark btn-sm">返回列表</a>
    </div>
</nav>

<div class="container py-5">

    <div class="card shadow-sm border-0 p-4 mx-auto" style="max-width:700px;">

        <h3 class="mb-3 fw-bold">編輯保養資料</h3>

        <div class="shop-box mb-4">
            目前車行：
            <strong>
                <?php echo htmlspecialchars($shop_name, ENT_QUOTES, "UTF-8"); ?>
            </strong>
        </div>

        <?php if ($message !== "") { ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php } ?>

        <form method="post">

            <input type="hidden" name="id" value="<?php echo $id; ?>">

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
                <label class="form-label">車牌</label>

                <input
                    type="text"
                    name="plate_number"
                    class="form-control"
                    value="<?php echo htmlspecialchars($plate_number, ENT_QUOTES, "UTF-8"); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">客戶姓名</label>

                <input
                    type="text"
                    name="customer_name"
                    class="form-control"
                    value="<?php echo htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8"); ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">保養項目</label>

                <select name="item_name" class="form-select" required>

                    <option value="">請選擇保養項目</option>

                    <?php foreach ($item_options as $option) { ?>

                        <option
                            value="<?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>"
                            <?php echo ($item_name === $option) ? "selected" : ""; ?>
                        >
                            <?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">目前狀態</label>

                <select name="status" class="form-select" required>

                    <?php foreach ($status_options as $option) { ?>

                        <option
                            value="<?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>"
                            <?php echo ($status === $option) ? "selected" : ""; ?>
                        >
                            <?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <div class="row g-2">

                <div class="col-md-6">
                    <button type="submit" class="btn btn-success w-100">
                        儲存修改
                    </button>
                </div>

                <div class="col-md-6">
                    <a href="maintenance_list.php" class="btn btn-secondary w-100">
                        取消
                    </a>
                </div>

            </div>

        </form>

    </div>

</div>

</body>
</html>
