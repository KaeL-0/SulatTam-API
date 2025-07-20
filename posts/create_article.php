<?php

    include '../allowedOrigins.php';

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }

    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    require '../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    $secretKey = "i_am_mikhael_a_web_dev";

    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit;
    }

    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($secretKey, 'HS256'));
        $userId = $decoded->user_id;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }

    parse_str(file_get_contents("php://input"), $_POST);

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    if (!$title || !$description || !$body) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        exit;
    }

    include '../db_connect.php';

    function generateSlug($title) {
        $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    $baseSlug = generateSlug($title);
    $slug = $baseSlug;

    // Ensure uniqueness (only within published articles for now)
    $counter = 1;
    while (true) {
        $checkStmt = $conn->prepare("SELECT article_id FROM articles WHERE author_id = ? AND slug = ?");
        $checkStmt->bind_param("is", $userId, $slug);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            break;
        }

        $slug = $baseSlug . '-' . $counter++;
        $checkStmt->close();
    }

    // Insert into pending_articles
    $stmt = $conn->prepare("INSERT INTO pending_articles (author_id, title, about, slug, content) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $title, $description, $slug, $body);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to submit article for review"]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $pendingArticleId = $stmt->insert_id;
    $stmt->close();

    if ($tags !== '') {
        // Split by spaces and filter out empty strings
        $tagList = array_filter(array_map('trim', preg_split('/\s+/', $tags)));
        
        foreach ($tagList as $tagName) {
            if ($tagName === '') continue;

            $tagStmt = $conn->prepare("SELECT tag_id FROM tags WHERE name = ?");
            $tagStmt->bind_param("s", $tagName);
            $tagStmt->execute();
            $tagResult = $tagStmt->get_result();

            if ($tagResult->num_rows > 0) {
                $tagId = $tagResult->fetch_assoc()['tag_id'];
            } else {
                $insertTagStmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                $insertTagStmt->bind_param("s", $tagName);
                $insertTagStmt->execute();
                $tagId = $insertTagStmt->insert_id;
                $insertTagStmt->close();
            }
            $tagStmt->close();

            $linkStmt = $conn->prepare("INSERT INTO pending_article_tags (pending_article_id, tag_id) VALUES (?, ?)");
            $linkStmt->bind_param("ii", $pendingArticleId, $tagId);
            $linkStmt->execute();
            $linkStmt->close();
        }
    }

    $conn->close();
    http_response_code(201);
    echo json_encode(["message" => "Article submitted for review."]);

?>