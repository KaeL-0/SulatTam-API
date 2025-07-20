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

    require '../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    $secretKey = "i_am_mikhael_a_web_dev";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }
        
    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing token"]);
        exit;
    }

    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($secretKey, 'HS256'));
        $userId = $decoded->user_id;

        $input = json_decode(file_get_contents("php://input"), true);
        $articleId = $input['article_id'] ?? null;

        if (!$articleId || !is_numeric($articleId)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid or missing article_id"]);
            exit;
        }

        include '../db_connect.php';

        // Check if the user already favorited this article
        $stmt = $conn->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND article_id = ?");
        $stmt->bind_param("ii", $userId, $articleId);
        $stmt->execute();
        $alreadyFavorited = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($alreadyFavorited) {
            // Unfavorite
            $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND article_id = ?");
            $stmt->bind_param("ii", $userId, $articleId);
            $stmt->execute();
            $stmt->close();

            // Get updated favorites count
            $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM favorites WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            echo json_encode([
                "message" => "Article unfavorited",
                "favorited" => false,
                "favoritesCount" => (int)$row['count']
            ]);
        } else {
            // Favorite
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, article_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $articleId);
            $stmt->execute();
            $stmt->close();

            // Get updated favorites count
            $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM favorites WHERE article_id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            echo json_encode([
                "message" => "Article favorited",
                "favorited" => true,
                "favoritesCount" => (int)$row['count']
            ]);
        }

        $conn->close();

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }
?>