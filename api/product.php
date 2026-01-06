<?php

include 'config/dbconfig.php'; // Include database connection
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

// Ensure action is set
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}
$action = $obj->action; // Extract action from the request

if ($action === 'listProduct') {
    $query = "SELECT * FROM products WHERE delete_at = 0";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $response = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["products" => $products]
        ];
    } else {
        $response = [
            "head" => ["code" => 200, "msg" => "No Products Found"],
            "body" => ["products" => []]
        ];
    }
}
// Create Product
elseif ($action === 'createProduct') {
    $product_name = $obj->product_name ?? null;
    $Knitting_wage = $obj->Knitting_wage ?? null;
    $deluxe_Knitting_wage = $obj->deluxe_Knitting_wage ?? null;
    $packing_cooly = $obj->packing_cooly ?? null;
    $unit_cooly = $obj->unit_cooly ?? null; // New Field Added

    if ($product_name && ($Knitting_wage || $deluxe_Knitting_wage || $packing_cooly || $unit_cooly)) {
        $stmt = $conn->prepare("INSERT INTO products (product_name, Knitting_wage,deluxe_Knitting_wage, packing_cooly, unit_cooly, create_at) VALUES (?, ?, ?,?, ?, ?)");
        $stmt->bind_param("ssssss", $product_name, $Knitting_wage, $deluxe_Knitting_wage, $packing_cooly, $unit_cooly, $timestamp);

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            $product_id = uniqueID("product", $insertId);

            $stmtUpdate = $conn->prepare("UPDATE products SET product_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $product_id, $insertId);
            $stmtUpdate->execute();

            $response = [
                "status" => 200,
                "message" => "Product Added Successfully",
                "product_id" => $product_id
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Product. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "status" => 400,
            "message" => "Either Knitting Wage, Packing Cooly, or Unit Cooly is required."
        ];
    }
}


// Update Product
elseif ($action === 'updateProductInfo') {
    $edit_Product_id = $obj->edit_Product_id ?? null;
    $product_name = $obj->product_name ?? null;

    $Knitting_wage = $obj->Knitting_wage ?? null;
    $deluxe_Knitting_wage = $obj->deluxe_Knitting_wage ?? null;
    $packing_cooly = $obj->packing_cooly ?? null;
    $unit_cooly = $obj->unit_cooly ?? null; // New Field Added

    if ($edit_Product_id && $product_name) {
        $stmt = $conn->prepare("UPDATE products SET product_name = ?, Knitting_wage = ?,deluxe_Knitting_wage = ?, packing_cooly = ?, unit_cooly = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $product_name, $Knitting_wage, $deluxe_Knitting_wage, $packing_cooly, $unit_cooly, $edit_Product_id);

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Product Updated Successfully",
                "id" => $edit_Product_id
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Product. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "status" => 400,
            "message" => "Missing or Invalid Parameters"
        ];
    }
}
// Delete Product (Soft Delete)
elseif ($action === 'deleteProduct') {
    $delete_Product_id = $obj->delete_Product_id ?? null;

    if ($delete_Product_id) {
        $stmt = $conn->prepare("UPDATE products SET delete_at = 1 WHERE id = ?");
        $stmt->bind_param("i", $delete_Product_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Product Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Product. Error: " . $stmt->error]
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "head" => ["code" => 400, "msg" => "Missing or Invalid Parameters"]
        ];
    }
} elseif ($action === 'listSetting') {
    $stmt = $conn->prepare("SELECT * FROM `setting`");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $setting = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["setting" => $setting]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Setting details not found"],
            "body" => ["setting" => []]
        ];
    }
    echo json_encode($output);
    exit();
} elseif ($action === 'updateSetting' && isset($obj->id) && isset($obj->sorcha_cooly)  && isset($obj->giant_cooly)  && isset($obj->thiri_sorcha_cooly) && isset($obj->thiri_giant_cooly)) {
    $id = $obj->id;

    $sorcha_cooly = $obj->sorcha_cooly;

    $giant_cooly = $obj->giant_cooly;

    $thiri_sorcha_cooly = $obj->thiri_sorcha_cooly;

    $thiri_giant_cooly = $obj->thiri_giant_cooly;

    $updateQuery = "UPDATE `setting` SET 
                    
                    `sorcha_cooly` = ?,
                 
                    `giant_cooly` = ?,
                    
                    `thiri_sorcha_cooly` = ?,
                    
                    `thiri_giant_cooly` = ?
                    
                    WHERE `id` = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ssssi",  $sorcha_cooly, $giant_cooly,  $thiri_sorcha_cooly, $thiri_giant_cooly, $id);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "RingPunch details updated successfully", "id" => $id]];
    } else {

        $output = ["head" => ["code" => 400, "msg" => "Failed to update RingPunch. Error: " . $conn->error]];
    }
    echo json_encode($output);
    exit();
} elseif ($action === 'listPaySetting') {
    $stmt = $conn->prepare("SELECT * FROM `pay_setting`");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $setting = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["paySetting" => $setting] // Fixed typo: $paySetting -> $setting
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Setting details not found"],
            "body" => ["paySetting" => []]
        ];
    }
    echo json_encode($output);
    $stmt->close();
    exit();
} elseif ($action === 'updatePaySetting') {
    $id = $obj->id ?? null;
    $pay_setting_cooly_one = $obj->pay_setting_cooly_one ?? null;
    $pay_setting_cooly_two = $obj->pay_setting_cooly_two ?? null;

    if ($id && $pay_setting_cooly_one && $pay_setting_cooly_two) {
        $updateQuery = "UPDATE pay_setting SET pay_setting_cooly_one = ?, pay_setting_cooly_two = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssi", $pay_setting_cooly_one, $pay_setting_cooly_two, $id);

        if ($stmt->execute()) {
            $output = [
                "head" => ["code" => 200, "msg" => "Paysetting details updated successfully"],
                "body" => ["id" => $id]
            ];
        } else {
            error_log("SQL Error: " . $conn->error);
            $output = [
                "head" => ["code" => 400, "msg" => "Failed to update Paysetting. Error: " . $conn->error]
            ];
        }
        $stmt->close();
    } else {
        $output = [
            "head" => ["code" => 400, "msg" => "Missing or invalid parameters"]
        ];
    }
    echo json_encode($output);
    exit();
}
// Invalid Action
else {
    $response = [
        "head" => ["code" => 400, "msg" => "Invalid Action"]
    ];
}

// Close Database Connection
$conn->close();

// Return JSON Response
echo json_encode($response, JSON_NUMERIC_CHECK);
