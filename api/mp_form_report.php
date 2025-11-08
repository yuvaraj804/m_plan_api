<?php
include 'mint_func.inc';
require_once 'bootstrap.php'; 
  

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
$action = $_POST['action']??'';

try {
    // ===============================Single Record================================
    if (!empty($_POST['mplan_id'])&& $action!=='view') {
        $sql = "
            SELECT 
                b.dcode AS branch,
                p.dcode AS equipment,
                mt.dcode AS mtype,
                asn.dcode AS assign_to,
                pst.dcode AS pstage,
                pr.dcode AS priority,
                m.mref_no,
                TO_CHAR(m.start_date, 'DD/MM/YYYY') AS start_date,
                TO_CHAR(m.end_date, 'DD/MM/YYYY') AS end_date,
                m.desc_prob,
                m.attach_det AS attachment,
                m.remark,
                m.frequency AS freq_type,
                TO_CHAR(m.next_due, 'DD/MM/YYYY') AS next_due,
                m.rstatus
            FROM _10009_1pl.msopl_mplan m
            LEFT JOIN _10009_1pl.common b   ON b.did = m.branch_idf AND TRIM(b.category) = 'branch'
            LEFT JOIN _10009_1pl.common p   ON p.did = m.product_idf AND TRIM(p.category) = 'product'
            LEFT JOIN _10009_1pl.common mt  ON mt.did = m.mtype_idf AND TRIM(mt.category) = 'mtype'
            LEFT JOIN _10009_1pl.common asn ON asn.did = m.assign_to AND TRIM(asn.category) = 'assign_to'
            LEFT JOIN _10009_1pl.common pst ON pst.did = m.pstage_idf AND TRIM(pst.category) = 'pstage'
            LEFT JOIN _10009_1pl.common pr ON pr.did = m.pref_idf AND TRIM(pr.category) = 'pref'
            WHERE m.mplan_id = :mplan_id
        ";

        $data = $conn->fetchAssociative($sql, ['mplan_id' => $_POST['mplan_id']]);
            if ($data&&!empty($data['attachment'])) {
                // PostgreSQL returns arrays as "{file1,file2}" strings unless already converted
                if (is_array($data['attachment'])) {
                    $data['attachment'] = array_filter($data['attachment']); // already array
                } else {
                    $raw = trim((string)$data['attachment'], '{}');
                    $data['attachment'] = $raw !== '' ? array_map('trim', explode(',', $raw)) : [];
                }
            } else {
                $data['attachment'] = [];
            }
            
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // ===============================Filtered Report List================================
    $where = [];
    $params = [];
    if (!empty($_POST['mplan_id'])) {
        $where[] = "m.mplan_id = :mplan_id";
        $params['mplan_id'] = $_POST['mplan_id'];
    }
    if (!empty($_POST['branch_code'])) {
        $branch_id = getCommonId($conn, $_POST['branch_code'], 'branch');
        if ($branch_id) {
            $where[] = "m.branch_idf = :branch_id";
            $params['branch_id'] = $branch_id;
        }
    }

    if (!empty($_POST['equ_code'])) {
        $product_id = getCommonId($conn, $_POST['equ_code'], 'product');
        if ($product_id) {
            $where[] = "m.product_idf = :product_id";
            $params['product_id'] = $product_id;
        }
    }

    if (!empty($_POST['mt_type'])) {
        $mtype_id = getCommonId($conn, $_POST['mt_type'], 'mtype');
        if ($mtype_id) {
            $where[] = "m.mtype_idf = :mtype_id";
            $params['mtype_id'] = $mtype_id;
        }
    }

    if (!empty($_POST['assign_to'])) {
        $assign_id = getCommonId($conn, $_POST['assign_to'], 'assign_to');
        if ($assign_id) {
            $where[] = "m.assign_to = :assign_to";
            $params['assign_to'] = $assign_id;
        }
    }

    // if (!empty($_POST['rstatus'])) {
    //     $where[] = "m.rstatus = :rstatus";
    //     $params['rstatus'] = $_POST['rstatus'];
    // }
   
if (!empty($_POST['rstatus'])) {
    $statusText = trim($_POST['rstatus']);
    $dbStatus = $reverseStatusLabels[$statusText] ?? null;

    if ($dbStatus !== null) {
        $where[] = "m.rstatus = :rstatus";
        $params['rstatus'] = $dbStatus;
    } else {
        // Debug (optional)
        error_log("Unknown status filter: {$dbStatus}");
    }
}
    if (!empty($_POST['from_date']) && !empty($_POST['to_date'])) {
        $where[] = "m.start_date BETWEEN :from_date AND :to_date";
        $params['from_date'] = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['from_date'])));
        $params['to_date']   = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['to_date'])));
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT 
            m.mplan_id,
            b.dname AS branch_name,
            ps.dname AS prod_stage,
            eq.dname AS equipment_name,
            mt.dname AS mt_type,
            asn.dname AS assign_to,
            m.mref_no,
            TO_CHAR(m.start_date, 'DD/MM/YYYY') AS start_date,
            TO_CHAR(m.end_date, 'DD/MM/YYYY') AS end_date,
            TO_CHAR(m.next_due, 'DD/MM/YYYY') AS next_due,
            m.frequency,
            m.desc_prob,
            m.remark,
            m.attach_det AS attachment,
            m.rstatus
        FROM _10009_1pl.msopl_mplan m
        LEFT JOIN _10009_1pl.common b   ON m.branch_idf  = b.did
        LEFT JOIN _10009_1pl.common ps  ON m.pstage_idf  = ps.did
        LEFT JOIN _10009_1pl.common eq  ON m.product_idf = eq.did
        LEFT JOIN _10009_1pl.common mt  ON m.mtype_idf   = mt.did
        LEFT JOIN _10009_1pl.common asn ON m.assign_to   = asn.did
        $whereSQL
        ORDER BY m.mplan_id DESC
    ";

    $rows = $conn->fetchAllAssociative($sql, $params);

    foreach ($rows as &$row) {
        $row['rstatus'] = $statusLabels[$row['rstatus']] ?? $row['rstatus'];
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (\Exception $e) {
    print_r('POST mplan report error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch maintenance plan report.']);
}
exit;
