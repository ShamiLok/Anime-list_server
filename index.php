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
        echo "Invalid type parameter";
        return;
    }
} else {
    http_response_code(400);
    echo "Missing type parameter";
    return;
}

function add_row($row) {
    global $csv_file;
    $fp = fopen($csv_file, "a");
    fputcsv($fp, $row);
    fclose($fp);
}

function delete_row_by_number($number, $csv_file) {
    error_log('asd');
    $rows = [];
    $fp = fopen($csv_file, "r");
    $i = 0;
    while ($row = fgetcsv($fp)) {
        if ($i != $number) {
            $rows[] = $row;
        }
        $i++;
    }
    fclose($fp);
    $fp = fopen($csv_file, "w");
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

$method = $_SERVER["REQUEST_METHOD"];
switch ($method) {
    case "GET":
        $fp = fopen($csv_file, "r");
        while ($row = fgetcsv($fp)) {
            echo implode(",", $row) . "\r\n";
        }
        fclose($fp);
        break;
    case "POST":
        $row = $_POST;
        add_row($row);
        break;
    case "DELETE":
        parse_str($_SERVER["QUERY_STRING"], $query);
        $number = $query["number"] + 1;
        delete_row_by_number($number, $csv_file);
        http_response_code(200);
        break;
}
