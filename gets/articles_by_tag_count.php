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

    include '../checkToken.php';
    include '../db_connect.php';

    $tagId = $_GET['tag'] ?? '';

    if (empty($tagId) || !is_numeric($tagId)) {
        http_response_code(400);
        echo json_encode(["error" => "Valid tag ID is required"]);
        exit;
    }

    $sql = "
        SELECT COUNT(DISTINCT a.article_id) as count
        FROM articles a
        JOIN article_tags at ON a.article_id = at.article_id
        WHERE at.tag_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tagId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch tag articles count"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $count = $row['count'];

    echo json_encode(["count" => $count]);

    $conn->close();

?>
