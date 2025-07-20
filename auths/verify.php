<?php

    include '../db_connect.php';

    $token = $_GET['token'] ?? '';

    if (!$token) {
        exit("<script>alert('Invalid verification link.'); window.close();</script>");
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        exit("<script>alert('Invalid or expired token.'); window.close();</script>");
    }

    $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();

    echo "<script>
            alert('Email verified! You may now close this tab and log in.');
            window.close();
          </script>";

?>
