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

$status_message = $_GET["status"] ?? "";

/*
    刪除保養案件
    只允許刪除目前車行自己的資料
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_maintenance"])
) {
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        $id <= 0 ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {
        header("Location: maintenance_list.php?status=invalid");
        exit();
    }

    try {
        $conn->begin_transaction();

        /*
            先刪除這家車行的進度紀錄
        */
        $progress_sql = "
            DELETE FROM maintenance_progress
            WHERE maintenance_id = ?
              AND shop_id = ?
        ";

        $progress_stmt = $conn->prepare($progress_sql);

        if (!$progress_stmt) {
            throw new Exception("無法刪除進度資料");
        }

        $progress_stmt->bind_param("ii", $id, $shop_id);
        $progress_stmt->execute();
        $progress_stmt->close();

        /*
            再刪除保養案件
        */
        $delete_sql = "
            DELETE FROM maintenance_records
            WHERE id = ?
              AND shop_id = ?
        ";

        $delete_stmt = $conn->prepare($delete_sql);

        if (!$delete_stmt) {
            throw new Exception("無法建立刪除指令");
        }

        $delete_stmt->bind_param("ii", $id, $shop_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows <= 0) {
            $delete_stmt->close();
            throw new Exception("找不到案件或案件不屬於目前車行");
        }

        $delete_stmt->close();

        $conn->commit();

        header("Location: maintenance_list.php?status=deleted");
        exit();

    } catch (Throwable $error) {

        try {
            $conn->rollback();
        } catch (Throwable $rollback_error) {
        }

        header("Location: maintenance_list.php?status=delete_failed");
        exit();
    }
}

/*
    只顯示目前車行的保養案件
*/
$sql = "
    SELECT
        id,
        plate_number,
        customer_name,
        item_name,
        status,
        created_at
    FROM maintenance_records
    WHERE shop_id = ?
    ORDER BY plate_number ASC, id DESC
";

$stmt = $conn->prepare($sql);
$result = null;

if ($stmt) {
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$no = 1;
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保養管理</title>

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

        .action-group{
            display:flex;
            flex-wrap:wrap;
            gap:5px;
        }

        @media(max-width:768px){
            .page-title-row{
                display:block !important;
            }

            .page-title-row .btn{
                width:100%;
                margin-top:12px;
            }
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-warning shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold text-dark">保養管理</span>
        <a href="dashboard.php" class="btn btn-dark btn-sm">回首頁</a>
    </div>
</nav>

<div class="container py-4">

    <?php if ($status_message === "deleted") { ?>
        <div class="alert alert-success">
            保養案件已刪除。
        </div>
    <?php } elseif ($status_message === "delete_failed") { ?>
        <div class="alert alert-danger">
            刪除失敗，請重新操作。
        </div>
    <?php } elseif ($status_message === "invalid") { ?>
        <div class="alert alert-danger">
            操作要求不正確。
        </div>
    <?php } elseif ($status_message === "added") { ?>
        <div class="alert alert-success">
            保養案件新增成功。
        </div>
    <?php } elseif ($status_message === "updated") { ?>
        <div class="alert alert-success">
            保養案件修改成功。
        </div>
    <?php } ?>

    <div class="shop-box mb-4">
        目前車行：
        <strong>
            <?php echo htmlspecialchars($shop_name, ENT_QUOTES, "UTF-8"); ?>
        </strong>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 page-title-row">
        <h1 class="mb-0">保養案件列表</h1>

        <a href="add_maintenance.php" class="btn btn-success">
            ＋ 新增保養案件
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">

                    <thead class="table-warning">
                        <tr>
                            <th>ID</th>
                            <th>車牌</th>
                            <th>客戶姓名</th>
                            <th>保養項目</th>
                            <th>目前狀態</th>
                            <th>建立時間</th>
                            <th>操作</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($result && $result->num_rows > 0) { ?>

                        <?php while ($row = $result->fetch_assoc()) { ?>

                            <tr>

                                <td><?php echo $no++; ?></td>

                                <td class="fw-bold">
                                    <?php echo htmlspecialchars($row["plate_number"], ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["customer_name"], ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["item_name"], ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td>
                                    <?php
                                    $current_status = $row["status"];

                                    if ($current_status === "待處理") {
                                        echo '<span class="badge bg-secondary">待處理</span>';
                                    } elseif ($current_status === "處理中") {
                                        echo '<span class="badge bg-primary">處理中</span>';
                                    } elseif ($current_status === "待交車") {
                                        echo '<span class="badge bg-warning text-dark">待交車</span>';
                                    } elseif ($current_status === "已完成") {
                                        echo '<span class="badge bg-success">已完成</span>';
                                    } elseif ($current_status === "維修完畢") {
                                        echo '<span class="badge bg-dark">維修完畢</span>';
                                    } elseif ($current_status === "已通知領車") {
                                        echo '<span class="badge bg-success">已通知領車</span>';
                                    } else {
                                        echo '<span class="badge bg-dark">' .
                                             htmlspecialchars($current_status, ENT_QUOTES, "UTF-8") .
                                             '</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["created_at"], ENT_QUOTES, "UTF-8"); ?>
                                </td>

                                <td>
                                    <div class="action-group">

                                        <a
                                            href="report_progress.php?id=<?php echo (int)$row["id"]; ?>"
                                            class="btn btn-primary btn-sm"
                                        >
                                            回報進度
                                        </a>

                                        <a
                                            href="view_progress.php?plate=<?php echo urlencode($row["plate_number"]); ?>"
                                            class="btn btn-dark btn-sm"
                                        >
                                            查看進度
                                        </a>

                                        <a
                                            href="edit_maintenance.php?id=<?php echo (int)$row["id"]; ?>"
                                            class="btn btn-warning btn-sm"
                                        >
                                            修改
                                        </a>

                                        <form
                                            method="post"
                                            class="m-0"
                                            onsubmit="return confirm('確定要刪除這筆保養案件嗎？');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int)$row["id"]; ?>"
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

                                            <button
                                                type="submit"
                                                name="delete_maintenance"
                                                class="btn btn-danger btn-sm"
                                            >
                                                刪除
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                目前車行尚無保養案件
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

</body>
</html>
