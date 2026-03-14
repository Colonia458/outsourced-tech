<?php
// api/test-booking.php - Test Booking Availability System
// Usage: Visit this URL to test booking features

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';

echo "<h1>🧪 Service Booking System Test</h1>";

$results = [];

// Test 1: Check database connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM services");
    $service_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p style='color: green;'>✅ Database connected! Found $service_count services.</p>";
    $results['database'] = 'OK';
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    $results['database'] = 'FAIL';
}

// Test 2: Get a test service
echo "<h2>Test 2: Get Test Service</h2>";
try {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM services LIMIT 1");
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service) {
        echo "<p style='color: green;'>✅ Found service: {$service['name']}</p>";
        echo "<p>Price: KSh " . number_format($service['price']) . "</p>";
        echo "<p>Duration: {$service['duration_minutes']} minutes</p>";
        $results['service'] = $service['id'];
    } else {
        echo "<p style='color: orange;'>⚠️ No services found. Please add a service first.</p>";
        $results['service'] = 'NO_SERVICE';
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    $results['service'] = 'ERROR';
}

// Test 3: Get available slots
echo "<h2>Test 3: Check Available Slots</h2>";
if (!empty($results['service']) && is_numeric($results['service'])) {
    require_once __DIR__ . '/../src/booking.php';
    
    $test_date = date('Y-m-d', strtotime('+1 day')); // Tomorrow
    $slots = get_available_slots($results['service'], $test_date);
    
    if (!empty($slots)) {
        echo "<p style='color: green;'>✅ Found " . count($slots) . " available slots for $test_date</p>";
        echo "<ul>";
        foreach (array_slice($slots, 0, 5) as $slot) {
            echo "<li>{$slot['display']}</li>";
        }
        if (count($slots) > 5) {
            echo "<li>... and " . (count($slots) - 5) . " more</li>";
        }
        echo "</ul>";
        $results['slots'] = 'OK';
    } else {
        echo "<p style='color: orange;'>⚠️ No slots available for $test_date</p>";
        // Try another date
        $test_date2 = date('Y-m-d', strtotime('+2 days'));
        $slots2 = get_available_slots($results['service'], $test_date2);
        if (!empty($slots2)) {
            echo "<p>Found slots for $test_date2</p>";
            $results['slots'] = 'OK';
        } else {
            $results['slots'] = 'NO_SLOTS';
        }
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipped - no service found</p>";
    $results['slots'] = 'SKIPPED';
}

// Test 4: Check if slot is available
echo "<h2>Test 4: Check Specific Slot</h2>";
if (!empty($results['service']) && is_numeric($results['service'])) {
    require_once __DIR__ . '/../src/booking.php';
    
    $test_date = date('Y-m-d', strtotime('+3 days'));
    $available = is_slot_available($results['service'], $test_date, '10:00:00');
    
    if ($available) {
        echo "<p style='color: green;'>✅ Slot 10:00 AM on $test_date is available!</p>";
        $results['check_slot'] = 'OK';
    } else {
        echo "<p style='color: orange;'>⚠️ Slot is not available (may already be booked)</p>";
        $results['check_slot'] = 'BOOKED';
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipped</p>";
    $results['check_slot'] = 'SKIPPED';
}

// Test 5: Generate Google Calendar link
echo "<h2>Test 5: Generate Calendar Links</h2>";
if (!empty($results['service'])) {
    require_once __DIR__ . '/../src/booking.php';
    
    $test_booking = [
        'booking_date' => date('Y-m-d', strtotime('+5 days')),
        'booking_time' => '10:00:00',
        'notes' => 'Test booking from automated system'
    ];
    
    $test_service = [
        'name' => 'Test Service',
        'duration_minutes' => 60
    ];
    
    $google_cal = generate_google_calendar_link($test_booking, $test_service);
    
    echo "<p style='color: green;'>✅ Google Calendar link generated!</p>";
    echo "<p><a href='$google_cal' target='_blank'>📅 Add to Google Calendar</a></p>";
    
    $ical = generate_ical_event($test_booking, $test_service);
    echo "<p style='color: green;'>✅ iCal content generated!</p>";
    echo "<textarea rows='4' style='width: 100%;'>" . htmlspecialchars($ical) . "</textarea>";
    
    $results['calendar'] = 'OK';
} else {
    echo "<p style='color: orange;'>⚠️ Skipped</p>";
    $results['calendar'] = 'SKIPPED';
}

// Test 6: Service Bookings Table
echo "<h2>Test 6: Check Bookings Table</h2>";
try {
    global $pdo;
    $stmt = $pdo->query("DESCRIBE service_bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_cols = ['booking_date', 'booking_time', 'status', 'reminder_sent'];
    $missing = array_diff($required_cols, $columns);
    
    if (empty($missing)) {
        echo "<p style='color: green;'>✅ All required columns exist in service_bookings table</p>";
        $results['table'] = 'OK';
    } else {
        echo "<p style='color: orange;'>⚠️ Missing columns: " . implode(', ', $missing) . "</p>";
        echo "<p>Run: ALTER TABLE service_bookings ADD COLUMN missing_columns...</p>";
        $results['table'] = 'NEED_UPDATE';
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    $results['table'] = 'ERROR';
}

// Summary
echo "<h2>📊 Test Summary</h2>";
echo "<pre>";
print_r($results);
echo "</pre>";

$all_passed = !in_array('FAIL', $results) && !in_array('ERROR', $results);
if ($all_passed && !in_array('NEED_UPDATE', $results)) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px;'>";
    echo "<h3 style='color: #155724; margin: 0;'>🎉 All tests passed!</h3>";
    echo "<p>Booking system is working correctly.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin: 0;'>⚠️ Some tests need attention</h3>";
    echo "<p>Please check the errors above.</p>";
    echo "</div>";
}

echo "<h2>📝 Next Steps</h2>";
echo "<ul>";
echo "<li>Set up cron jobs to process booking reminders</li>";
echo "<li>Test creating a booking through the frontend</li>";
echo "<li>Verify SMS/email notifications work</li>";
echo "</ul>";

echo "<p><a href='../admin/dashboard.php'>← Back to Admin Dashboard</a></p>";
