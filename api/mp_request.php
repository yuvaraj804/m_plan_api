<?php
session_start();

include 'mint_func.inc';
require_once 'bootstrap.php'; 


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? 'insert';
// Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']));
}
try {
    if ($action === 'insert' || $action === 'edit') {
        // Validate required fields
        $required = [
            'branch_code'       => 'Branch Code',
            'equ_code'          => 'Equipment Code',
            'issue_description' => 'Issue Description',
            'priority'          => 'Priority',
            'request_id'        => 'Request ID',
           
        ];
        $missing = [];

        foreach ($required as $field => $label) {
            if (empty($_POST[$field])) {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ]);
            exit;
        }

        // Resolve IDs
        $branch_idf  = getCommonId($conn, $_POST['branch_code'], 'branch');
        $product_idf = getCommonId($conn, $_POST['equ_code'], 'product');
        $priority = getCommonId($conn, $_POST['priority'], 'pref');

       /* ----------  Handle Attachments  ---------- */
            $attachments = []; // use array instead of null
            $uploadDir = __DIR__ . '/../uploads/request/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // 1️⃣ Keep existing attachments (from hidden inputs)
            if (!empty($_POST['attach']) && is_array($_POST['attach'])) {
                foreach ($_POST['attach'] as $old) {
                    if (!empty($old)) $attachments[] = $old;
                }
            }

            // 2️⃣ Add newly uploaded attachments
            if (!empty($_FILES['attachment']['name'][0])) {
                foreach ($_FILES['attachment']['name'] as $idx => $filename) {
                    if (empty($filename)) continue;
                    $tmpName = $_FILES['attachment']['tmp_name'][$idx];
                    $safeName = $filename;
                    $target = $uploadDir . $safeName;
                    if (move_uploaded_file($tmpName, $target)) {
                        $attachments[] = $safeName;
                    }
                }
            }
            // 3️⃣ Convert array → PostgreSQL format
            $attachments = !empty($attachments)
                ? '{' . implode(',', $attachments) . '}'
                : '{}'; // empty array fallback



        // $comp_status = $statusMap[$_POST['status']] ?? $_POST['status'];

        if ($action === 'insert') {
            $insertSql = "INSERT INTO _10009_1pl.msopl_complaint (
                branch_idf, product_idf, pref_idf, cref_no,
                desc_prob, attach_det, comp_status, euser_idf
            ) VALUES (
                :branch_idf, :product_idf, :pref_idf, :cref_no,
                :desc_prob, :attach_det, 'opn', :euser_idf
            )";

            $conn->executeStatement($insertSql, [
                'branch_idf'  => $branch_idf,
                'product_idf' => $product_idf,
                'pref_idf'    => $priority,
                'cref_no'     => $_POST['request_id'],
                'desc_prob'   => $_POST['issue_description'],
                'attach_det'  => $attachments,
                'euser_idf'   => $_SESSION['guser_id']  
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Complaint Registered Successfully'
            ]);
        }

        elseif ($action === 'edit') {
            $updateSql = "UPDATE _10009_1pl.msopl_complaint SET
                branch_idf = :branch_idf,
                product_idf = :product_idf,
                pref_idf = :pref_idf,
                desc_prob = :desc_prob,
                attach_det = :attach_det,
                comp_status = 'opn',
                euser_idf = :euser_idf
                WHERE comp_id = :comp_id";

            $conn->executeStatement($updateSql, [

                'comp_id'  => $_POST['comp_id'],
                'branch_idf'  => $branch_idf,
                'product_idf' => $product_idf,
                'pref_idf'    => $priority,
                'desc_prob'   => $_POST['issue_description'],
                'attach_det'  => $attachments,
                // 'comp_status' => $comp_status,
                'euser_idf'   =>$_SESSION['guser_id'],
                'cref_no'     => $_POST['request_id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Complaint Updated Successfully'
            ]);
        }
    }

    elseif ($action === 'del') {
        if (empty($_POST['comp_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing Request ID for deletion.'
            ]);
            exit;
        }

        $deleteSql = "DELETE FROM _10009_1pl.msopl_complaint WHERE comp_id = :comp_id";

        $conn->executeStatement($deleteSql, [
            'comp_id' => $_POST['comp_id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Complaint deleted successfully.'
        ]);
    }

    else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action specified.'
        ]);
    }
} catch (\Exception $e) {
    print_r('Complaint API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process complaint.'
    ]);
}
exit;
