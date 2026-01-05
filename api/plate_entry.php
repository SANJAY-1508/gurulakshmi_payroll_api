<?php

include 'config/dbconfig.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow only your React app
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE"); // Allow HTTP methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow headers
header("Access-Control-Allow-Credentials: true"); // If needed for cookies/auth

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// <<<<<<<<<<===================== List Users =====================>>>>>>>>>>
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj->action;

if ($action === 'listplateentry') {
    $stmt = $conn->prepare("SELECT * FROM `plate_entry` WHERE `delete_at` = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $plateentry = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["plateentry" => $plateentry]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "PlateEntry Details Not Found"],
            "body" => ["plateentry" => []]
        ];
    }
}


// Check if the action is 'adduplate'
elseif ($action === 'addPlateEntery' && isset($obj->entry_date) && isset($obj->entry_count)) {
    // Assign values from the object
    $entry_date = $obj->entry_date;
    $entry_count = $obj->entry_count;

    // Validate Required Fields
    if (!empty($entry_date) && !empty($entry_count)) {
       
        // Prepare statement to insert the new plate entry
        $stmtInsert = $conn->prepare("INSERT INTO `plate_entry` (`entry_date`, `entry_count`, `delete_at`) 
                                      VALUES (?, ?, 0)");
        $stmtInsert->bind_param("ss", $entry_date, $entry_count);

        if ($stmtInsert->execute()) {
            $insertId = $stmtInsert->insert_id;

            // Retrieve the newly inserted plate entry
            $stmt = $conn->prepare("SELECT * FROM `plate_entry` WHERE `delete_at` = 0 AND id = ? ORDER BY `id` DESC");
            $stmt->bind_param("i", $insertId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $plateentry = $result->fetch_all(MYSQLI_ASSOC);
                 $update_id = 1;
                
                // Retrieve current empty_plate_count from magazineStock
                $stmtSelectStock = $conn->prepare("SELECT `empty_plate_count` FROM `magazineStock` WHERE `id` = ?");
                $stmtSelectStock->bind_param("i", $update_id);
                $stmtSelectStock->execute();
                $stockResult = $stmtSelectStock->get_result();
                
                if ($stockResult->num_rows > 0) {
                    $update_id = 1;
                    $stock = $stockResult->fetch_assoc();
                    $currentEmptyPlateCount = $stock['empty_plate_count'];

                    // Add the new entry_count to the current empty_plate_count
                    $newEmptyPlateCount = $currentEmptyPlateCount + $entry_count;

                    // Update magazineStock with the new empty_plate_count
                    $stmtUpdate = $conn->prepare("UPDATE `magazineStock` SET `empty_plate_count` = ? WHERE `id` = ?");
                    $stmtUpdate->bind_param("ii", $newEmptyPlateCount, $update_id);
                    $stmtUpdate->execute();
                    
                    $output = ["head" => ["code" => 200, "msg" => "plateEntry Created and Updated Successfully", "plateentry" => $plateentry]];
                } else {
                    $output = ["head" => ["code" => 400, "msg" => "Failed to find magazineStock record for update"]];
                }
                $stmtSelectStock->close();
            } else {
                $output = ["head" => ["code" => 400, "msg" => "Failed to retrieve plateEntry after insertion"]];
            }
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to Create plateEntry. Error: " . $stmtInsert->error]];
        }
        $stmtInsert->close();
  
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }

    // Send JSON response
    echo json_encode($output);
    exit;
}





// Check if the action is 'editplateentry'
elseif ($action === 'updatePlateEntry' && isset($obj->entry_date) && isset($obj->entry_count)) {
    // Extract data from the object
    $id = $obj->id;
    $entry_date = $obj->entry_date;
    $entry_count = $obj->entry_count;
     $update_id = 1;

    // Validate Required Fields
    if (!empty($id) && !empty($entry_date) && !empty($entry_count)) {
        // Step 1: Retrieve the original entry_count from plate_entry
        $stmtSelectOriginal = $conn->prepare("SELECT `entry_count` FROM `plate_entry` WHERE `id` = ?");
        $stmtSelectOriginal->bind_param("i", $id);
        $stmtSelectOriginal->execute();
        $resultOriginal = $stmtSelectOriginal->get_result();

        if ($resultOriginal->num_rows > 0) {
            $originalEntry = $resultOriginal->fetch_assoc();
            $originalCount = $originalEntry['entry_count'];
            
            
            
           

            // Step 2: Deduct the original entry_count from magazineStock
            $stmtSelectStock = $conn->prepare("SELECT `empty_plate_count` FROM `magazineStock` WHERE `id` = ?");
            $stmtSelectStock->bind_param("i", $update_id);
            $stmtSelectStock->execute();
            $stockResult = $stmtSelectStock->get_result();

            if ($stockResult->num_rows > 0) {
                $stock = $stockResult->fetch_assoc();
                $currentEmptyPlateCount = $stock['empty_plate_count'];
                $newEmptyPlateCount = $currentEmptyPlateCount - $originalCount;
                $newstock =$newEmptyPlateCount + $entry_count;

                // Step 3: Update the magazineStock with the new empty_plate_count
                $stmtUpdateStock = $conn->prepare("UPDATE `magazineStock` SET `empty_plate_count` = ? WHERE `id` = ?");
                $stmtUpdateStock->bind_param("ii", $newstock, $update_id);
                $stmtUpdateStock->execute();

                // Step 4: Update the plate_entry record
                $updatePlateEntry = "UPDATE `plate_entry` SET 
                                     `entry_date` = ?, 
                                     `entry_count` = ?
                                     WHERE `id` = ?";
                $stmtUpdatePlateEntry = $conn->prepare($updatePlateEntry);
                $stmtUpdatePlateEntry->bind_param("ssi", $entry_date, $entry_count, $id);

                if ($stmtUpdatePlateEntry->execute()) {
                    $output = ["head" => ["code" => 200, "msg" => "Plate Entry Details Updated Successfully", "id" => $id]];
                } else {
                    error_log("SQL Error: " . $stmtUpdatePlateEntry->error);
                    $output = ["head" => ["code" => 400, "msg" => "Failed to Update Plate Entry. Error: " . $stmtUpdatePlateEntry->error]];
                }

                $stmtUpdatePlateEntry->close();
            } else {
                $output = ["head" => ["code" => 400, "msg" => "Failed to find magazineStock record"]];
            }

            $stmtSelectStock->close();
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to find original Plate Entry"]];
        }

        $stmtSelectOriginal->close();
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required details"]];
    }

    // Return the JSON response
    echo json_encode($output);
    exit;
}





// <<<<<<<<<<===================== Delete User =====================>>>>>>>>>>
elseif ($action === "deletePlateEntry") {
    $id = $obj->id ?? null;
    $update_id = 1;

    if (!empty($id)) {
        // Step 1: Get the `entry_count` for the plate entry being deleted
        $stmtSelectEntry = $conn->prepare("SELECT `entry_count` FROM `plate_entry` WHERE `id` = ?");
        $stmtSelectEntry->bind_param("i", $id);
        $stmtSelectEntry->execute();
        $resultEntry = $stmtSelectEntry->get_result();

        if ($resultEntry->num_rows > 0) {
            $entry = $resultEntry->fetch_assoc();
            $entryCount = $entry['entry_count'];

            // Step 2: Retrieve and update `empty_plate_count` in `magazineStock`
            $stmtSelectStock = $conn->prepare("SELECT `empty_plate_count` FROM `magazineStock` WHERE `id` = ?");
            $stmtSelectStock->bind_param("i", $update_id);
            $stmtSelectStock->execute();
            $resultStock = $stmtSelectStock->get_result();

            if ($resultStock->num_rows > 0) {
                $stock = $resultStock->fetch_assoc();
                $currentEmptyPlateCount = $stock['empty_plate_count'];
                $newEmptyPlateCount = $currentEmptyPlateCount - $entryCount;

                // Update the `empty_plate_count` in `magazineStock`
                $stmtUpdateStock = $conn->prepare("UPDATE `magazineStock` SET `empty_plate_count` = ? WHERE `id` = ?");
                $stmtUpdateStock->bind_param("ii", $newEmptyPlateCount, $update_id);
                $stmtUpdateStock->execute();

                // Step 3: Mark the plate entry as deleted
                $stmtDeleteEntry = $conn->prepare("UPDATE `plate_entry` SET `delete_at` = 1 WHERE `id` = ?");
                $stmtDeleteEntry->bind_param("i", $id);

                if ($stmtDeleteEntry->execute()) {
                    $output = ["head" => [
                        "code" => 200,
                        "msg" => "Plate Entry Deleted Successfully and Stock Updated"
                    ]];
                } else {
                    $output = ["head" => ["code" => 400, "msg" => "Failed to Delete Plate Entry"]];
                }

                $stmtDeleteEntry->close();
            } else {
                $output = ["head" => ["code" => 400, "msg" => "Failed to find magazineStock record"]];
            }

            $stmtSelectStock->close();
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Failed to find Plate Entry record"]];
        }

        $stmtSelectEntry->close();
    } else {
        $output = ["head" => [
            "code" => 400,
            "msg" => "Please provide all required details"
        ]];
    }

    // Return the JSON response
    echo json_encode($output);
    exit;
}else {
    $output = [
        "head" => ["code" => 400, "msg" => "Invalid Parameters"],
        "inputs" => $obj
    ];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
