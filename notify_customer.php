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
$plate = "";

if (isset($_GET["plate"])) {
    $plate = strtoupper(trim($_GET["plate"]));
    $plate = str_replace([" ", "-"], "", $plate);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $plate = strtoupper(trim($_POST["plate"] ?? ""));
    $plate = str_replace([" ", "-"], "", $plate);

    if ($plate === "") {

        $message = "
            <div class='alert alert-danger text-center'>
                ❌ 請輸入車牌號碼
            </div>
        ";

    } else {

        /*
            只查目前車行自己的案件
        */
        $sql = "
            SELECT
                id,
                status
            FROM maintenance_records
            WHERE shop_id = ?
              AND plate_number = ?
            ORDER BY id DESC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $message = "
                <div class='alert alert-danger text-center'>
                    系統查詢失敗
                </div>
            ";

        } else {

            $stmt->bind_param(
                "is",
                $shop_id,
                $plate
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {

                $can_notify = true;

                while ($row = $result->fetch_assoc()) {

                    if (
                        $row["status"] !== "待交車" &&
                        $row["status"] !== "已完成" &&
                        $row["status"] !== "維修完畢" &&
                        $row["status"] !== "已通知領車"
                    ) {
                        $can_notify = false;
                    }
                }

                if ($can_notify) {

                    /*
                        更新也只更新目前車行
                    */
                    $update_sql = "
                        UPDATE maintenance_records
                        SET status = '已通知領車'
                        WHERE shop_id = ?
                          AND plate_number = ?
                    ";

                    $update_stmt = $conn->prepare($update_sql);

                    if (!$update_stmt) {

                        $message = "
                            <div class='alert alert-danger text-center'>
                                更新失敗
                            </div>
                        ";

                    } else {

                        $update_stmt->bind_param(
                            "is",
                            $shop_id,
                            $plate
                        );

                        if ($update_stmt->execute()) {

                            $message = "
                                <div class='alert alert-success text-center'>
                                    ✅ 已成功通知客戶領車
                                </div>
                            ";

                        } else {

                            $message = "
                                <div class='alert alert-danger text-center'>
                                    更新失敗
                                </div>
                            ";
                        }

                        $update_stmt->close();
                    }

                } else {

                    $message = "
                        <div class='alert alert-danger text-center'>
                            ❌ 尚有未完成維修項目
                        </div>
                    ";
                }

            } else {

                $message = "
                    <div class='alert alert-danger text-center'>
                        ❌ 目前車行找不到此車牌
                    </div>
                ";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>維修完成通知</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .notify-card{
            max-width:760px;
            margin:0 auto;
            border:0;
            border-radius:18px;
        }

        .shop-box{
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }
    </style>
</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold">維修完成通知</span>
        <a href="dashboard.php" class="btn btn-light btn-sm">回首頁</a>
    </div>
</nav>

<div class="container py-5">

    <div class="card shadow-sm notify-card">
        <div class="card-body p-4 p-md-5">

            <h1 class="text-center fw-bold mb-4">
                📢 維修完成通知
            </h1>

            <div class="shop-box mb-4">
                目前車行：
                <strong>
                    <?php echo htmlspecialchars($shop_name, ENT_QUOTES, "UTF-8"); ?>
                </strong>
            </div>

            <?php echo $message; ?>

            <form method="post">

                <div class="mb-4">

                    <label class="form-label fw-bold">
                        車牌號碼
                    </label>

                    <input
                        type="text"
                        name="plate"
                        class="form-control form-control-lg"
                        value="<?php echo htmlspecialchars($plate, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="請輸入車牌號碼"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="btn btn-success btn-lg w-100"
                >
                    通知客戶
                </button>

            </form>

        </div>
    </div>

</div>

</body>
</html>
