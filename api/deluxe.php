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

// List Deluxe Payroll Records
if ($action === 'listdeluxe') {
    $stmt = $conn->prepare("SELECT * FROM `deluxe` WHERE `delete_at` = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $deluxePayroll = $result->fetch_all(MYSQLI_ASSOC);
        // Decode products JSON for each record
        foreach ($deluxePayroll as &$record) {
            $record['products'] = json_decode($record['products'], true);
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["deluxePayroll" => $deluxePayroll]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Deluxe Payroll Details Not Found"],
            "body" => ["deluxePayroll" => []]
        ];
    }
}

// Add Deluxe Payroll Record
elseif ($action === 'adddeluxePayroll' && isset($obj['entry_date']) && isset($obj['staff_id']) && isset($obj['products']) && isset($obj['total'])) {
    $entry_date = $obj['entry_date'];
    $staff_id = $obj['staff_id'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE); // Expecting products as an array
    $total = $obj['total'];

    if (!empty($entry_date) && !empty($staff_id) && !empty($products)) {
        // Fetch staff name
        $stmtFetchName = $conn->prepare("SELECT `Name` FROM `staff` WHERE id = ?");
        $stmtFetchName->bind_param("s", $staff_id);
        $stmtFetchName->execute();
        $resultName = $stmtFetchName->get_result();

        if ($resultName->num_rows > 0) {
            $row = $resultName->fetch_assoc();
            $staff_name = $row['Name'];
        } else {
            echo json_encode(["head" => ["code" => 400, "msg" => "Invalid staff ID"]]);
            exit;
        }
        $stmtFetchName->close();

        // Insert into deluxe table
        $stmtInsert = $conn->prepare("INSERT INTO `deluxe` (`entry_date`, `staff_id`, `staff_name`, `products`, `total`, `delete_at`) 
                                      VALUES (?, ?, ?, ?, ?, 0)");
        $stmtInsert->bind_param("sssss", $entry_date, $staff_id, $staff_name, $products, $total);

        if ($stmtInsert->execute()) {
            $newId = $conn->insert_id; // Get the ID of the newly inserted record
            $newRecord = [
                "id" => $newId,
                "entry_date" => $entry_date,
                "staff_id" => $staff_id,
                "staff_name" => $staff_name,
                "products" => $obj['products'], // Use original array instead of encoded string
                "total" => $total,
                "delete_at" => 0
            ];

            $output = [
                "head" => [
                    "code" => 200,
                    "msg" => "Deluxe Payroll Record Created Successfully",
                    "deluxePayroll" => [$newRecord] // Return only the new record
                ]
            ];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Create Deluxe Payroll Record. Error: " . $stmtInsert->error]];
        }
        $stmtInsert->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

// Update Deluxe Payroll Record
elseif ($action === 'updatedeluxePayroll' && isset($obj['id']) && isset($obj['staff_id']) && isset($obj['products']) && isset($obj['total'])) {
    $id = $obj['id'];
    $staff_id = $obj['staff_id'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE); // Expecting products as an array
    $total = $obj['total'];

    if (!empty($id) && !empty($staff_id) && !empty($products)) {
        // Fetch staff name
        $stmtFetchName = $conn->prepare("SELECT `Name` FROM `staff` WHERE id = ?");
        $stmtFetchName->bind_param("s", $staff_id);
        $stmtFetchName->execute();
        $resultName = $stmtFetchName->get_result();

        if ($resultName->num_rows > 0) {
            $row = $resultName->fetch_assoc();
            $staff_name = $row['Name'];
        } else {
            echo json_encode(["head" => ["code" => 400, "msg" => "Invalid staff ID"]]);
            exit;
        }
        $stmtFetchName->close();

        // Update deluxe table
        $stmtUpdate = $conn->prepare("UPDATE `deluxe` SET 
                                      `staff_id` = ?, 
                                      `staff_name` = ?, 
                                      `products` = ?, 
                                      `total` = ? 
                                      WHERE `id` = ?");
        $stmtUpdate->bind_param("ssssi", $staff_id, $staff_name, $products, $total, $id);

        if ($stmtUpdate->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "Deluxe Payroll Record Updated Successfully", "id" => $id]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Update Deluxe Payroll Record. Error: " . $stmtUpdate->error]];
        }
        $stmtUpdate->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

// Delete Deluxe Payroll Record
elseif ($action === "deletedeluxePayroll" && isset($obj['id'])) {
    $id = $obj['id'];

    if (!empty($id)) {
        $stmtDelete = $conn->prepare("UPDATE `deluxe` SET `delete_at` = 1 WHERE `id` = ?");
        $stmtDelete->bind_param("i", $id);

        if ($stmtDelete->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "Deluxe Payroll Record Deleted Successfully"]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Delete Deluxe Payroll Record"]];
        }
        $stmtDelete->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
} else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid Parameters"]];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
