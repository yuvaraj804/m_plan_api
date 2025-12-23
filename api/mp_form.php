<?php
session_start();
require_once 'bootstrap.php';
 
include 'mint_func.inc';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? '';

try {

    
    if ($action === 'calc_next_due') {
        $startDate   = $_POST['startDate'] ?? '';
        $freqPeriod  = $_POST['freqPeriod'] ?? '';
        $freqNo      = $_POST['freqNo'] ?? '';
        $freqWeekday = $_POST['freqWeekday'] ?? null;
        $freqMonth   = $_POST['freqMonth'] ?? null;
        $freqDay     = $_POST['freqDay'] ?? null;
        $freqWeekNum = $_POST['freqWeekNum'] ?? null;

        $nextDueData = calculateNextDueDate(
            $startDate,
            $freqPeriod,
            $freqNo,
            $freqWeekday,
            $freqMonth,
            $freqDay,
            $freqWeekNum
        );

        echo json_encode([
            'success' => true,
            'nextDue' => $nextDueData['nextDue'] ?? null
        ]);
        exit;
    }
    /* =====================================================
       INSERT / UPDATE 
    ====================================================== */
    if ($action === 'insert' || $action === 'edit') {
        // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']));
    }
    
        $required = [
            'branch_code' => 'Branch Code',
            'ps_code'     => 'Production Stage',
            'equ_code'    => 'Equipment Code',
            'plan_name'   => 'Plan Name',
            'mt_type'     => 'Maintenance Type',
            'mref_no'     => 'Plan Reference No',
            'start_date'  => 'Start Date',
            'end_date'    => 'End Date',
            'next_due'    => 'Next Due',
            // 'freq_type'   => 'Frequency Type',
            'assign_to'   => 'Assigned To',
            'priority'    => 'Priority'
        ];
        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($_POST[$field])) $missing[] = $label;
        }
        if (!empty($missing)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
            exit;
        }
 
        /* ----------  Resolve foreign keys ---------- */
        $branch_idf  = getCommonId($conn, $_POST['branch_code'], 'branch');
        $pstage_idf  = getCommonId($conn, $_POST['ps_code'], 'pstage');
        $product_idf = getCommonId($conn, $_POST['equ_code'], 'product');
        $mtype_idf   = getCommonId($conn, $_POST['mt_type'], 'mtype');
        $assign_to   = getCommonId($conn, $_POST['assign_to'], 'assign_to');
        $priority   = getCommonId($conn, $_POST['priority'], 'pref');
        
        /* ----------  Handle Attachments ---------- */
        $attachments = []; // use array instead of null

        $uploadDir = __DIR__ . '/../uploads/plan/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

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
                $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
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

        /* ----------  Date Parsing ---------- */
        $start_date = !empty($_POST['start_date']) ? \DateTime::createFromFormat('d/m/Y', $_POST['start_date']) : null;
        $end_date   = !empty($_POST['end_date'])   ? \DateTime::createFromFormat('d/m/Y', $_POST['end_date'])   : null;
        $next_due   = !empty($_POST['next_due'])   ? \DateTime::createFromFormat('d/m/Y', $_POST['next_due'])   : null;

       
       
        /* ----------  UPDATE  ---------- */
        if ($action === 'edit' && $_POST['mplan_id']) {
            $sql = "UPDATE _10009_1pl.msopl_mplan SET
                branch_idf = :branch_idf,
                pstage_idf = :pstage_idf,
                product_idf = :product_idf,
                mtype_idf  = :mtype_idf,
                mref_no    = :mref_no,
                start_date = :start_date,
                end_date   = :end_date,
                desc_prob  = :desc_prob,
                attach_det = :attach_det,
                assign_to  = :assign_to,
                remark     = :remark,
                frequency  = :frequency,
                pref_idf   = :pref_idf,
                next_due   = :next_due
            WHERE mplan_id = :mplan_id";

            $conn->executeStatement($sql, [
                'mplan_id'    => $_POST['mplan_id'],
                'branch_idf' => $branch_idf,
                'pstage_idf' => $pstage_idf,
                'product_idf'=> $product_idf,
                'mtype_idf'  => $mtype_idf,
                'mref_no'    => $_POST['mref_no'],
                'start_date' => $start_date? $start_date->format('Y-m-d'): null,
                'end_date'   => $end_date? $end_date->format('Y-m-d'): null,
                'desc_prob'  => $_POST['tdesc'] ?? '',
                'attach_det' => $attachments,
                'assign_to'  => $assign_to,
                'remark'     => $_POST['remark'] ?? '',
                'frequency'  => $_POST['freq_type'],
                'pref_idf'   => $priority,
                'next_due'   => $next_due ? $next_due->format('Y-m-d'): null,
                
            ]);

            echo json_encode(['success' => true, 
            'message' => 'Plan updated successfully',
        ]);
        }

        /* ----------  INSERT  ---------- */
        elseif ($action === 'insert') {
            $sql = "INSERT INTO _10009_1pl.msopl_mplan (
                branch_idf, pstage_idf, product_idf, mtype_idf, mref_no,
                start_date, end_date, desc_prob, attach_det, assign_to, remark,
                frequency, pref_idf, next_due, due_alert, rstatus, euser_idf
            ) VALUES (
                 :branch_idf, :pstage_idf, :product_idf, :mtype_idf, :mref_no,
                :start_date, :end_date, :desc_prob, :attach_det, :assign_to, :remark,
                :frequency, :pref_idf, :next_due, :due_alert, :rstatus, :euser_idf
            )";
    // $mplan_id = $conn->fetchOne("SELECT nextval('_10009_1pl.msopl_mplan_mplan_id_seq')");
            $conn->executeStatement($sql, [
                // 'mplan_id'  => $mplan_id,
                'branch_idf' => $branch_idf,
                'pstage_idf' => $pstage_idf,
                'product_idf'=> $product_idf,
                'mtype_idf'  => $mtype_idf,
                'mref_no'    => $_POST['mref_no'],
                'start_date' => $start_date? $start_date->format('Y-m-d'): null,
                'end_date'   => $end_date? $end_date->format('Y-m-d'): null,
                'desc_prob'  => $_POST['tdesc'] ?? '',
                'attach_det' => $attachments,
                'assign_to'  => $assign_to,
                'remark'     => $_POST['remark'] ?? '',
                'frequency'  => $_POST['freq_type'],
                'pref_idf'   => $priority,
                'next_due'   => $next_due? $next_due->format('Y-m-d'): null,
                'due_alert'  => 'Y',
                'rstatus'    => 'act',
                'euser_idf'  => $_SESSION['guser_id']
            ]);
            // file_put_contents('debug_upload.txt', print_r($_FILES, true))    ;

            echo json_encode(['success' => true, 
            'message' => 'Plan created successfully', 
           
        ]);
        }
    }

    /* =====================================================
       DELETE
    ====================================================== */
    elseif ($action === 'del') {
        if (empty($_POST['mplan_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing Plan ID for deletion.']);
            exit;
        }

        $deleteSql = "DELETE FROM _10009_1pl.msopl_mplan WHERE mplan_id = :mplan_id";
        $conn->executeStatement($deleteSql, ['mplan_id' => $_POST['mplan_id']]);

        echo json_encode(['success' => true, 'message' => 'Maintenance plan deleted successfully.']);
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    }
}
catch (\Exception $e) {
    print_r('MP Form API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process maintenance plan.']);
}
exit;
