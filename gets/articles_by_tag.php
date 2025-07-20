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
    $limit = (int) ($_GET['limit'] ?? 10);
    $page = (int) ($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    if (empty($tagId) || !is_numeric($tagId)) {
        http_response_code(400);
        echo json_encode(["error" => "Valid tag ID is required"]);
        exit;
    }

    $sql = "
        SELECT 
            a.article_id,
            u.username,
            u.user_profile_url,
            a.created_at AS post_date,
            a.title,
            a.about,
            a.slug,
            COUNT(f.user_id) AS favorites
        FROM articles a
        JOIN users u ON a.author_id = u.user_id
        JOIN article_tags at ON a.article_id = at.article_id
        LEFT JOIN favorites f ON a.article_id = f.article_id
        WHERE at.tag_id = ?
        GROUP BY a.article_id
        ORDER BY a.created_at DESC
        LIMIT ?
        OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $tagId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch tag articles"]);
        exit;
    }

    $articles = [];

    while ($row = $result->fetch_assoc()) {
        $articles[] = [
            "id" => $row["article_id"],
            "username" => $row["username"],
            "userProfileImageUrl" => $row["user_profile_url"],
            "postDate" => $row["post_date"],
            "title" => $row["title"],
            "about" => $row["about"],
            "favorites" => (int)$row["favorites"],
            "slug" => $row["slug"]
        ];
    }

    echo json_encode(["articles" => $articles]);

    $conn->close();

?>
