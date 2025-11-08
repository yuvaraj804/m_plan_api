<?php
include 'mint_func.inc';
require_once 'bootstrap.php'; 
  

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $where = [];
    $params = [];

    // === Filters (all POST) ===
    if (!empty($_POST['wo_id'])) {
        $where[] = "wo.wo_id = :wo_id";
        $params['wo_id'] = (int)$_POST['wo_id'];
    }
    $ref_cat_input = $_POST['ref_cat'] ?? '';
    $ref_cat = '';
    
    if ($ref_cat_input === 'complaint') {
        $ref_cat = 'c';
    } elseif ($ref_cat_input === 'plan') {
        $ref_cat = 'p';
    }
    if (!empty($ref_cat)) {
        $where[] = "wo.ref_cat = :ref_cat";
        $params['ref_cat'] = $ref_cat;
    }
    
    if (!empty($_POST['equ_code'])) {
        $equip_id = getCommonId($conn, $_POST['equ_code'], 'product');
        if ($equip_id) {
            $where[] = "c.product_idf = :equ_code";
            $params['equ_code'] = $equip_id;
        }
    }
    

    if (!empty($_POST['ref_no'])) {
        $where[] = "(c.cref_no ILIKE :ref_no OR p.mref_no ILIKE :ref_no)";
        $params['ref_no'] = '%' . $_POST['ref_no'] . '%';
    }
    $rstatus_inp = $_POST['rstatus'] ?? '';
    $rstatus = $statusMap[$rstatus_inp] ?? '';
    if (!empty($rstatus)) {
        $where[] = "wo.wo_status = :rstatus";
        $params['rstatus'] = $rstatus;
    }
    

    if (!empty($_POST['assign_to'])) {
        $assign_id = getCommonId($conn, $_POST['assign_to'], 'assign_to');
        $where[] = "wo.assign_to = :assign_to";
        $params['assign_to'] = $assign_id;
    }

    if (!empty($_POST['from_date']) && !empty($_POST['to_date'])) {
        $where[] = "wo.e_time BETWEEN :from_date AND :to_date";
        $params['from_date'] = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['from_date'])));
        $params['to_date']   = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['to_date'])));
    }
  
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
   // === Query ===
                $sql = "
                SELECT 
                wo.wo_id,
                wo.ref_cat,
                wo.ref_idf,
                c.cref_no AS ref_no,
                asn.dcode AS assign_to,
                asn.dname AS assign_name,
                wo.wo_status,
                wo.attach_det AS attachment,
                wo.desc_prob,
                TO_CHAR(wo.compl_dtime, 'YYYY-MM-DD HH24:MI:SS') AS compl_dtime,
                TO_CHAR(wo.e_time, 'YYYY-MM-DD HH24:MI:SS') AS e_time,
                c.product_idf AS equipment_id,
                eq.dname AS equipment,
                '' AS mtype,
                wo.compl_remarks
            FROM _10009_1pl.msopl_worder wo
            LEFT JOIN _10009_1pl.msopl_complaint c ON wo.ref_idf = c.comp_id
            LEFT JOIN _10009_1pl.common asn ON wo.assign_to = asn.did AND asn.category = 'assign_to'
            LEFT JOIN _10009_1pl.common eq ON c.product_idf = eq.did AND eq.category = 'product'
            $whereSQL
            ORDER BY wo.wo_id DESC

                ";

    $data = $conn->fetchAllAssociative($sql, $params);
 
      
    foreach ($data as &$row) {
        $row['attachment'] = array_map(
            fn($f) => trim($f, '"'),
            explode(',', trim($row['attachment'] ?? '{}', '{}'))
        );
         $row['wo_status']   = $reverseStatusMap[$row['wo_status']] ?? $row['wo_status'];

        $row['ref_cat'] = match ($row['ref_cat']) {
            'c' => 'Complaint',
            'p' => 'Plan',
            default => $row['ref_cat']
        };
    }

    // === If wo_id given → return that record only ===
    if (!empty($_POST['wo_id'])) {
        if (count($data) > 0) {
            echo json_encode(['success' => true, 'data' => $data[0]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found for this Work Order ID']);
        }
    } 
    // === Otherwise return list ===
    else {
        echo json_encode(['success' => true, 'data' => $data]);
    }

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
