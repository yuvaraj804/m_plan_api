<?php
session_start();
require_once 'bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $activePlans = (int)$conn->fetchOne("SELECT COUNT(*) FROM _10009_1pl.msopl_mplan WHERE rstatus = 'act'");
    $openRequests = (int)$conn->fetchOne("SELECT COUNT(*) FROM _10009_1pl.msopl_complaint WHERE comp_status = 'opn'");
    $workOrders = (int)$conn->fetchOne("SELECT COUNT(*) FROM _10009_1pl.msopl_worder WHERE wo_status = 'opn'");
    $completed = (int)$conn->fetchOne("SELECT COUNT(*) FROM _10009_1pl.msopl_worder WHERE wo_status = 'cls'");

    echo json_encode([
        'success' => true,
        'counts' => [
            'activePlans'  => $activePlans,
            'openRequests' => $openRequests,
            'workOrders'   => $workOrders,
            'completed'    => $completed
        ]
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
