<?php
// idk how this authentication works
function authenticateUser($username, $password) {
    if ($username === 'exampleUser' && $password === 'password') {
        return true;
    }
    return false;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWT($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

$secret_key = "uwu";

function base64UrlDecode($data) {
    $padding = 4 - (strlen($data) % 4);
    $data .= str_repeat('=', $padding);
    return base64_decode(strtr($data, '-_', '+/'));
}

function verifyJWT($jwt, $secret) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return false;
    }

    list($header, $payload, $signature) = $parts;
    $validSignature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
    $base64UrlValidSignature = base64UrlEncode($validSignature);
    if (hash_equals($base64UrlValidSignature, $signature)) {
        $decodedPayload = json_decode(base64UrlDecode($payload), true);
        return $decodedPayload;
    } else {
        return false;
    }
}

function authenticate() {
    $headers = apache_request_headers();
    if (!isset($headers['authorization'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit();
    }
    $authHeader = $headers['authorization'];
    list($jwt) = sscanf($authHeader, 'Bearer %s');
    if (!$jwt) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit();
    }
    $decodedPayload = verifyJWT($jwt, $GLOBALS['secret_key']);
    if (!$decodedPayload) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit();
    }
    return $decodedPayload;
}

$csv_file = "";

if (isset($_GET["type"])) {
    $type = $_GET["type"];
    if ($type == "main") {
        $csv_file = "data.csv";
    } else if ($type == "willwatch") {
        $csv_file = "willwatch.csv";
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid type parameter"]);
        return;
    } 
}

function add_row($row) {
    global $csv_file;
    $fp = fopen($csv_file, "a");
    fputcsv($fp, $row);
    fclose($fp);
}

function delete_row_by_id($id, $csv_file) {
    $rows = [];
    $fp = fopen($csv_file, "r");
    while ($row = fgetcsv($fp)) {
        if ($row[0] != $id) {
            $rows[] = $row;
        }
    }
    fclose($fp);
    $fp = fopen($csv_file, "w");
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

function get_last_id($csv_file) {
    $fp = fopen($csv_file, "r");
    fseek($fp, -2, SEEK_END);
    $last_line = '';
    while ($char = fgetc($fp)) { 
        if ($char === "\n") {
            break;
        }
        $last_line = $char . $last_line;
    }
    fclose($fp);
    $last_row = str_getcsv($last_line);
    $last_id = $last_row[0];
    return $last_id;
}


$method = $_SERVER["REQUEST_METHOD"];
switch ($method) {
    case "GET":
        if (authenticate()) {
            if (isset($_GET["lastid"])) {
                $last_id = get_last_id($csv_file);
                echo $last_id;
                return;
            } else if (isset($_GET["type"])) {
                $fp = fopen($csv_file, "r");
                $json = array();
                while ($row = fgetcsv($fp)) {
                    $type = $_GET["type"];
                    if ($type == "main") {
                        $json[] = array(
                            "ID" => $row[0],
                            "Name" => $row[1],
                            "Progress" => $row[2],
                            "ProgressType" => $row[3],
                            "Notes" => $row[4]
                        );
                    } else if ($type == "willwatch") {
                        $json[] = array(
                            "ID" => $row[0],
                            "Name" => $row[1],
                            "Notes" => $row[2]
                        );
                    }
                }
                fclose($fp);
                
                header('Content-Type: application/json');
                echo json_encode($json, JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(405);
                echo json_encode(["status" => "error", "message" => ":3"]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Bad token"]);
        }
        break;
    case "POST":
        $headers = getallheaders();
        header("Access-Control-Allow-Origin: *"); 
        if($headers['type'] == 'addrow') {
            if (authenticate()) {
                $row = json_decode(file_get_contents('php://input'), true);
                add_row($row);
                http_response_code(200);
            } else {
                echo json_encode(["status" => "error", "message" => "Bad token"]);
                http_response_code(400);
            }
            break;
        } else if ($headers['type'] == 'login'){
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'];
            $password = $data['password'];
            if (authenticateUser($username, $password)) {
                $payload = [
                    "iss" => "your_issuer",
                    "aud" => "your_audience",
                    "iat" => time(),
                    "nbf" => time() + 10,
                    "exp" => time() + 3600,
                    "data" => [
                        "username" => $username
                    ]
                ];
                $jwt = generateJWT($payload, $secret_key);
                echo json_encode(["status" => "success", "token" => $jwt]);
                http_response_code(200);
            } else {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
            }
        } else if ($headers['type'] == 'tokencheck'){
            authenticate();
            echo json_encode(["status" => "success", "message" => "good"]);
            http_response_code(200);
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => ":3"]);
        }
        break;
    case "DELETE":
        if (authenticate()) {
            parse_str($_SERVER["QUERY_STRING"], $query);
            delete_row_by_id($query["number"], $csv_file);
            http_response_code(200);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Bad token"]);
        }
        break;
}
