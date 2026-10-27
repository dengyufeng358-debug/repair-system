<?php
include "db.php";

header(
    "Content-Type: application/json; charset=UTF-8"
);

$query = isset($_GET["q"])
    ? trim($_GET["q"])
    : "";

if (
    mb_strlen(
        $query,
        "UTF-8"
    ) < 2
) {
    echo json_encode(
        [],
        JSON_UNESCAPED_UNICODE
    );
    exit();
}

/*
    最多只回傳 10 間，
    即使未來有幾百、幾千間車行，
    首頁也不會一次載入全部。
*/
$like =
    "%" .
    $query .
    "%";

$sql = "
    SELECT
        id,
        shop_name,
        shop_code
    FROM shops
    WHERE shop_name LIKE ?
       OR shop_code LIKE ?
    ORDER BY
        CASE
            WHEN shop_code = ? THEN 0
            WHEN shop_name = ? THEN 1
            ELSE 2
        END,
        shop_name ASC
    LIMIT 10
";

$stmt =
    $conn->prepare($sql);

$shops = [];

if ($stmt) {

    $upper_query =
        strtoupper(
            $query
        );

    $stmt->bind_param(
        "ssss",
        $like,
        $like,
        $upper_query,
        $query
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $shops[] = [
            "id" =>
                (int)$row["id"],

            "shop_name" =>
                $row["shop_name"],

            "shop_code" =>
                $row["shop_code"]
        ];
    }

    $stmt->close();
}

echo json_encode(
    $shops,
    JSON_UNESCAPED_UNICODE
);
?>
