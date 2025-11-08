<?php
  require_once 'bootstrap.php'; 
  // defines $entityManager
  session_start();
  $guser_id = $_SESSION['guser_id'] ?? '';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    
    $sql = "SELECT dcode, dname, TRIM(category) AS category FROM _10009_1pl.common ORDER BY category, dname";
    $rows = $conn->fetchAllAssociative($sql);
    
    $grouped = [];
    
    foreach ($rows as $row) {
        $category = strtolower(trim($row['category']));
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = [
            'code' => $row['dcode'],
            'name' => $row['dname']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $grouped
    ]);
    
} catch (\Exception $e) {
    print_r('DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch data.']);
}
