<?php

 // --- Helper function to resolve did from common ---
 function getCommonId($conn, $dcode, $category) {
    $sql = "
        SELECT did 
        FROM _10009_1pl.common 
        WHERE dcode= :dcode
          AND TRIM(category) = :category
        LIMIT 1
    ";
    return $conn->fetchOne($sql, [
        'dcode' => $dcode,
        'category' => $category
    ]);
}

function json_response($success, $data = null, $error = null, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error
    ]);
    exit;
}

$statusMap = [
    'Open'        => 'opn',
    'Closed'       => 'cls',
    'In Progress' => 'ini',
    'On Hold'     => 'hol'
  ];
$reverseStatusMap = array_flip($statusMap);

  // 🔁 Status label mapping
$statusLabels = [ 
    'act' => 'Open', 'can' => 'Cancelled', 'com' => 'Completed',
    'del' => 'Deleted', 'log' => 'Logged', 'sus' => 'Suspended',
    'rev' => 'Revised', 'iac' => 'Inactive', 'pro' => 'In Progress',
    'obs' => 'Obsolete', 'pre' => 'Prepared', 'sac' => 'Sanctioned',
    'shp' => 'Shipped'
];
$reverseStatusLabels = array_flip($statusLabels);


//      ============================ Next Due Date ============================

function calculateNextDueDate($startDate, $freqPeriod, $freqNo, $freqWeekday = null, $freqMonth = null, $freqDay = null, $freqWeekNum = null) {
    $start = new DateTime($startDate);
    $now = new DateTime();
    
    // If start date is in future, use start date as first due date
    if ($start > $now) {
        return ['nextDue' =>$start->format('Y-m-d')];
    }
    
    $nextDue = clone $start;
    
    switch ($freqPeriod) {
        case '11': // Daily
            while ($nextDue <= $now) {
                $nextDue->modify('+1 day');
            }
            break;
            
        case '12': // Weekly
            $targetWeekday = (int)$freqNo;
            $currentWeekday = (int)$start->format('w');
            
            // Find next occurrence of the target weekday
            $daysToAdd = ($targetWeekday - $currentWeekday + 7) % 7;
            $daysToAdd = $daysToAdd === 0 ? 7 : $daysToAdd; // If same day, go to next week
            
            $nextDue->modify("+{$daysToAdd} days");
            
            // If we're still in the past, add weeks
            while ($nextDue <= $now) {
                $nextDue->modify('+1 week');
            }
            break;
            
       case '13': // Monthly
    $occurrence = $freqNo; // 1st, 2nd, 3rd, 4th, last
    $targetWeekday = (int)$freqWeekday;

    if (empty($occurrence)) {
        return ['error' => 'Missing occurrence value'];
    }
    if (!is_numeric($targetWeekday)) {
        return ['error' => 'Missing weekday value'];
    }

    $weekdayName = getWeekdayName($targetWeekday);

    while (true) {
        // Start from first day of current month
        $nextDue->modify('first day of this month');

        if ($occurrence === 'last') {
            // Last weekday of month
            $nextDue->modify("last $weekdayName of this month");
        } else {
            // Nth occurrence (1st, 2nd, 3rd, 4th)
            $nextDue->modify("first $weekdayName of this month");
            $nextDue->modify('+' . ($occurrence - 1) . ' weeks');
        }

        // If nextDue is still before now → move to next month
        if ($nextDue > $now) break;
        $nextDue->modify('first day of next month');
    }

    break;

            
        case '14': // Quarterly (every 3 months)
            $occurrence = $freqNo;
            $targetWeekday = (int)$freqWeekday;
            
            // Determine current quarter and start month
            $currentMonth = (int)$nextDue->format('n');
            $quarterStartMonth = ((ceil($currentMonth / 3) - 1) * 3) + 1;
            $nextDue->setDate($nextDue->format('Y'), $quarterStartMonth, 1);
            
            // Calculate the target day for this quarter
            calculateMonthlyOccurrence($nextDue, $occurrence, $targetWeekday);
            
            // If in past, move to next quarter
            while ($nextDue <= $now) {
                $nextDue->modify('+3 months');
                $nextDue->setDate($nextDue->format('Y'), $nextDue->format('n'), 1);
                calculateMonthlyOccurrence($nextDue, $occurrence, $targetWeekday);
            }
            break;
            
        case '15': // Half-Yearly (every 6 months)
            $occurrence = $freqNo;
            $targetWeekday = (int)$freqWeekday;
            
            // Determine current half-year and start month
            $currentMonth = (int)$nextDue->format('n');
            $halfYearStartMonth = $currentMonth <= 6 ? 1 : 7;
            $nextDue->setDate($nextDue->format('Y'), $halfYearStartMonth, 1);
            
            // Calculate the target day for this half-year
            calculateMonthlyOccurrence($nextDue, $occurrence, $targetWeekday);
            
            // If in past, move to next half-year
            while ($nextDue <= $now) {
                $nextDue->modify('+6 months');
                $nextDue->setDate($nextDue->format('Y'), $nextDue->format('n'), 1);
                calculateMonthlyOccurrence($nextDue, $occurrence, $targetWeekday);
            }
            break;
            
        case '16': // Yearly
            if ($freqNo === 'yearly_date') {
                // Specific date each year
                $nextDue->setDate($nextDue->format('Y'), (int)$freqMonth, (int)$freqDay);
                
                // If this year's date is past, move to next year
                if ($nextDue <= $now) {
                    $nextDue->modify('+1 year');
                    $nextDue->setDate($nextDue->format('Y'), (int)$freqMonth, (int)$freqDay);
                }
            } else if ($freqNo === 'yearly_week') {
                // Nth week and specific weekday
                $targetWeekday = (int)$freqWeekday;
                $targetWeekNum = (int)$freqWeekNum;
                
                // Set to first day of the year
                $nextDue->setDate($nextDue->format('Y'), 1, 1);
                
                // Move to the target week (week numbers start from 1)
                $weekOffset = ($targetWeekNum - 1) * 7;
                $nextDue->modify('+' . $weekOffset . ' days');
                
                // Move to the target weekday within that week
                $currentWeekday = (int)$nextDue->format('w');
                $daysToAdd = ($targetWeekday - $currentWeekday + 7) % 7;
                $nextDue->modify("+{$daysToAdd} days");
                
                // If in past, move to next year
                if ($nextDue <= $now) {
                    $nextDue->modify('+1 year');
                    $nextDue->setDate($nextDue->format('Y'), 1, 1);
                    $nextDue->modify('+' . $weekOffset . ' days');
                    $currentWeekday = (int)$nextDue->format('w');
                    $daysToAdd = ($targetWeekday - $currentWeekday + 7) % 7;
                    $nextDue->modify("+{$daysToAdd} days");
                }
            }
            break;
            
        default:
            return ['error' => 'Invalid frequency period'];
    }
    
    return ['nextDue' => $nextDue->format('Y-m-d'),];
}

// Helper function to calculate monthly occurrences
function calculateMonthlyOccurrence(&$date, $occurrence, $targetWeekday) {
    $weekdayName = getWeekdayName($targetWeekday);
    
    if ($occurrence === 'last') {
        // Last specific weekday of the month
        $date->modify('last day of this month');
        $currentWeekday = (int)$date->format('w');
        $daysToSubtract = ($currentWeekday - $targetWeekday + 7) % 7;
        if ($daysToSubtract > 0) {
            $date->modify("-$daysToSubtract days");
        }
    } else {
        // Nth occurrence (1st, 2nd, 3rd, 4th)
        $date->modify("first $weekdayName of this month");
        
        if ($occurrence > 1) {
            $date->modify('+' . ($occurrence - 1) . ' weeks');
        }
        
        // Ensure we're still in the same month
        $currentMonth = (int)$date->format('n');
        $testDate = clone $date;
        $testDate->modify("first $weekdayName of this month");
        $testDate->modify('+' . ($occurrence - 1) . ' weeks');
        
        if ((int)$testDate->format('n') === $currentMonth) {
            $date = $testDate;
        } else {
            // If beyond current month, use last occurrence
            $date->modify('last ' . $weekdayName . ' of this month');
        }
    }
}

function getWeekdayName($weekdayNum) {
    $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    return $weekdays[$weekdayNum] ?? 'monday';
}

// calc due date usage
// try {
//     $startDate = $_POST['startDate'] ?? '';
//     $freqPeriod = $_POST['freqPeriod'] ?? '';
    
//     if (!$startDate || !$freqPeriod) {
//         echo json_encode(['error' => 'Start date and frequency period are required']);
//         exit;
//     }
    
//     // Validate date format
//     if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
//         echo json_encode(['error' => 'Invalid start date format']);
//         exit;
//     }
    
//     $result = calculateNextDueDate(
//         $startDate,
//         $freqPeriod,
//         $_POST['freqNo'] ?? '',
//         $_POST['freqWeekday'] ?? null,
//         $_POST['freqMonth'] ?? null,
//         $_POST['freqDay'] ?? null,
//         $_POST['freqWeekNum'] ?? null
//     );
    
   
//     echo json_encode($result);
    
// } catch (Exception $e) {
//     print_r("Error in calculate_next_due.php: " . $e->getMessage());
//     echo json_encode(['error' => 'Error calculating next due date: ' . $e->getMessage()]);
// }