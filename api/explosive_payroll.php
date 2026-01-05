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

// <<<<<<<<<<===================== List explosive Payroll Records =====================>>>>>>>>>>
if (!isset($obj['action'])) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj['action'];

if ($action === 'list_explosive_payroll') {
    $stmt = $conn->prepare("SELECT * FROM `explosive_device_payroll` WHERE `delete_at` = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $explosive_payroll = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["explosive_payroll" => $explosive_payroll]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "explosive_payroll Details Not Found"],
            "body" => ["explosive_payroll" => []]
        ];
    }
}

// <<<<<<<<<<===================== Add explosive Payroll Record =====================>>>>>>>>>>
elseif ($action === 'addexplosivepayroll' && isset($obj['entry_date'], $obj['staff_id'], $obj['products'], $obj['total'])) {
    $entry_date = $obj['entry_date'];
    $staff_id = $obj['staff_id'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE); // Ensure proper UTF-8 encoding
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

        // Insert into explosive_payroll table
        $stmtInsert = $conn->prepare("INSERT INTO `explosive_device_payroll` (`entry_date`, `staff_id`, `staff_name`, `products`, `total`, `delete_at`) 
                                      VALUES (?, ?, ?, ?, ?, 0)");
        $stmtInsert->bind_param("sssss", $entry_date, $staff_id, $staff_name, $products, $total);

        if ($stmtInsert->execute()) {
            $stmt = $conn->prepare("SELECT * FROM `explosive_device_payroll` WHERE `delete_at` = 0 ORDER BY `id` DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $explosivePayroll = ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

            $output = ["head" => ["code" => 200, "msg" => "explosive Payroll Record Created Successfully", "explosivePayroll" => $explosivePayroll]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Create explosive Payroll Record. Error: " . $stmtInsert->error]];
        }
        $stmtInsert->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

// <<<<<<<<<<===================== Update explosive Payroll Record =====================>>>>>>>>>>
elseif ($action === 'update_explosive_payroll' && isset($obj['id'], $obj['staff_id'], $obj['products'], $obj['total'])) {
    $id = $obj['id'];
    $staff_id = $obj['staff_id'];
    $products = json_encode($obj['products'], JSON_UNESCAPED_UNICODE);
    $total = $obj['total'];

    if (!empty($id) && !empty($staff_id) && !empty($products)) {
        // Fetch staff name
        $stmtFetchName = $conn->prepare("SELECT Name FROM staff WHERE id = ?");
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

        // Update explosive_payroll table
        $stmtUpdate = $conn->prepare("UPDATE explosive_device_payroll SET 
                                      staff_id = ?, 
                                      staff_name = ?, 
                                      products = ?, 
                                      total = ? 
                                      WHERE id = ?");
        $stmtUpdate->bind_param("ssssi", $staff_id, $staff_name, $products, $total, $id);

        if ($stmtUpdate->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "explosive Payroll Record Updated Successfully", "id" => $id]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Update explosive Payroll Record. Error: " . $stmtUpdate->error]];
        }
        $stmtUpdate->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

// <<<<<<<<<<===================== Delete explosive Payroll Record =====================>>>>>>>>>>
elseif ($action === "delete_explosive_ayroll" && isset($obj['id'])) {
    $id = $obj['id'];

    if (!empty($id)) {
        $stmtDelete = $conn->prepare("UPDATE explosive_device_payroll SET delete_at = 1 WHERE id = ?");
        $stmtDelete->bind_param("i", $id);

        $output = $stmtDelete->execute()
            ? ["head" => ["code" => 200, "msg" => "explosive Payroll Record Deleted Successfully"]]
            : ["head" => ["code" => 400, "msg" => "Failed to Delete explosive Payroll Record"]];

        $stmtDelete->close();
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
} else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid Parameters"], "inputs" => $obj];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
