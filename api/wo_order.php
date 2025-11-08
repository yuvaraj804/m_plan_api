<?php
session_start();

include 'mint_func.inc';
require_once 'bootstrap.php'; 
   

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? '';
$wo_id  = $_POST['wo_id'] ?? '';
// Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']));
}
try {
    if ($action === 'insert') {

        // 🔸 Required Fields
        $required = ['ref_idf'=>'Request Id', 'equ_code'=>'Equipment', 'task_des'=>'Task Decision', 'assign_to'=>'Assigned Technician'];
        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($_POST[$field])) {
                $missing[] = $label;
            }
        }

        if ($missing) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing fields: ' . implode(', ', $missing)
            ]);
            exit;
        }

        // 🔸 Identify source type (plan or complaint)
        $ref_cat = $_POST['ref_cat'] ?? 'c';
        $ref_idf_inp = $_POST['ref_idf'];
        $ref_idf = $conn->fetchOne(
            "SELECT comp_id FROM _10009_1pl.msopl_complaint WHERE cref_no = ?",
            [$ref_idf_inp]
        );
        
        // 🔸 Resolve references
        $product_idf = getCommonId($conn, $_POST['equ_code'], 'product');
        $assign_idf  = getCommonId($conn, $_POST['assign_to'], 'assign_to');

        if (!$product_idf || !$assign_idf) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Equipment or Assignee.'
            ]);
            exit;
        }

        // 🔸 Handle attachments (optional)
        $attachments = null;
        $uploadDir = __DIR__ . '/uploads/workorder/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!empty($_FILES['attachment']['name'][0])) {
            $saved = [];
            foreach ($_FILES['attachment']['name'] as $i => $f) {
                $safe = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $f);
                $path = $uploadDir . $safe;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $path)) {
                    $saved[] = $safe;
                }
            }
            $attachments = '{' . implode(',', $saved) . '}';
        }

        // 🔸 Insert record
        $sql = "
            INSERT INTO _10009_1pl.msopl_worder (
                ref_cat, ref_idf, desc_prob, attach_det, assign_to,
                wo_status, euser_idf
            )
            VALUES (
                :ref_cat, :ref_idf, :desc_prob, :attach_det, :assign_to,
                'opn', :euser_idf
            )
            RETURNING wo_id
            ";

        $wo_id = $conn->fetchOne($sql, [
            'ref_cat'     => $ref_cat,
            'ref_idf'     => $ref_idf,
            'product_idf' => $product_idf,
            'desc_prob'   => $_POST['task_des'] ?? '',
            'attach_det'  => $attachments,
            'assign_to'   => $assign_idf,
            'euser_idf'   => $_SESSION['guser_id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Work Order Created Successfully',
            'wo_id'   => $wo_id
        ]);
    }
    elseif ($action === 'edit') {
        if (empty($wo_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing Work Order ID']);
            exit;
        }
        if (empty($_POST['assign_to'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing field: Assigned Technician'
            ]);
            exit;
        }
        // --- Resolve IDs ---
        $ref_cat = $_POST['ref_cat'] ?? 'c';
        $ref_idf_inp = $_POST['ref_idf'] ?? '';
        $ref_idf = $conn->fetchOne(
            "SELECT comp_id FROM _10009_1pl.msopl_complaint WHERE cref_no = ?",
            [$ref_idf_inp]
        );
    
        $assign_idf = getCommonId($conn, $_POST['assign_to'] ?? '', 'assign_to');
    
        // --- Handle attachment (optional new uploads) ---
        $attachments = null;
        $uploadDir = dirname(__DIR__) . '/uploads/workorder/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (!empty($_FILES['attachment']['name'][0])) {
            $saved = [];
            foreach ($_FILES['attachment']['name'] as $i => $f) {
                $safe = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $f);
                $path = $uploadDir . $safe;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $path)) {
                    $saved[] = $safe;
                }
            }
            $attachments = '{' . implode(',', $saved) . '}';
        }
    
        // --- Update existing record ---
        $conn->executeStatement("
            UPDATE _10009_1pl.msopl_worder
            SET 
                ref_cat        = :ref_cat,
                ref_idf        = :ref_idf,
                assign_to      = :assign_to,
                desc_prob      = :desc_prob,
                wo_status      = :wo_status,
                compl_dtime    = :compl_dtime,
                compl_remarks  = :compl_remarks,
                attach_det     = COALESCE(:attach_det, attach_det)
            WHERE wo_id = :wo_id
        ", [
            'ref_cat'       => $ref_cat,
            'ref_idf'       => $ref_idf,
            'assign_to'     => $assign_idf,
            'desc_prob'     => $_POST['task_des'] ?? '',
            'wo_status'     => $_POST['wo_status'] ?? 'opn',
            'compl_dtime'   => !empty($_POST['compl_dtime'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['compl_dtime'])))
                                : null,
            'compl_remarks' => $_POST['compl_remarks'] ?? '',
            'attach_det'    => $attachments,
            'wo_id'         => $wo_id
        ]);
    
        echo json_encode([
            'success' => true,
            'message' => 'Work Order Updated Successfully',
            // 'wo_id'   => $wo_id
        ]);
    }
    
    elseif ($action === 'close') {
        $compl_remarks = $_POST['compl_remarks'] ?? '';
        $compl_desc    = $_POST['compl_desc'] ?? '';
        $follow_up     = $_POST['follow_up'] ?? '';
        $compl_dtime   = !empty($_POST['compl_dtime']) ? \DateTime::createFromFormat('d/m/Y', $_POST['compl_dtime']) : null;
        if (empty($_POST['compl_dtime'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing field: Completion Date'
            ]);
            exit;
        }
        $compl_attach_det = null;
        $uploadDir = __DIR__ . '/../uploads/workorder/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (!empty($_FILES['compl_attachment']['name'][0])) {
            $saved = [];
            foreach ($_FILES['compl_attachment']['name'] as $i => $f) {
                $safe =  $f;
                $path = $uploadDir . $safe;
                if (move_uploaded_file($_FILES['compl_attachment']['tmp_name'][$i], $path)) {
                    $saved[] = $safe;
                }
            }
            $compl_attach_det = '{' . implode(',', $saved) . '}';
        }
        $conn->beginTransaction();

        $exists = $conn->fetchOne("SELECT COUNT(*) FROM _10009_1pl.msopl_worder WHERE wo_id = ?", [$wo_id]);
        if (!$exists) {
            json_response(false, null, 'Work Order not found');
        }

        $conn->executeStatement("
            UPDATE _10009_1pl.msopl_worder
            SET wo_status = 'cls',
                compl_dtime = :compl_dtime,
                compl_remarks = :compl_remarks,
                desc_prob = :desc_prob,
                compl_attach_det = :compl_attach_det
            WHERE wo_id = :wo_id
        ", [
            'compl_dtime'   => $compl_dtime ? $compl_dtime->format('Y-m-d') : null,
            'compl_remarks' => $compl_remarks,
            'desc_prob'     => $compl_desc,
            'compl_attach_det'  => $compl_attach_det,
            'wo_id'         => $wo_id
        ]);

        // if (!empty($follow_up)) {
        //     $conn->executeStatement("
        //         INSERT INTO _10009_1pl.msopl_follow_up (wo_id, followup_desc, euser_idf)
        //         VALUES (:wo_id, :followup_desc, :euser_idf)
        //     ", [
        //         'wo_id'         => (int)$wo_id,
        //         'followup_desc' => $follow_up,
        //         'euser_idf'     => 1
        //     ]);
        // }

        $conn->commit();
        json_response(true, ['wo_id' => $wo_id], 'Work Order Closed');
    }
    elseif ($action === 'del') {
        if (empty($_POST['wo_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing Work Order ID']);
            exit;
        }

        $conn->executeStatement("DELETE FROM _10009_1pl.msopl_worder WHERE wo_id = :wo_id", [
            'wo_id' => $_POST['wo_id']
        ]);

        echo json_encode(['success' => true, 'message' => 'Work Order Deleted Successfully']);
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Invalid Action']);
    }

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
exit;
