<?php
/**
 * ============================================================================
 * DASHBOARD STATISTICS HELPER
 * File: include/dashboard_stats.php
 * Purpose: Calculate all dashboard statistics in one place
 * ============================================================================
 */

// Don't include db.php here - it should be included in the main file first

/**
 * Get all dashboard statistics
 * @param mysqli $conn Database connection
 * @return array All statistics
 */
function getDashboardStats($conn) {
    $stats = [];
    
    // ========================================
    // 1. EARNINGS STATISTICS
    // ========================================
    $earningsQuery = "
        SELECT 
            COALESCE(SUM(price_per_day * 30), 0) as estimated_monthly,
            COALESCE(SUM(price_per_day * 7), 0) as estimated_weekly,
            COALESCE(SUM(price_per_day), 0) as estimated_daily
        FROM cars 
        WHERE status = 'approved'
    ";
    $result = $conn->query($earningsQuery);
    
    if ($result === false) {
        $stats['earnings'] = [
            'estimated_monthly' => 0,
            'estimated_weekly' => 0,
            'estimated_daily' => 0
        ];
    } else {
        $stats['earnings'] = $result->fetch_assoc();
    }
    
    // ========================================
    // 2. CARS/BOOKINGS STATISTICS
    // ========================================
    $carsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM cars
    ";
    $result = $conn->query($carsQuery);
    
    if ($result === false) {
        $stats['cars'] = [
            'total' => 0,
            'active' => 0,
            'pending' => 0,
            'rejected' => 0
        ];
    } else {
        $stats['cars'] = $result->fetch_assoc();
    }
    
    // ========================================
    // 3. USERS STATISTICS
    // ========================================
    $usersQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'Owner' THEN 1 ELSE 0 END) as owners,
            SUM(CASE WHEN role = 'Renter' THEN 1 ELSE 0 END) as renters
        FROM users
    ";
    $result = $conn->query($usersQuery);
    
    if ($result === false) {
        $stats['users'] = [
            'total' => 0,
            'owners' => 0,
            'renters' => 0
        ];
    } else {
        $stats['users'] = $result->fetch_assoc();
    }
    
    // ========================================
    // 4. NOTIFICATIONS STATISTICS
    // ========================================
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $notificationsQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN read_status = 'unread' THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN read_status = 'read' THEN 1 ELSE 0 END) as read_count
            FROM notifications
        ";
        $result = $conn->query($notificationsQuery);
        
        if ($result === false) {
            $stats['notifications'] = [
                'total' => 0,
                'unread' => 0,
                'read_count' => 0
            ];
        } else {
            $stats['notifications'] = $result->fetch_assoc();
        }
    } else {
        $stats['notifications'] = [
            'total' => 0,
            'unread' => 0,
            'read_count' => 0
        ];
    }
    
    // ========================================
    // 5. VERIFICATIONS STATISTICS
    // ========================================
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_verifications'");
    
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $verificationsQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM user_verifications
        ";
        $result = $conn->query($verificationsQuery);
        
        if ($result === false) {
            $stats['verifications'] = [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];
        } else {
            $stats['verifications'] = $result->fetch_assoc();
        }
    } else {
        $stats['verifications'] = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
    }
    
    // ========================================
    // 6. MUNICIPALITY DISTRIBUTION
    // ========================================
    $municipalityQuery = "
        SELECT 
            municipality,
            COUNT(*) as user_count
        FROM users
        WHERE municipality IS NOT NULL AND municipality != ''
        GROUP BY municipality
        ORDER BY user_count DESC
        LIMIT 5
    ";
    $result = $conn->query($municipalityQuery);
    
    $stats['municipalities'] = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stats['municipalities'][] = $row;
        }
    }
    
    // ========================================
    // 7. RECENT ACTIVITY
    // ========================================
    $recentCarsResult = $conn->query("
        SELECT COUNT(*) as count 
        FROM cars 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    $stats['recent_cars_count'] = ($recentCarsResult && $recentCarsResult->num_rows > 0) 
        ? $recentCarsResult->fetch_assoc()['count'] 
        : 0;
    
    $recentUsersResult = $conn->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    $stats['recent_users_count'] = ($recentUsersResult && $recentUsersResult->num_rows > 0) 
        ? $recentUsersResult->fetch_assoc()['count'] 
        : 0;
    
    // ========================================
    // 8. GROWTH CALCULATIONS (placeholder)
    // ========================================
    // TODO: Implement actual growth calculations based on historical data
    $stats['growth'] = [
        'earnings' => 12.5,
        'cars' => 8.3,
        'users' => 5.2,
        'notifications' => -2.1 // negative = improvement
    ];
    
    return $stats;
}

/**
 * Get recent cars for dashboard table
 * @param mysqli $conn Database connection
 * @param int $limit Number of cars to fetch
 * @return mysqli_result|false Query result or false on failure
 */
function getRecentCars($conn, $limit = 5) {
    $sql = "
        SELECT cars.*, users.fullname 
        FROM cars 
        JOIN users ON users.id = cars.owner_id 
        ORDER BY cars.created_at DESC 
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        return $conn->query("SELECT * FROM cars WHERE 1=0");
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get bookings statistics (when bookings table exists)
 * @param mysqli $conn Database connection
 * @return array Bookings statistics
 */
function getBookingsStats($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'active' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total_revenue' => 0
        ];
    }
    
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as total_revenue
        FROM bookings
    ";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        return [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'active' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total_revenue' => 0
        ];
    }
    
    return $result->fetch_assoc();
}

/**
 * Get top performing cars
 * @param mysqli $conn Database connection
 * @param int $limit Number of cars to return
 * @return array Top performing cars with booking counts and revenue
 */
function getTopPerformingCars($conn, $limit = 5) {
    // Check required tables
    $bookingsExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    $reviewsExists  = $conn->query("SHOW TABLES LIKE 'reviews'");

    if (!$bookingsExists || $bookingsExists->num_rows == 0) {
        return [];
    }

    if (!$reviewsExists || $reviewsExists->num_rows == 0) {
        return []; // No reviews = no top-rated cars
    }

    $query = "
        SELECT 
            c.id,
            c.brand,
            c.model,
            c.plate_number,
            c.image,
            u.fullname AS owner_name,
            'Car' as vehicle_type,

            COUNT(DISTINCT b.id) AS total_bookings,
            COALESCE(SUM(b.total_amount), 0) AS total_revenue,

            ROUND(AVG(r.rating), 1) AS avg_rating,
            COUNT(r.id) AS total_reviews

        FROM cars c

        LEFT JOIN bookings b 
            ON b.car_id = c.id 
            AND b.status = 'completed'

        INNER JOIN reviews r 
            ON r.car_id = c.id   -- INNER JOIN ensures only rated cars show

        LEFT JOIN users u 
            ON u.id = c.owner_id

        WHERE c.status = 'approved'

        GROUP BY c.id

        HAVING total_reviews > 0

        ORDER BY avg_rating DESC, total_reviews DESC

        LIMIT ?
    ";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        return [];
    }

    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $topCars = [];
    while ($row = $result->fetch_assoc()) {
        $topCars[] = $row;
    }

    return $topCars;
}

/**
 * Get top performing vehicles (Cars + Motorcycles combined)
 * @param mysqli $conn Database connection
 * @param int $limit Number of vehicles to return
 * @return array Top performing vehicles with booking counts and revenue
 */
function getTopPerformingVehicles($conn, $limit = 5) {
    // Check required tables
    $bookingsExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$bookingsExists || $bookingsExists->num_rows == 0) {
        return [];
    }
    
    // Check if reviews table exists
    $reviewsExists = $conn->query("SHOW TABLES LIKE 'reviews'");
    $hasReviews = $reviewsExists && $reviewsExists->num_rows > 0;

    // Get all vehicles (cars + motorcycles) with their performance data
    $vehicles = [];
    
    // Build query based on whether reviews table exists
    if ($hasReviews) {
        // Get top cars WITH reviews
        $carQuery = "
            SELECT 
                c.id,
                c.brand,
                c.model,
                c.plate_number,
                c.image,
                COALESCE(u.fullname, 'Unknown Owner') AS owner_name,
                'Car' as vehicle_type,
                COUNT(DISTINCT b.id) AS total_bookings,
                COALESCE(SUM(b.total_amount), 0) AS total_revenue,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(DISTINCT r.id) AS total_reviews
            FROM cars c
            LEFT JOIN bookings b ON b.car_id = c.id AND b.status = 'completed'
            LEFT JOIN reviews r ON r.car_id = c.id
            LEFT JOIN users u ON u.id = c.owner_id
            WHERE c.status = 'approved'
            GROUP BY c.id, c.brand, c.model, c.plate_number, c.image, u.fullname
            HAVING total_bookings > 0 OR total_reviews > 0
            ORDER BY total_revenue DESC, total_bookings DESC
        ";
    } else {
        // Get top cars WITHOUT reviews table
        $carQuery = "
            SELECT 
                c.id,
                c.brand,
                c.model,
                c.plate_number,
                c.image,
                COALESCE(u.fullname, 'Unknown Owner') AS owner_name,
                'Car' as vehicle_type,
                COUNT(DISTINCT b.id) AS total_bookings,
                COALESCE(SUM(b.total_amount), 0) AS total_revenue,
                0 AS avg_rating,
                0 AS total_reviews
            FROM cars c
            LEFT JOIN bookings b ON b.car_id = c.id AND b.status = 'completed'
            LEFT JOIN users u ON u.id = c.owner_id
            WHERE c.status = 'approved'
            GROUP BY c.id, c.brand, c.model, c.plate_number, c.image, u.fullname
            HAVING total_bookings > 0
            ORDER BY total_revenue DESC, total_bookings DESC
        ";
    }
    
    $carResult = $conn->query($carQuery);
    if ($carResult) {
        while ($row = $carResult->fetch_assoc()) {
            $vehicles[] = $row;
        }
    } else {
        error_log("getTopPerformingVehicles car query failed: " . $conn->error);
    }
    
    // Check if motorcycle_id column exists in bookings table
    $columnsResult = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
    $hasMotorcycleColumn = $columnsResult && $columnsResult->num_rows > 0;
    
    // Check if motorcycles table exists
    $motorcyclesExists = $conn->query("SHOW TABLES LIKE 'motorcycles'");
    $hasMotorcycles = $motorcyclesExists && $motorcyclesExists->num_rows > 0;
    
    if ($hasMotorcycleColumn && $hasMotorcycles) {
        // Build motorcycle query based on whether reviews table exists
        if ($hasReviews) {
            // Get top motorcycles WITH reviews
            $motorcycleQuery = "
                SELECT 
                    m.id,
                    m.brand,
                    m.model,
                    m.plate_number,
                    m.image,
                    COALESCE(u.fullname, 'Unknown Owner') AS owner_name,
                    'Motorcycle' as vehicle_type,
                    COUNT(DISTINCT b.id) AS total_bookings,
                    COALESCE(SUM(b.total_amount), 0) AS total_revenue,
                    ROUND(AVG(r.rating), 1) AS avg_rating,
                    COUNT(DISTINCT r.id) AS total_reviews
                FROM motorcycles m
                LEFT JOIN bookings b ON b.motorcycle_id = m.id AND b.status = 'completed'
                LEFT JOIN reviews r ON r.motorcycle_id = m.id
                LEFT JOIN users u ON u.id = m.owner_id
                WHERE m.status = 'approved'
                GROUP BY m.id, m.brand, m.model, m.plate_number, m.image, u.fullname
                HAVING total_bookings > 0 OR total_reviews > 0
                ORDER BY total_revenue DESC, total_bookings DESC
            ";
        } else {
            // Get top motorcycles WITHOUT reviews table
            $motorcycleQuery = "
                SELECT 
                    m.id,
                    m.brand,
                    m.model,
                    m.plate_number,
                    m.image,
                    COALESCE(u.fullname, 'Unknown Owner') AS owner_name,
                    'Motorcycle' as vehicle_type,
                    COUNT(DISTINCT b.id) AS total_bookings,
                    COALESCE(SUM(b.total_amount), 0) AS total_revenue,
                    0 AS avg_rating,
                    0 AS total_reviews
                FROM motorcycles m
                LEFT JOIN bookings b ON b.motorcycle_id = m.id AND b.status = 'completed'
                LEFT JOIN users u ON u.id = m.owner_id
                WHERE m.status = 'approved'
                GROUP BY m.id, m.brand, m.model, m.plate_number, m.image, u.fullname
                HAVING total_bookings > 0
                ORDER BY total_revenue DESC, total_bookings DESC
            ";
        }
        
        $motorcycleResult = $conn->query($motorcycleQuery);
        if ($motorcycleResult) {
            while ($row = $motorcycleResult->fetch_assoc()) {
                $vehicles[] = $row;
            }
        } else {
            error_log("getTopPerformingVehicles motorcycle query failed: " . $conn->error);
        }
    }
    
    // Sort by rating, then revenue, then bookings
    if (!empty($vehicles)) {
        usort($vehicles, function($a, $b) {
            if ($a['avg_rating'] != $b['avg_rating']) {
                return $b['avg_rating'] <=> $a['avg_rating'];
            }
            if ($a['total_revenue'] != $b['total_revenue']) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            }
            return $b['total_bookings'] <=> $a['total_bookings'];
        });
    }
    
    // Return top N vehicles
    return array_slice($vehicles, 0, $limit);
}


/**
 * Get revenue by time period
 * @param mysqli $conn Database connection
 * @return array Revenue breakdown by different time periods
 */
function getRevenueByPeriod($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return [
            'today' => 0,
            'this_week' => 0,
            'this_month' => 0,
            'this_year' => 0,
            'all_time' => 0
        ];
    }
    
    $query = "
        SELECT 
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END), 0) as today,
            COALESCE(SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(NOW()) THEN total_amount ELSE 0 END), 0) as this_week,
            COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) THEN total_amount ELSE 0 END), 0) as this_month,
            COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(NOW()) THEN total_amount ELSE 0 END), 0) as this_year,
            COALESCE(SUM(total_amount), 0) as all_time
        FROM bookings
        WHERE status = 'completed'
    ";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        return [
            'today' => 0,
            'this_week' => 0,
            'this_month' => 0,
            'this_year' => 0,
            'all_time' => 0
        ];
    }
    
    return $result->fetch_assoc();
}

/**
 * Get recent bookings (Cars + Motorcycles)
 * @param mysqli $conn Database connection
 * @param int $limit Number of bookings to fetch
 * @return array Recent bookings with vehicle and user details
 */
function getRecentBookings($conn, $limit = 5) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return [];
    }
    
    // Check if motorcycle_id column exists in bookings table
    $columnsResult = $conn->query("SHOW COLUMNS FROM bookings LIKE 'motorcycle_id'");
    $hasMotorcycleColumn = $columnsResult && $columnsResult->num_rows > 0;
    
    // Check if motorcycles table exists
    $motorcyclesExists = $conn->query("SHOW TABLES LIKE 'motorcycles'");
    $hasMotorcycles = $motorcyclesExists && $motorcyclesExists->num_rows > 0;
    
    if ($hasMotorcycleColumn && $hasMotorcycles) {
        // Query with motorcycles support
        $query = "
            SELECT 
                b.*,
                COALESCE(c.brand, m.brand, 'Unknown') as brand,
                COALESCE(c.model, m.model, 'Vehicle') as model,
                COALESCE(c.plate_number, m.plate_number, 'N/A') as plate_number,
                CASE 
                    WHEN b.motorcycle_id IS NOT NULL AND m.id IS NOT NULL THEN 'Motorcycle'
                    WHEN b.car_id IS NOT NULL AND c.id IS NOT NULL THEN 'Car'
                    WHEN b.motorcycle_id IS NOT NULL THEN 'Motorcycle'
                    ELSE 'Car'
                END as vehicle_type,
                COALESCE(u.fullname, 'Unknown User') as renter_name,
                COALESCE(o1.fullname, o2.fullname, 'Unknown Owner') as owner_name,
                CASE 
                    WHEN (b.car_id IS NOT NULL AND c.id IS NULL) OR (b.motorcycle_id IS NOT NULL AND m.id IS NULL) THEN 1
                    ELSE 0
                END as vehicle_deleted
            FROM bookings b
            LEFT JOIN cars c ON c.id = b.car_id
            LEFT JOIN motorcycles m ON m.id = b.motorcycle_id
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN users o1 ON o1.id = c.owner_id
            LEFT JOIN users o2 ON o2.id = m.owner_id
            ORDER BY b.created_at DESC
            LIMIT ?
        ";
    } else {
        // Fallback query without motorcycles
        $query = "
            SELECT 
                b.*,
                COALESCE(c.brand, 'Unknown') as brand,
                COALESCE(c.model, 'Vehicle') as model,
                COALESCE(c.plate_number, 'N/A') as plate_number,
                'Car' as vehicle_type,
                COALESCE(u.fullname, 'Unknown User') as renter_name,
                COALESCE(o.fullname, 'Unknown Owner') as owner_name,
                CASE 
                    WHEN b.car_id IS NOT NULL AND c.id IS NULL THEN 1
                    ELSE 0
                END as vehicle_deleted
            FROM bookings b
            LEFT JOIN cars c ON c.id = b.car_id
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN users o ON o.id = c.owner_id
            ORDER BY b.created_at DESC
            LIMIT ?
        ";
    }
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        // If prepare fails, log error and return empty
        error_log("getRecentBookings prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $limit);
    if (!$stmt->execute()) {
        error_log("getRecentBookings execute failed: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    return $bookings;
}

/**
 * Get average booking value
 * @param mysqli $conn Database connection
 * @return float Average booking value
 */
function getAverageBookingValue($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return 0;
    }
    
    $query = "
        SELECT AVG(total_amount) as avg_value
        FROM bookings
        WHERE status IN ('completed', 'active')
    ";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        return 0;
    }
    
    $data = $result->fetch_assoc();
    return $data['avg_value'] ?? 0;
}

/**
 * Get car utilization rate
 * @param mysqli $conn Database connection
 * @return float Utilization rate percentage
 */
function getCarUtilizationRate($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return 0;
    }
    
    // Get total active cars
    $carsResult = $conn->query("SELECT COUNT(*) as total FROM cars WHERE status = 'approved'");
    $totalCars = $carsResult ? $carsResult->fetch_assoc()['total'] : 0;
    
    if ($totalCars == 0) {
        return 0;
    }
    
    // Get cars with active or recent bookings (last 30 days)
    $query = "
        SELECT COUNT(DISTINCT car_id) as utilized_cars
        FROM bookings
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        return 0;
    }
    
    $data = $result->fetch_assoc();
    $utilizedCars = $data['utilized_cars'] ?? 0;
    
    return ($utilizedCars / $totalCars) * 100;
}

/**
 * Get booking cancellation rate
 * @param mysqli $conn Database connection
 * @return float Cancellation rate percentage
 */
function getCancellationRate($conn) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if (!$tableExists || $tableExists->num_rows == 0) {
        return 0;
    }
    
    $query = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
        FROM bookings
    ";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        return 0;
    }
    
    $data = $result->fetch_assoc();
    $total = $data['total_bookings'] ?? 0;
    $cancelled = $data['cancelled_bookings'] ?? 0;
    
    if ($total == 0) {
        return 0;
    }
    
    return ($cancelled / $total) * 100;
}

/**
 * Format currency for display
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Format number with proper thousands separator
 * @param int $number Number to format
 * @return string Formatted number
 */
function formatNumber($number) {
    return number_format($number);
}

/**
 * Calculate percentage with proper formatting
 * @param float $value Current value
 * @param float $oldValue Previous value
 * @return array ['percentage' => float, 'direction' => 'up'|'down']
 */
function calculateGrowth($value, $oldValue) {
    if ($oldValue == 0) {
        return ['percentage' => 0, 'direction' => 'neutral'];
    }
    
    $percentage = (($value - $oldValue) / $oldValue) * 100;
    
    return [
        'percentage' => round(abs($percentage), 1),
        'direction' => $percentage >= 0 ? 'up' : 'down',
        'is_positive' => $percentage >= 0
    ];
}

/**
 * Get status badge class
 * @param string $status Status value
 * @return string CSS class
 */
function getStatusClass($status) {
    $classes = [
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'active' => 'info'
    ];
    
    return $classes[$status] ?? 'secondary';
}

/**
 * Get system health status
 * @param mysqli $conn Database connection
 * @return array System health information
 */
function getSystemHealth($conn) {
    $tablesResult = $conn->query("SHOW TABLES");
    $tableCount = ($tablesResult) ? $tablesResult->num_rows : 0;
    
    return [
        'database_status' => $conn->ping() ? 'online' : 'offline',
        'total_tables' => $tableCount,
        'last_backup' => null, // Implement if you have backup system
        'storage_used' => null // Implement if needed
    ];
}
?>