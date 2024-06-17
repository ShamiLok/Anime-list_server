<?php
$csv_file = "";

if (isset($_GET["type"])) {
    $type = $_GET["type"];
    if ($type == "main") {
        $csv_file = "data.csv";
    } else if ($type == "willwatch") {
        $csv_file = "willwatch.csv";
    } else {
        http_response_code(400);
        // echo "Invalid type parameter";
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
        if (isset($_GET["lastid"])) {
            $last_id = get_last_id($csv_file);
            echo $last_id;
            return;
        } else {
            $fp = fopen($csv_file, "r");
            $rows = [];
            while ($row = fgetcsv($fp)) {
                $rows[] = $row;
            }
            fclose($fp);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }
        break;
    case "POST":
        $row = $_POST;
        add_row($row);
        break;
    case "DELETE":
        parse_str($_SERVER["QUERY_STRING"], $query);
        delete_row_by_id($query["number"], $csv_file);
        http_response_code(200);
        break;
}
