<?php

include 'config/dbconfig.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json, true);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (!isset($obj['action'])) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj['action'];

// <<<<<<<<<<===================== List Staff =====================>>>>>>>>>>
if ($action === 'listStaff') {
    $search_text = isset($obj['search_text']) ? $obj['search_text'] : '';
    $stmt = $conn->prepare("SELECT * FROM `staff` WHERE `deleted_at` = 0 AND `Name` LIKE ?");
    $search_text = '%' . $search_text . '%';
    $stmt->bind_param("s", $search_text);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $staff = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($staff as &$staffMember) {
            $staffMember['staff_type'] = json_decode($staffMember['staff_type'], true) ?: $staffMember['staff_type'];
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["staff" => $staff]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Staff Details Not Found"],
            "body" => ["staff" => []]
        ];
    }
}

// <<<<<<<<<<===================== Add Staff =====================>>>>>>>>>>
elseif ($action === 'addStaff' && isset($obj['Name']) && isset($obj['Mobile_Number']) && isset($obj['Place']) && isset($obj['Staff_Type'])) {
    $Name = $obj['Name'];
    $Mobile_Number = $obj['Mobile_Number'];
    $Place = $obj['Place'];
    $Staff_Type = json_encode($obj['Staff_Type'], JSON_UNESCAPED_UNICODE); // Use JSON_UNESCAPED_UNICODE

    $stmtInsert = $conn->prepare("INSERT INTO `staff` (`Name`, `Mobile_Number`, `Place`, `staff_type`, `created_at_datetime`, `deleted_at`) 
                                  VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmtInsert->bind_param("ssss", $Name, $Mobile_Number, $Place, $Staff_Type);

    if ($stmtInsert->execute()) {
        $insertId = $stmtInsert->insert_id;
        $staff_id = uniqueID("staff", $insertId); // Assuming uniqueID exists

        $stmtUpdate = $conn->prepare("UPDATE `staff` SET `staff_id` = ? WHERE `id` = ?");
        $stmtUpdate->bind_param("si", $staff_id, $insertId);

        if ($stmtUpdate->execute()) {
            $stmt = $conn->prepare("SELECT * FROM `staff` WHERE `deleted_at` = 0 AND id = ? ORDER BY `id` DESC");
            $stmt->bind_param("i", $insertId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $staff = $result->fetch_all(MYSQLI_ASSOC);
                foreach ($staff as &$staffMember) {
                    $staffMember['staff_type'] = json_decode($staffMember['staff_type'], true);
                }
            }
            $output = ["head" => ["code" => 200, "msg" => "Staff Created Successfully", "staff" => $staff]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Update Staff ID"]];
        }
        $stmtUpdate->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to Create Staff. Error: " . $stmtInsert->error]];
    }
    $stmtInsert->close();
}

// <<<<<<<<<<===================== Update Staff =====================>>>>>>>>>>
elseif ($action === 'updateStaff' && isset($obj['staff_id']) && isset($obj['Name']) && isset($obj['Mobile_Number']) && isset($obj['Place']) && isset($obj['Staff_Type'])) {
    $staff_id = $obj['staff_id'];
    $Name = $obj['Name'];
    $Mobile_Number = $obj['Mobile_Number'];
    $Place = $obj['Place'];
    $Staff_Type = json_encode($obj['Staff_Type'], JSON_UNESCAPED_UNICODE); // Use JSON_UNESCAPED_UNICODE

    $stmtUpdate = $conn->prepare("UPDATE `staff` SET 
                                  `Name` = ?, 
                                  `Mobile_Number` = ?, 
                                  `Place` = ?, 
                                  `staff_type` = ? 
                                  WHERE `id` = ?");
    $stmtUpdate->bind_param("ssssi", $Name, $Mobile_Number, $Place, $Staff_Type, $staff_id);

    if ($stmtUpdate->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "Staff Details Updated Successfully", "id" => $staff_id]];
    } else {
        error_log("SQL Error: " . $conn->error);
        $output = ["head" => ["code" => 400, "msg" => "Failed to Update Staff. Error: " . $conn->error]];
    }
    $stmtUpdate->close();
}

// <<<<<<<<<<===================== Update Advance =====================>>>>>>>>>>
elseif ($action === 'updateAdvance' && isset($obj['staff_id']) && isset($obj['advance_amount'])) {
    $staff_id = intval($obj['staff_id']);
    $advance_amount = floatval($obj['advance_amount']);

    if ($advance_amount <= 0) {
        $output = ["head" => ["code" => 400, "msg" => "Advance amount must be greater than zero"]];
    } else {
        $type = 'add';
        $recovery_mode = 'direct';
        $advance_id = 'ADV' . date('Ymd') . str_pad($staff_id, 4, '0', STR_PAD_LEFT) . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $entry_date = date('Y-m-d');

        // Start transaction for data consistency
        $conn->begin_transaction();

        try {
            // 1. Insert into staff_advance history table
            $stmtInsert = $conn->prepare("
                INSERT INTO `staff_advance` 
                (`advance_id`, `staff_id`, `staff_name`, `amount`, `type`, `recovery_mode`, `weekly_salary_id`, `entry_date`, `created_at`)
                VALUES (?, ?, (SELECT `Name` FROM `staff` WHERE `id` = ?), ?, ?, ?, NULL, ?, NOW())
            ");
            $stmtInsert->bind_param("sisdsss", $advance_id, $staff_id, $staff_id, $advance_amount, $type, $recovery_mode, $entry_date);
            $stmtInsert->execute();
            $stmtInsert->close();

            // 2. Update running total in staff table
            $stmtUpdate = $conn->prepare("
                UPDATE `staff` 
                SET `staff_advance` = COALESCE(`staff_advance`, 0) + ? 
                WHERE `id` = ? AND `deleted_at` = 0
            ");
            $stmtUpdate->bind_param("di", $advance_amount, $staff_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $conn->commit();
            $output = ["head" => ["code" => 200, "msg" => "Advance Added Successfully"]];

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Advance Transaction Error: " . $e->getMessage());
            $output = ["head" => ["code" => 400, "msg" => "Failed to add advance. Please try again."]];
        }
    }
}
// <<<<<<<<<<===================== Delete Staff =====================>>>>>>>>>>
elseif ($action === "deleteStaff") {
    $delete_staff_id = $obj['delete_staff_id'] ?? null;

    if (!empty($delete_staff_id)) {
        $stmt = $conn->prepare("UPDATE `staff` SET `deleted_at` = 1 WHERE `id` = ?");
        $stmt->bind_param("i", $delete_staff_id);

        if ($stmt->execute()) {
            $output = ["head" => ["code" => 200, "msg" => "Staff Deleted Successfully"]];
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Delete Staff"]];
        }
        $stmt->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }
} else {
    $output = [
        "head" => ["code" => 400, "msg" => "Invalid Parameters"],
        "inputs" => $obj
    ];
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); // Ensure output is also unescaped