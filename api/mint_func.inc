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





/**
 * Normalize date from d/m/Y or Y-m-d into Y-m-d.
 * Returns false on invalid.
 */
function normalizeDate($dateStr) {
    $dateStr = trim($dateStr);
    if ($dateStr === '') return false;

    // Already Y-m-d?
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        $d = DateTime::createFromFormat('Y-m-d', $dateStr);
        return $d ? $d->format('Y-m-d') : false;
    }

    // d/m/Y
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateStr)) {
        $parts = explode('/', $dateStr);
        $d = DateTime::createFromFormat('d/m/Y', $dateStr);
        return $d ? $d->format('Y-m-d') : false;
    }

    // Try a generic parse
    try {
        $d = new DateTime($dateStr);
        return $d->format('Y-m-d');
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Keep parts positions stable by not filtering out empties.
 * Example: '01-13-01-04' => ['', '13', '01', '04'] if exploded with -, but we expect index 0=01, 1=13 etc.
 * We'll explode and pad to at least 4 parts.
 */
function parseFrequency($frequencyCode) {
    $frequencyCode = trim((string)$frequencyCode);

    // if empty, return default daily
    if ($frequencyCode === '') {
        return [
            'freqPeriod' => '11',
            'freqNo' => '',
            'freqWeekday' => null,
            'freqMonth' => null,
            'freqDay' => null,
            'freqWeekNum' => null
        ];
    }

    $parts = explode('-', $frequencyCode);
    // Ensure we have at least 4 parts (pad with empty strings)
    while (count($parts) < 4) $parts[] = '';

    // parts expected: [0]=01, [1]=period, [2]=x, [3]=y
    $period = $parts[1] ?? '';
    $p2 = $parts[2] ?? '';
    $p3 = $parts[3] ?? '';

    $out = [
        'freqPeriod' => $period,
        'freqNo' => '',
        'freqWeekday' => null,
        'freqMonth' => null,
        'freqDay' => null,
        'freqWeekNum' => null
    ];

    switch ($period) {
        case '11': // daily
            $out['freqNo'] = '';
            break;
        case '12': // weekly - p2 is weekday (00..06)
            $out['freqNo'] = ($p2 !== '' ? str_pad((int)$p2, 2, '0', STR_PAD_LEFT) : '01');
            break;
        case '13': // monthly - p2 = occurrence (01,02,03,04,05,99), p3 = weekday (0..6)
        case '14': // quarterly same shape
        case '15': // half-yearly same shape
            // Normalize occurrence to two digits (5 becomes 05)
            $out['freqNo'] = ($p2 !== '' ? str_pad((int)$p2, 2, '0', STR_PAD_LEFT) : '01');
            $out['freqWeekday'] = ($p3 !== '') ? str_pad((int)$p3, 2, '0', STR_PAD_LEFT) : '00';
            break;
        case '16': // yearly - could be date-based or week-based
            // If both numeric and month range 1..12 => date-based
            if (is_numeric($p2) && is_numeric($p3) && (int)$p2 >= 1 && (int)$p2 <= 12) {
                $out['freqNo'] = 'yearly_date';
                $out['freqMonth'] = str_pad((int)$p2, 2, '0', STR_PAD_LEFT);
                $out['freqDay'] = str_pad((int)$p3, 2, '0', STR_PAD_LEFT);
            } elseif (is_numeric($p2) && is_numeric($p3)) {
                // week-based
                $out['freqNo'] = 'yearly_week';
                $out['freqWeekNum'] = $p2;
                $out['freqWeekday'] = $p3;
            } else {
                // fallback
                $out['freqNo'] = 'yearly_date';
                $out['freqMonth'] = '01';
                $out['freqDay'] = '01';
            }
            break;
        default:
            // unknown period -> default to daily
            $out['freqPeriod'] = '11';
            $out['freqNo'] = '';
    }

    return $out;
}

function getWeekdayName($weekdayNum) {
    $weekdayNum = (int)$weekdayNum;
    $weekdays = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    return $weekdays[$weekdayNum] ?? 'monday';
}

/**
 * Calculate the next due date based on normalized Y-m-d $startDate and parsed frequency params.
 * Returns array ['nextDue' => 'Y-m-d'] or ['error'=> '...']
 */
function calculateNextDueDate($startDateYMD, $freqPeriod, $freqNo = '', $freqWeekday = null, $freqMonth = null, $freqDay = null, $freqWeekNum = null) {
    try {
        $start = new DateTime($startDateYMD);
    } catch (Throwable $e) {
        return ['error' => 'Invalid start date'];
    }

    $now = new DateTime('today'); // compare dates only (no time)

    // If start date is in the future, that's the first due date.
    if ($start > $now) {
        return ['nextDue' => $start->format('Y-m-d')];
    }

    $nextDue = clone $start;

    switch ($freqPeriod) {
        case '11': // daily
            while ($nextDue <= $now) {
                $nextDue->modify('+1 day');
            }
            break;

        case '12': // weekly - freqNo is weekday number (0..6)
            $targetWeekday = is_numeric($freqNo) ? (int)$freqNo : 0;
            // find next occurrence after now (we want future)
            // Start from today's date to find next target weekday
            $candidate = clone $now;
            $currentWeekday = (int)$candidate->format('w');
            $delta = ($targetWeekday - $currentWeekday + 7) % 7;
            if ($delta === 0) $delta = 7; // move to next week if same weekday
            $candidate->modify("+{$delta} days");
            $nextDue = $candidate;
            break;

        case '13': // monthly (occurrence & weekday)
        case '14': // quarterly
        case '15': // half-yearly
            $occurrence = $freqNo; // '01','02','03','04' or '99' for last
            $targetWeekday = is_numeric($freqWeekday) ? (int)$freqWeekday : 0;

            // function to find nth/last weekday of a given month/year
$findInMonth = function($year, $month, $occurrence, $weekday) {
    // build a DateTime on first day of that month
    $d = DateTime::createFromFormat('!Y-n-j', "$year-$month-1");
    
    $weekdayName = getWeekdayName($weekday);
    $d->modify("first $weekdayName of this month"); // first occurrence
    
    if ($occurrence === '05' || $occurrence === '5') {
        // For "Last" occurrence, find the last one in the month
        $lastDay = DateTime::createFromFormat('!Y-n-j', "$year-$month-1");
        $lastDay->modify('last day of this month');
        
        // Start from the last day and go backwards to find the last occurrence of the weekday
        $temp = clone $lastDay;
        $temp->modify("last $weekdayName of this month");
        
        // Ensure we found a valid date in this month
        if ((int)$temp->format('n') === (int)$month) {
            $d = $temp;
        } else {
            // If not found (shouldn't happen), fall back to 4th occurrence
            $d->modify('+3 weeks');
        }
    } else {
        $n = max(1, (int)$occurrence);
        if ($n > 1) {
            $d->modify('+' . ($n - 1) . ' weeks');
        }
        // Ensure still in same month
        if ((int)$d->format('n') !== (int)$month) {
            // fallback to last occurrence in the month
            $lastDay = DateTime::createFromFormat('!Y-n-j', "$year-$month-1");
            $lastDay->modify('last day of this month');
            $temp = clone $lastDay;
            $temp->modify("last $weekdayName of this month");
            if ((int)$temp->format('n') === (int)$month) {
                $d = $temp;
            } else {
                $d->modify('-1 week'); // fallback to previous week
            }
        }
    }
    return $d;
};

            // Determine step size in months (1 for monthly, 3 for quarterly, 6 for half-yearly)
            $stepMonths = ($freqPeriod === '13') ? 1 : (($freqPeriod === '14') ? 3 : 6);

            // Start from current month containing $start (if start in past, we want next occurrence after now)
            $candidate = clone $start;

            // Find candidate in the month of start and then iterate forward until > now
            $year = (int)$candidate->format('Y');
            $month = (int)$candidate->format('n');

            // iterate months forward in stepMonths increments
            $loops = 0;
            do {
                $occDate = $findInMonth($year, $month, $occurrence, $targetWeekday);
                if ($occDate > $now) {
                    $nextDue = $occDate;
                    break;
                }
                // move forward
                $month += $stepMonths;
                while ($month > 12) { $month -= 12; $year++; }
                $loops++;
                if ($loops > 120) { // safety guard
                    return ['error' => 'Could not compute next due date for monthly/quarterly/half-yearly frequency'];
                }
            } while (true);

            break;

        case '16': // yearly
            if ($freqNo === 'yearly_date') {
                $m = is_numeric($freqMonth) ? (int)$freqMonth : 1;
                $d = is_numeric($freqDay) ? (int)$freqDay : 1;
                $candidate = DateTime::createFromFormat('!Y-n-j', $now->format('Y') . "-$m-$d");
                if ($candidate <= $now) {
                    $candidate->modify('+1 year');
                }
                $nextDue = $candidate;
            } elseif ($freqNo === 'yearly_week') {
                $targetWeekday = is_numeric($freqWeekday) ? (int)$freqWeekday : 0;
                $targetWeekNum = is_numeric($freqWeekNum) ? (int)$freqWeekNum : 1;

                // Start from this year's week
                $year = (int)$now->format('Y');
                // find the date for weekNum in this year: we take Jan 1 + (weekNum-1)*7 and then adjust to weekday
                $candidate = DateTime::createFromFormat('!Y-n-j', "$year-1-1");
                $candidate->modify('+' . (($targetWeekNum - 1) * 7) . ' days');
                $curWd = (int)$candidate->format('w');
                $delta = ($targetWeekday - $curWd + 7) % 7;
                $candidate->modify("+{$delta} days");
                if ($candidate <= $now) {
                    // next year
                    $year++;
                    $candidate = DateTime::createFromFormat('!Y-n-j', "$year-1-1");
                    $candidate->modify('+' . (($targetWeekNum - 1) * 7) . ' days');
                    $curWd = (int)$candidate->format('w');
                    $delta = ($targetWeekday - $curWd + 7) % 7;
                    $candidate->modify("+{$delta} days");
                }
                $nextDue = $candidate;
            } else {
                // fallback to next day
                $candidate = clone $now;
                $candidate->modify('+1 day');
                $nextDue = $candidate;
            }
            break;

        default:
            return ['error' => 'Invalid frequency period'];
    }

    return ['nextDue' => $nextDue->format('Y-m-d')];
}
function convertToFrequencyCode($frequency) {
    $frequency = trim($frequency);
    
    // If it's already in your format, return as is
    if (preg_match('/^\d{2}-\d{2}/', $frequency)) {
        return $frequency;
    }
    
    // Convert plain text to your frequency code format WITHOUT trailing hyphens
    $lowerFreq = strtolower($frequency);
    
    if ($lowerFreq === 'every day' || $lowerFreq === 'daily') {
        return '01-11';
    }
    elseif ($lowerFreq === 'every week' || $lowerFreq === 'weekly') {
        return '01-12-01'; // Weekly on Monday
    }
    elseif (strpos($lowerFreq, 'every week on sunday') !== false) {
        return '01-12-00'; // Weekly on Sunday
    }
    elseif ($lowerFreq === 'every month' || $lowerFreq === 'monthly') {
        return '01-13-01-01'; // Monthly on 1st Monday
    }
    elseif (strpos($lowerFreq, 'every quarter') !== false && strpos($lowerFreq, 'saturday') !== false) {
        return '01-14-01-06'; // Quarterly on 1st Saturday
    }
    elseif ($lowerFreq === 'every quarter' || $lowerFreq === 'quarterly') {
        return '01-14-01-01'; // Quarterly on 1st Monday
    }
    elseif ($lowerFreq === 'every year' || $lowerFreq === 'yearly') {
        return '01-16-01-01'; // Yearly on Jan 1st
    }
    elseif (strpos($lowerFreq, 'every year on 23 sep') !== false) {
        return '01-16-09-23'; // Yearly on Sep 23rd
    }
    // Handle incomplete codes like "00-16" - convert to proper format
    elseif (preg_match('/^(\d{2})-(\d{2})$/', $frequency, $matches)) {
        $base = $matches[1];
        $period = $matches[2];
        
        switch ($period) {
            case '11': return '01-11';
            case '12': return '01-12-01';
            case '13': return '01-13-01-01';
            case '14': return '01-14-01-01';
            case '15': return '01-15-01-01';
            case '16': return '01-16-01-01';
            default: return '01-11';
        }
    }
    // Handle numeric values (like "11" - assuming days)
    elseif (is_numeric($frequency)) {
        return '01-11';
    }
    
    throw new Exception("Cannot convert frequency to code: " . $frequency);
}


///////////////////////////////calculate next due End//////////////////////////////////////
