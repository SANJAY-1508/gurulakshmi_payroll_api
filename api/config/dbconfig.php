<?php
$name = "localhost";
$username = "root";
$password = "";
$database = "gurulakshmi_payroll";

// Establishing the connection
$conn = new mysqli($name, $username, $password, $database);

// Check for connection errors
if ($conn->connect_error) {
    $output = array();
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "DB Connection Lost: " . $conn->connect_error;

    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit(); // Terminate script to prevent further errors
}

// Function to check if a string contains only numbers
function numericCheck($data)
{
    // Check if the string contains only digits
    return preg_match('/^\d+$/', $data) === 1;
}

// Function to generate a unique ID
function uniqueID($prefix_name, $auto_increment_id)
{
    date_default_timezone_set('Asia/Calcutta'); // Set timezone
    $timestamp = date('YmdHis'); // Format timestamp as YYYYMMDDHHMMSS
    $encryptId = $prefix_name . "_" . $timestamp . "_" . $auto_increment_id;

    // Hash the ID using MD5 (you may replace with SHA256 for better security)
    return md5($encryptId);
}
function generateDeliverySlipNumber($conn)
{
    global $conn;
    // Query to get the maximum ID from the delivery table
    $query = "SELECT MAX(id) AS max_id FROM delivery";
    $result = $conn->query($query); // Assume $this->db is the database connection

    if ($result && $row = $result->fetch_assoc()) {
        $maxId = $row['max_id'] ?? 0; // Use 0 if no max_id is found
    } else {
        $maxId = 0; // Default if query fails
    }

    $nextId = $maxId + 1;
    $paddedId = str_pad($nextId, 3, '0', STR_PAD_LEFT);
    return "MH" . $paddedId;
}
function productCountDelivery($conn, $product_name)
{
    $query = "SELECT Sub_count FROM products WHERE product_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['Sub_count'] ?? 0;
}
