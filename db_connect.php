<?php 
    $host = 'mysql-sulat-tam.alwaysdata.net'; 
    $user = 'sulat-tam_myuser';                       
    $pass = 'Password009!';                   
    $db   = 'sulat-tam_db';                       

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection failed"]);
        exit;
    }
?>