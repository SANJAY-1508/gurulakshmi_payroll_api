<?php

include 'config/dbconfig.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json, true); // Decode JSON to array
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Check if action is missing
if (!isset($obj['action'])) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj['action'];

if ($action === 'listStocks') {
    // Query to get data from product_stock
    $stmtProductStock = $conn->prepare("SELECT * FROM `product_stock` WHERE 1");
    $stmtProductStock->execute();
    $resultProductStock = $stmtProductStock->get_result();

    if ($resultProductStock->num_rows > 0) {
        $productStock = $resultProductStock->fetch_all(MYSQLI_ASSOC);
    } else {
        $productStock = [];
    }

    // Query to get data from magazineStock
    $stmtMagazineStock = $conn->prepare("SELECT * FROM `magazineStock` WHERE 1");
    $stmtMagazineStock->execute();
    $resultMagazineStock = $stmtMagazineStock->get_result();

    if ($resultMagazineStock->num_rows > 0) {
        $magazineStock = $resultMagazineStock->fetch_all(MYSQLI_ASSOC);
    } else {
        $magazineStock = [];
    }

    // Combine both stock results into the response
    $output = [
        "head" => ["code" => 200, "msg" => "Stock Data Retrieved Successfully"],
        "body" => [
            "product_stock" => $productStock,
            "magazine_stock" => $magazineStock
        ]
    ];
} else if ($action === 'listCoolieReport') {
    $fromDate = $obj['from_date'] ?? null;
    $toDate = $obj['to_date'] ?? null;

    $finalReport = [];

    // Updated queries based on actual table structure
    $queries = [
        "Deluxe Payroll" => "SELECT staff_name, 'டீலக்ஸ் பின்னல்' AS staff_type, entry_date, products, total FROM deluxe WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
        "Knotting Payroll" => "SELECT staff_name, 'பின்னல் பிரிவு' AS staff_type, entry_date, products, total FROM knotting_payroll WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
        "Packing Payroll" => "SELECT staff_name, 'பாக்கெட் பிரிவு' AS staff_type, entry_date, products, total FROM packing_payroll WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
        "Ring Boxing Payroll" => "SELECT staff_name, 'வளையம் குத்து பிரிவு' AS staff_type, entry_date, products, total FROM ring_boxing WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
        "Explosive Payroll" => "SELECT staff_name, 'வெடி உருடு பிரிவு' AS staff_type, entry_date, products, total FROM explosive_device_payroll WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
        "Pay Payroll" => "SELECT staff_name, 'செலுத்து பிரிவு' AS staff_type, entry_date, products, total FROM pay WHERE entry_date BETWEEN ? AND ? AND delete_at = 0",
    ];

    foreach ($queries as $payrollType => $query) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $fromDate, $toDate);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Decode the products JSON
                $products = json_decode($row['products'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $products = [];
                }

                // Create a new report entry for each row
                $reportEntry = [
                    'staff_name' => $row['staff_name'],
                    'staff_type' => $row['staff_type'],
                    'entry_date' => $row['entry_date'],
                    'products' => [],
                    'total' => 0
                ];

                // Add decoded products to the report entry
                $calculatedTotal = 0;
                if (!empty($products)) {
                    foreach ($products as $product) {
                        $productTotal = ($product['count'] ?? 0) * ($product['per_cooly_rate'] ?? 0);
                        $reportEntry['products'][] = [
                            'product_name' => $product['product_name'] ?? '',
                            'count' => $product['count'] ?? 0,
                            'per_cooly_rate' => $product['per_cooly_rate'] ?? 0,
                            'total' => $productTotal
                        ];
                        $calculatedTotal += $productTotal;
                    }
                }

                // Use calculated total instead of row total for consistency
                $reportEntry['total'] = $calculatedTotal;
                $finalReport[] = $reportEntry;
            }
        }
        $stmt->close();
    }

    if (empty($finalReport)) {
        $output = [
            "head" => ["code" => 404, "msg" => "No Records Found"],
            "body" => [
                "action" => "listCoolieReport",
                "from_date" => $fromDate,
                "to_date" => $toDate,
                "report" => []
            ]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Coolie Report Retrieved Successfully"],
            "body" => [
                "action" => "listCoolieReport",
                "from_date" => $fromDate,
                "to_date" => $toDate,
                "report" => $finalReport
            ]
        ];
    }
} elseif ($action === 'listCompanyPayrollReport') {
    $fromDate = $obj['from_date'] ?? null;
    $toDate = $obj['to_date'] ?? null;

    $finalReport = [];

    // Query to fetch payroll data within the date range
    $query = "SELECT id, entry_date, company_data FROM company_payroll WHERE entry_date BETWEEN '$fromDate' AND '$toDate' AND delete_at = 0";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $staffSummary = [];

        while ($row = $result->fetch_assoc()) {
            $entryDate = $row['entry_date'];
            $companyData = json_decode($row['company_data'], true);

            if (!empty($companyData)) {
                foreach ($companyData as $staff) {
                    $staffName = $staff['staff_name'];
                    $status = $staff['status'];
                    $wages = (float) $staff['wages'];

                    if (!isset($staffSummary[$staffName])) {
                        $staffSummary[$staffName] = [
                            "staff_name" => $staffName,
                            "total_days" => 0,
                            "present_days" => 0,
                            "absent_days" => 0,
                            "salary" => 0
                        ];
                    }

                    $staffSummary[$staffName]['total_days'] += 1;
                    if ($status === "present") {
                        $staffSummary[$staffName]['present_days'] += 1;
                    } else {
                        $staffSummary[$staffName]['absent_days'] += 1;
                    }
                    $staffSummary[$staffName]['salary'] += $wages;
                }
            }
        }

        $finalReport = array_values($staffSummary);
    }

    if (empty($finalReport)) {
        $output = [
            "head" => ["code" => 404, "msg" => "No Records Found"],
            "body" => [
                "action" => "listCompanyPayrollReport",
                "from_date" => $fromDate,
                "to_date" => $toDate,
                "report" => []
            ]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "Company Payroll Report Retrieved Successfully"],
            "body" => [
                "action" => "listCompanyPayrollReport",
                "from_date" => $fromDate,
                "to_date" => $toDate,
                "report" => $finalReport
            ]
        ];
    }
} else {
    $output = [
        "head" => ["code" => 400, "msg" => "Invalid Parameters"],
        "inputs" => $obj
    ];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
