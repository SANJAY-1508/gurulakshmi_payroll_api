<?php

include 'config/dbconfig.php'; // Database connection
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow only your React app's origin
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE"); // Allowed HTTP methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true"); // Allow credentials like cookies

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Respond OK for OPTIONS requests
    exit();
}

// Rest of your code starts here
$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Check if action is provided
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj->action;

// <<<<<<<<<<===================== Add Company Payroll =====================>>>>>>>>>>
if ($action === 'addCompanyPayroll' && isset($obj->date) && isset($obj->data)) {
    // Get values from the object
    $entry_date = $obj->date;
    $company_data = $obj->data; // Array of staff details
    $created_at = date('Y-m-d H:i:s'); // Get current timestamp

    // Validate the required fields
    if (!empty($entry_date) && !empty($company_data) && is_array($company_data)) {
        // Convert company_data to JSON string
        $company_data_json = json_encode($company_data);

        // Prepare the INSERT query
        $stmt = $conn->prepare("INSERT INTO `company_payroll` (`entry_date`, `company_data`, `created_at`) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $entry_date, $company_data_json, $created_at);

        if ($stmt->execute()) {
            // Successfully inserted
            $payroll_id = $stmt->insert_id;
            $output = [
                "head" => ["code" => 200, "msg" => "Company Payroll Added Successfully"],
                "body" => [
                    "company_payroll" => [
                        "id" => $payroll_id,
                        "date" => $entry_date,
                        "data" => $company_data
                    ]
                ]
            ];
        } else {
            // Error during insertion
            $output = ["head" => ["code" => 400, "msg" => "Failed to Add Company Payroll. Error: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        // Missing required fields
        $output = ["head" => ["code" => 400, "msg" => "Please provide valid data"]];
    }

    // Send the response
    echo json_encode($output);
    exit();
}

// <<<<<<<<<<===================== List Company Payroll =====================>>>>>>>>>>
elseif ($action === 'listCompanyPayroll') {
    $stmt = $conn->prepare("SELECT * FROM `company_payroll` WHERE delete_at = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $company_payroll = [];
        while ($row = $result->fetch_assoc()) {
            $company_data = json_decode($row['company_data'], true); // Decode JSON to array

            // Calculate aggregates
            $presents = 0;
            $absents = 0;
            $total_wages = 0;

            // Check if company_data is an array before using foreach
            if (is_array($company_data)) {
                foreach ($company_data as $staff) {
                    if (isset($staff['status']) && $staff['status'] === 'present') {
                        $presents++;
                    } elseif (isset($staff['status']) && $staff['status'] === 'absent') {
                        $absents++;
                    }
                    $total_wages += isset($staff['wages']) ? floatval($staff['wages']) : 0;
                }
            }

            $company_payroll[] = [
                "id" => $row['id'],
                "date" => $row['entry_date'], // Map entry_date to date for frontend
                "presents" => $presents,
                "absents" => $absents,
                "total_wages" => $total_wages,
                "data" => $company_data // Include the full data for edit functionality
            ];
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["company_payroll" => $company_payroll]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No Records Found"],
            "body" => ["company_payroll" => []]
        ];
    }

    echo json_encode($output);
    exit();
}

// <<<<<<<<<<===================== Update Company Payroll =====================>>>>>>>>>>
elseif ($action === 'updateCompanyPayroll' && isset($obj->id) && isset($obj->date) && isset($obj->data)) {
    // Extract values
    $id = $obj->id;
    $entry_date = $obj->date;
    $company_data = $obj->data; // Array of staff details
    $updated_at = date('Y-m-d H:i:s'); // Get current timestamp

    // Validate required fields
    if (!empty($id) && !empty($entry_date) && !empty($company_data) && is_array($company_data)) {
        // Convert company_data to JSON string
        $company_data_json = json_encode($company_data);

        // Prepare the UPDATE query
        $stmt = $conn->prepare("UPDATE `company_payroll` SET `entry_date` = ?, `company_data` = ? WHERE `id` = ?");
        $stmt->bind_param("ssi", $entry_date, $company_data_json, $id);

        if ($stmt->execute()) {
            // Successfully updated
            $output = [
                "head" => ["code" => 200, "msg" => "Company Payroll Updated Successfully"],
                "body" => [
                    "company_payroll" => [
                        "id" => $id,
                        "date" => $entry_date,
                        "data" => $company_data
                    ]
                ]
            ];
        } else {
            // Error during update
            $output = ["head" => ["code" => 400, "msg" => "Failed to Update Company Payroll. Error: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        // Missing required fields
        $output = ["head" => ["code" => 400, "msg" => "Please provide all required fields"]];
    }

    // Send the response
    echo json_encode($output);
    exit();
}

// <<<<<<<<<<===================== Delete Company Payroll =====================>>>>>>>>>>
elseif ($action === "deleteCompanyPayroll" && isset($obj->id)) {
    $id = $obj->id;

    // Validate if ID is provided
    if (!empty($id)) {
        // Prepare the DELETE query (soft delete)
        $stmt = $conn->prepare("UPDATE `company_payroll` SET delete_at = 1 WHERE `id` = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Successfully deleted
            $output = ["head" => ["code" => 200, "msg" => "Company Payroll Deleted Successfully"]];
        } else {
            // Error during delete
            $output = ["head" => ["code" => 400, "msg" => "Failed to Delete Company Payroll. Error: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        // Missing ID
        $output = ["head" => ["code" => 400, "msg" => "Please provide a valid ID"]];
    }

    // Send the response
    echo json_encode($output);
    exit();
}

// Default response for unknown actions
else {
    $output = [
        "head" => ["code" => 400, "msg" => "Invalid Parameters"]
    ];
    echo json_encode($output);
    exit();
}
