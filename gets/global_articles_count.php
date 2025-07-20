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

    $result = $conn->query("SELECT COUNT(*) as count from articles");

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch articles count"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $count = $row['count'];

    echo json_encode(["count" => $count]);

    $conn->close();

?>
