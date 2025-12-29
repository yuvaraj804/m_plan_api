<?php
include 'mint_func.inc';

//  require_once '/var/www/extreme.minervaerp.com/public_html/minerva_erp_v14_dev/includes/doc_dbal_config.php';
 require_once 'bootstrap.php'; 

// ini_set('display_errors', 1);
// error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
$action = $_POST['action']??'';

try {
    // ===============================Single Data================================ 
    $comp_id = $_POST['comp_id'] ?? '';

    $cref_no = $_POST['request_id']??$_POST['ref_no']??'';
    if (empty($comp_id) && !empty($cref_no)) {
        // Convert cref_no to comp_id
        $comp_id = $conn->fetchOne("SELECT comp_id FROM _10009_1pl.msopl_complaint WHERE cref_no = ?", [$cref_no]);
    }
    
    
    // Proceed with query using $comp_id...

    if (!empty($comp_id)&& $action!=='view') {
        $sql = "
            SELECT 
                c.comp_id,
                c.cref_no,
                c.desc_prob,
                b.dcode AS branch,
                p.dcode AS product,
                pr.dcode AS priority,
                c.comp_status AS status,
                c.attach_det AS attachment,
                TO_CHAR(c.e_time, 'YYYY-MM-DD HH24:MI:SS') AS e_time
            FROM _10009_1pl.msopl_complaint c
            LEFT JOIN _10009_1pl.common b 
                ON b.did = c.branch_idf AND b.category = 'branch'
            LEFT JOIN _10009_1pl.common p 
                ON p.did = c.product_idf AND p.category = 'product'
            LEFT JOIN _10009_1pl.common pr 
            ON pr.did = c.pref_idf AND pr.category = 'pref'

            WHERE c.comp_id = :comp_id
            LIMIT 1
        ";
        $data = $conn->fetchAssociative($sql, ['comp_id' => $comp_id]);
        if ($data) {
            $reverseStatusMap = array_flip($statusMap);
            $data['status'] = $reverseStatusMap[$data['status']] ?? $data['comp_status'];

            // $data['attachment'] = array_filter(
            //     explode(',', trim($data['attachment'], '{}'))
            // );
            if (!empty($data['attachment'])) {
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
            
        }

        echo json_encode(['success' => true, 'data' => $data]);
    }
    // ===============================All Data with filter================================ 
    else {
        try {
            $where = [];
            $params = [];

            // 🔹 Complaint Filter (comp_id)
            if (!empty($_POST['comp_id'])) {
                $where[] = "c.comp_id = :comp_id";
                $params['comp_id'] = $_POST['comp_id'];
            }

            // 🔹 Branch Filter
            if (!empty($_POST['branch_code'])) {
                $branch_id = getCommonId($conn, $_POST['branch_code'], 'branch');
                if ($branch_id) {
                    $where[] = "c.branch_idf = :branch_id";
                    $params['branch_id'] = $branch_id;
                }
            }

            // 🔹 Equipment Filter
            if (!empty($_POST['equ_code'])) {
                $product_id = getCommonId($conn, $_POST['equ_code'], 'product');
                if ($product_id) {
                    $where[] = "c.product_idf = :product_id";
                    $params['product_id'] = $product_id;
                }
            }

            // 🔹 Maintenance Type Filter
            if (!empty($_POST['mt_type'])) {
                $mtype_id = getCommonId($conn, $_POST['mt_type'], 'mtype');
                if ($mtype_id) {
                    $where[] = "c.mtype_idf = :mtype_id";
                    $params['mtype_id'] = $mtype_id;
                }
            }

         // 🔹 Status Filter (including 'unas' = unassigned)
                if (!empty($_POST['rstatus'])) {
                    if ($_POST['rstatus'] === 'unas') {
                        // Show complaints NOT linked to any work order
                        $where[] = "c.comp_id NOT IN (
                            SELECT wo.ref_idf 
                            FROM _10009_1pl.msopl_worder wo 
                            WHERE wo.ref_cat = 'c'
                        )";
                    } else {
                        $dbStatus = $statusMap[$_POST['rstatus']] ?? null;
                        if ($dbStatus) {
                            $where[] = "c.comp_status = :status";
                            $params['status'] = $dbStatus;
                        }
                    }
                }



            // 🔹 Date Range Filter (by created time)
            if (!empty($_POST['from_date']) && !empty($_POST['to_date'])) {
                $where[] = "c.e_time BETWEEN :from_date AND :to_date";
                $params['from_date'] = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['from_date'])));
                $params['to_date']   = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['to_date'])));
            }


            // --- Build WHERE SQL ---
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // --- Main Query ---
            $sql = "
                SELECT 
                    c.comp_id,
                    c.cref_no, 
                    c.desc_prob,
                    b.dname AS branch, 
                    p.dname AS product,
                    pr.dname AS priority,
                    c.comp_status, 
                    c.attach_det AS attachment,
                    TO_CHAR(c.e_time, 'DD/MM/YYYY HH24:MI:SS') AS e_time,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM _10009_1pl.msopl_worder wo 
                            WHERE wo.ref_idf = c.comp_id AND wo.ref_cat = 'c'
                        )
                        THEN TRUE 
                        ELSE FALSE 
                    END AS is_assigned
                FROM _10009_1pl.msopl_complaint c
                LEFT JOIN _10009_1pl.common b  ON c.branch_idf  = b.did
                LEFT JOIN _10009_1pl.common p  ON c.product_idf = p.did 
                LEFT JOIN _10009_1pl.common pr ON c.pref_idf    = pr.did 
                $whereSQL
                ORDER BY c.comp_id DESC
            ";

            $data = $conn->fetchAllAssociative($sql, $params);

            foreach ($data as &$row) {
                $code = $row['comp_status'] ?? '';
                $row['comp_status'] = $reverseStatusMap[$code] ?? $code;
            }

            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            print_r('POST complaint report error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch complaint report data.'
            ]);
        }
        exit;
    }
} catch (\Exception $e) {
    print_r('POST mp_request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch maintenance request.']);
}
exit;
