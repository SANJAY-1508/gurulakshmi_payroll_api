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
   // Update these lines in report.php inside the listCoolieReport action
$queries = [
    "Deluxe Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, dp.staff_name, 'டீலக்ஸ் பின்னல்' AS staff_type, dp.entry_date, dp.products, dp.total 
                     FROM deluxe dp 
                     JOIN staff s ON dp.staff_name = s.Name 
                     WHERE dp.entry_date BETWEEN ? AND ? AND dp.delete_at = 0",

    "Knotting Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, kp.staff_name, 'பின்னல் பிரிவு' AS staff_type, kp.entry_date, kp.products, kp.total 
                       FROM knotting_payroll kp 
                       JOIN staff s ON kp.staff_name = s.Name 
                       WHERE kp.entry_date BETWEEN ? AND ? AND kp.delete_at = 0",

    "Packing Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, pp.staff_name, 'பாக்கெட் பிரிவு' AS staff_type, pp.entry_date, pp.products, pp.total 
                      FROM packing_payroll pp 
                      JOIN staff s ON pp.staff_name = s.Name 
                      WHERE pp.entry_date BETWEEN ? AND ? AND pp.delete_at = 0",

    "Ring Boxing Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, rbp.staff_name, 'வளையம் குத்து பிரிவு' AS staff_type, rbp.entry_date, rbp.products, rbp.total 
                          FROM ring_boxing rbp 
                          JOIN staff s ON rbp.staff_name = s.Name 
                          WHERE rbp.entry_date BETWEEN ? AND ? AND rbp.delete_at = 0",

    "Explosive Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, edp.staff_name, 'வெடி உருடு பிரிவு' AS staff_type, edp.entry_date, edp.products, edp.total 
                        FROM explosive_device_payroll edp 
                        JOIN staff s ON edp.staff_name = s.Name 
                        WHERE edp.entry_date BETWEEN ? AND ? AND edp.delete_at = 0",

    "Pay Payroll" => "SELECT s.id AS staff_numeric_id, s.staff_advance, p.staff_name, 'செலுத்து பிரிவு' AS staff_type, p.entry_date, p.products, p.total 
                  FROM pay p 
                  JOIN staff s ON p.staff_name = s.Name 
                  WHERE p.entry_date BETWEEN ? AND ? AND p.delete_at = 0",
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
                $reportEntry['staff_id'] = $row['staff_numeric_id'] ?? null;
                $reportEntry['staff_advance'] = $row['staff_advance'] ?? 0;
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
} else if ($action === 'listWeeklySalary') {
    $query = "SELECT * FROM weekly_salary WHERE delete_at = 0 ORDER BY create_at DESC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $weekly_salary = [];
        while ($row = $result->fetch_assoc()) {
            $weekly_salary[] = [
                "id" => $row["id"],
                "weekly_salary_id" => $row["weekly_salary_id"],
                "from_date" => $row["from_date"],
                "to_date" => $row["to_date"],
                "salary_data" => json_decode($row["salary_data"], true), // Decode JSON data
                "create_at" => $row["create_at"]
            ];
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["weekly_salary" => $weekly_salary]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No Weekly Salary Found"],
            "body" => ["weekly_salary" => []]
        ];
    }
} elseif ($action === 'createWeeklySalary') {
    $data = $obj['data'] ?? null;
    $from_date = $obj['from_date'] ?? null;
    $to_date = $obj['to_date'] ?? null;

    if (empty($from_date) || empty($to_date) || empty($data) || !is_array($data)) {
        $output = [
            "head" => ["code" => 400, "msg" => "Missing or invalid parameters: from_date, to_date, and data (array) required"]
        ];
    } else {
        $from_date_obj = new DateTime($from_date);
        $to_date_obj = new DateTime($to_date);
        $formatted_from = $from_date_obj->format('Y-m-d');
        $formatted_to = $to_date_obj->format('Y-m-d');
        $data_json = json_encode($data, JSON_UNESCAPED_UNICODE);

        // Check for overlapping date ranges
        $stmtCheck = $conn->prepare("SELECT COUNT(*) as count FROM weekly_salary WHERE ? <= to_date AND ? >= from_date AND delete_at = 0");
        $stmtCheck->bind_param("ss", $formatted_from, $formatted_to);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $rowCheck = $resultCheck->fetch_assoc();
        $stmtCheck->close();

        if ($rowCheck['count'] > 0) {
            $output = [
                "head" => ["code" => 400, "msg" => "Weekly salary already exists for overlapping date range"]
            ];
        } else {
            // Start transaction for consistency
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("INSERT INTO weekly_salary (weekly_salary_id, from_date, to_date, salary_data, create_at) VALUES (?, ?, ?, ?, ?)");
                $weekly_salary_id = uniqid('WSY'); // Generate unique ID

                $stmt->bind_param("sssss", $weekly_salary_id, $formatted_from, $formatted_to, $data_json, $timestamp);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert weekly_salary: " . $stmt->error);
                }
                $stmt->close();

                // Process deductions for each staff
                $errors = [];
                foreach ($data as $staff_entry) {
                    $staff_name = $staff_entry['staff_name'] ?? null;
                    $deduction = (float) ($staff_entry['deduction'] ?? 0);

                    if ($deduction > 0 && $staff_name) {
                        // Find staff ID by name (assuming unique names)
                        $stmtStaff = $conn->prepare("SELECT id, staff_id FROM staff WHERE Name = ? AND deleted_at = 0 LIMIT 1");
                        $stmtStaff->bind_param("s", $staff_name);
                        $stmtStaff->execute();
                        $staffResult = $stmtStaff->get_result();
                        $staff_row = $staffResult->fetch_assoc();
                        $stmtStaff->close();

                        if ($staff_row) {
                            $staff_id_internal = $staff_row['id'];
                            $staff_id_string = $staff_row['staff_id'];

                            // Create advance log
                            $advance_id = uniqid('ADV');
                            $entry_date = $formatted_from; // Use from_date as entry_date
                            $stmtAdvance = $conn->prepare("
                                INSERT INTO staff_advance 
                                (advance_id, weekly_salary_id, staff_id, staff_name, amount, type, recovery_mode, entry_date, created_at, delete_at)
                                VALUES (?, ?, ?, ?, ?, 'less', 'salary', ?, ?, 0)
                            ");
                            $stmtAdvance->bind_param(
                                "ssssdss",
                                $advance_id,
                                $weekly_salary_id,
                                $staff_id_string,
                                $staff_name,
                                $deduction,
                                $entry_date,
                                $timestamp
                            );
                            if (!$stmtAdvance->execute()) {
                                $errors[] = "Failed to create advance for $staff_name: " . $stmtAdvance->error;
                            }
                            $stmtAdvance->close();

                            // Update staff advance balance
                            $stmtUpdateStaff = $conn->prepare("UPDATE staff SET staff_advance = staff_advance - ? WHERE id = ? AND deleted_at = 0");
                            $stmtUpdateStaff->bind_param("di", $deduction, $staff_id_internal);
                            if (!$stmtUpdateStaff->execute()) {
                                $errors[] = "Failed to update advance balance for $staff_name: " . $stmtUpdateStaff->error;
                            }
                            $stmtUpdateStaff->close();
                        } else {
                            $errors[] = "Staff not found for $staff_name";
                        }
                    }
                }

                if (!empty($errors)) {
                    $conn->rollback();
                    $output = [
                        "head" => ["code" => 400, "msg" => "Weekly salary created but errors in deductions: " . implode('; ', $errors)]
                    ];
                } else {
                    $conn->commit();
                    $output = [
                        "head" => ["code" => 200, "msg" => "Weekly salary created successfully"],
                        "body" => ["weekly_salary_id" => $weekly_salary_id]
                    ];
                }
            } catch (Exception $e) {
                $conn->rollback();
                $output = [
                    "head" => ["code" => 400, "msg" => "Failed to create weekly salary: " . $e->getMessage()]
                ];
            }
        }
    }
} else {
    $output = [
        "head" => ["code" => 400, "msg" => "Invalid Parameters"],
        "inputs" => $obj
    ];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
