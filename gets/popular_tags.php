<?php

    include '../allowedOrigins.php';

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }

    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    include '../db_connect.php';

    $sql = "
        SELECT 
            t.tag_id,
            t.name,
            COUNT(at.article_id) AS usage_count
        FROM tags t
        JOIN article_tags at ON t.tag_id = at.tag_id
        GROUP BY t.tag_id
        ORDER BY usage_count DESC, t.name ASC
        LIMIT 20
    ";

    $result = $conn->query($sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Database query failed"]);
        exit;
    }

    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = [
            "id" => (int)$row["tag_id"],
            "name" => $row["name"],
            "count" => (int)$row["usage_count"]
        ];
    }

    echo json_encode($tags);
    $conn->close();

?>