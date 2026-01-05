<?php

include 'config/dbconfig.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Ensure UTF-8 encoding for the database connection
$conn->set_charset("utf8mb4");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json, true); // Decode JSON with assoc=true to get an array
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Check if action is set
if (!isset($obj['action'])) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj['action'];

// List all Pay entries
if ($action === 'listpay') {
    $stmt = $conn->prepare("SELECT * FROM `pay` WHERE delete_at = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $pay = $result->fetch_all(MYSQLI_ASSOC);
        // Decode products JSON for each record
        foreach ($pay as &$record) {
            $record['products'] = json_decode($record['products'], true);
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["pay" => $pay]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No Data Found"],
            "body" => ["pay" => []]
        ];
    }
}

// Add a new Pay entry with products as JSON
elseif ($action === 'addPay' && isset($obj['staff_name'], $obj['entry_date'], $obj['ring_count'], $obj['products'], $obj['total'])) {
    $staff_name = $obj['staff_name'];
    $entry_date = $obj['entry_date'];
    $ring_count = $obj['ring_count'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE); // Expecting products as an array
    $total = $obj['total'];

    if (!empty($staff_name) && !empty($entry_date) && !empty($products) && !empty($total)) {
        $stmt = $conn->prepare("INSERT INTO `pay` (`staff_name`, `entry_date`, `ring_count`, `products`, `total`, `delete_at`) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssss", $staff_name, $entry_date, $ring_count, $products, $total); // Fixed: 5 's' for 5 variables

        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $newRecord = [
                "id" => $newId,
                "staff_name" => $staff_name,
                "entry_date" => $entry_date,
                "ring_count" => $ring_count,
                "products" => $obj['products'], // Return original array
                "total" => $total,
                "delete_at" => 0
            ];
            $output = [
                "head" => [
                    "code" => 200,
                    "msg" => "Pay Created Successfully",
                    "pay" => [$newRecord]
                ]
            ];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Create Pay: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
}

// Update Pay entry with products as JSON
elseif ($action === 'updatePay' && isset($obj['id'], $obj['staff_name'], $obj['entry_date'], $obj['ring_count'], $obj['products'], $obj['total'])) {
    $id = $obj['id'];
    $staff_name = $obj['staff_name'];
    $entry_date = $obj['entry_date'];
    $ring_count = $obj['ring_count'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE); // Expecting products as an array
    $total = $obj['total'];

    if (!empty($id) && !empty($staff_name) && !empty($entry_date) && !empty($products) && !empty($total)) {
        $stmt = $conn->prepare("UPDATE `pay` SET `staff_name` = ?, `entry_date` = ?, `ring_count` = ?, `products` = ?, `total` = ? WHERE `id` = ?");
        $stmt->bind_param("sssssi", $staff_name, $entry_date, $ring_count, $products, $total, $id); // 5 strings + 1 integer

        if ($stmt->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "Pay Updated Successfully", "id" => $id]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Update Pay: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
}

// Delete Pay entry
elseif ($action === "deletePay" && isset($obj['id'])) {
    $id = $obj['id'];

    if (!empty($id)) {
        $stmt = $conn->prepare("UPDATE `pay` SET delete_at = 1 WHERE `id` = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "Pay Deleted Successfully"]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Delete Pay: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide an ID"]];
    }
} else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid Parameters"]];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
exit;
