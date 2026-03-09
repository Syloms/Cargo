<?php
session_start();
require_once 'include/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['fullname'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - CarGo Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="include/admin-styles.css" rel="stylesheet">
    <link href="include/notifications.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8f9fb;
            color: #1f2937;
            overflow-x: hidden;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 0;
            min-height: 100vh;
            background: #f8f9fb;
        }
        
        /* Top Header */
        .calendar-top-bar {
            background: #ffffff;
            padding: 1.5rem 2.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .date-range-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f9fafb;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .nav-arrow {
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-arrow:hover {
            background: #e5e7eb;
            color: #1f2937;
        }
        
        .current-date-range {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
            min-width: 200px;
            text-align: center;
        }
        
        .view-toggles {
            display: flex;
            gap: 0.5rem;
            background: #f9fafb;
            padding: 0.25rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .view-toggle {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .view-toggle.active {
            background: #ffffff;
            color: #1f2937;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .top-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding: 0.625rem 0.875rem 0.625rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
            font-size: 0.875rem;
            width: 280px;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.875rem;
        }
        
        .today-btn {
            padding: 0.625rem 1.25rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .today-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .add-event-btn {
            padding: 0.625rem 1.25rem;
            background: #6366f1;
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .add-event-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        /* Week View Container */
        .week-view-container {
            padding: 2rem 2.5rem;
        }
        
        .week-header {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            margin-bottom: 1px;
        }
        
        .time-column-header {
            background: #ffffff;
            padding: 1rem;
        }
        
        .day-header {
            background: #ffffff;
            padding: 1rem;
            text-align: center;
        }
        
        .day-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .day-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .day-header.today {
            background: #6366f1;
        }
        
        .day-header.today .day-name {
            color: rgba(255,255,255,0.8);
        }
        
        .day-header.today .day-number {
            color: #ffffff;
        }
        
        /* Timeline Grid */
        .timeline-grid {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            position: relative;
        }
        
        .time-slot {
            background: #ffffff;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 500;
            height: 80px;
            display: flex;
            align-items: flex-start;
        }
        
        .day-column {
            background: #ffffff;
            position: relative;
            min-height: 80px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .day-column:hover {
            background: #f9fafb;
        }
        
        /* Event Cards */
        .event-card {
            position: absolute;
            left: 4px;
            right: 4px;
            padding: 0.75rem;
            border-radius: 10px;
            border-left: 3px solid;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
            z-index: 1;
        }
        
        .event-card:hover {
            transform: translateX(2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10;
        }
        
        .event-card.booking {
            border-left-color: #10b981;
            background: #ecfdf5;
        }
        
        .event-card.payment {
            border-left-color: #3b82f6;
            background: #eff6ff;
        }
        
        .event-card.verification {
            border-left-color: #8b5cf6;
            background: #f5f3ff;
        }
        
        .event-card.vehicle {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        
        .event-card.report {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        
        .event-card.refund {
            border-left-color: #ec4899;
            background: #fdf2f8;
        }
        
        .event-time {
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .event-card.booking .event-time { color: #059669; }
        .event-card.payment .event-time { color: #2563eb; }
        .event-card.verification .event-time { color: #7c3aed; }
        .event-card.vehicle .event-time { color: #d97706; }
        .event-card.report .event-time { color: #dc2626; }
        .event-card.refund .event-time { color: #db2777; }
        
        .event-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .event-participants {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .participant-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            margin-left: -6px;
            object-fit: cover;
        }
        
        .participant-avatar:first-child {
            margin-left: 0;
        }
        
        .participant-more {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #e5e7eb;
            border: 2px solid #ffffff;
            margin-left: -6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 600;
            color: #6b7280;
        }
        
        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }
        
        .stat-icon.booking { background: #ecfdf5; color: #10b981; }
        .stat-icon.payment { background: #eff6ff; color: #3b82f6; }
        .stat-icon.verification { background: #f5f3ff; color: #8b5cf6; }
        .stat-icon.vehicle { background: #fffbeb; color: #f59e0b; }
        .stat-icon.report { background: #fef2f2; color: #ef4444; }
        .stat-icon.refund { background: #fdf2f8; color: #ec4899; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Event Detail Modal */
        .event-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 9999;
            padding: 2rem;
            overflow-y: auto;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: #ffffff;
            max-width: 600px;
            margin: 0 auto;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .modal-close {
            background: #f3f4f6;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: #6b7280;
        }
        
        .modal-close:hover {
            background: #e5e7eb;
            color: #1f2937;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .detail-row {
            margin-bottom: 1.5rem;
        }
        
        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 500;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .week-header,
            .timeline-grid {
                grid-template-columns: 60px repeat(7, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
            
            .calendar-top-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .calendar-nav {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-box input {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .week-header,
            .timeline-grid {
                grid-template-columns: 50px repeat(3, 1fr);
            }
            
            .day-header:nth-child(n+5),
            .day-column:nth-child(n+5) {
                display: none;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="calendar-top-bar">
            <div class="calendar-nav">
                <div class="date-range-selector">
                    <button class="nav-arrow" onclick="changeWeek(-1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="current-date-range" id="dateRange">May 21 - 26, 2025</div>
                    <button class="nav-arrow" onclick="changeWeek(1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                
                <div class="view-toggles">
                    <button class="view-toggle" onclick="setView('year')">Year</button>
                    <button class="view-toggle active" onclick="setView('week')">Week</button>
                    <button class="view-toggle" onclick="setView('month')">Month</button>
                    <button class="view-toggle" onclick="setView('day')">Day</button>
                </div>
            </div>
            
            <div class="top-actions">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search..." id="searchInput" onkeyup="handleSearch(event)">
                </div>
                <button class="today-btn" onclick="goToToday()">Today</button>
                <button class="add-event-btn">
                    <i class="bi bi-plus-lg"></i>
                    Add Event
                </button>
            </div>
        </div>
        
        <div class="week-view-container">
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="stat-bookings">0</div>
                            <div class="stat-label">Bookings</div>
                        </div>
                        <div class="stat-icon booking">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="stat-payments">0</div>
                            <div class="stat-label">Payments</div>
                        </div>
                        <div class="stat-icon payment">
                            <i class="bi bi-credit-card"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="stat-verifications">0</div>
                            <div class="stat-label">Verifications</div>
                        </div>
                        <div class="stat-icon verification">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="stat-vehicles">0</div>
                            <div class="stat-label">Vehicles</div>
                        </div>
                        <div class="stat-icon vehicle">
                            <i class="bi bi-car-front"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Week Header -->
            <div class="week-header" id="weekHeader">
                <div class="time-column-header"></div>
                <!-- Days will be inserted here -->
            </div>
            
            <!-- Timeline Grid -->
            <div class="timeline-grid" id="timelineGrid">
                <!-- Time slots and events will be inserted here -->
            </div>
        </div>
    </div>
    
    <!-- Event Detail Modal -->
    <div class="event-modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Event Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Event details will be inserted here -->
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="include/notifications.js"></script>
    
    <script>
        let currentWeekStart = new Date();
        let calendarEvents = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set to start of week (Sunday)
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay());
            loadWeekView();
        });
        
        function loadWeekView() {
            showLoading();
            
            const startDate = new Date(currentWeekStart);
            const endDate = new Date(currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            
            // Format dates for API
            const startStr = formatDateForAPI(startDate);
            const endStr = formatDateForAPI(endDate);
            
            // Update date range display
            updateDateRange(startDate, endDate);
            
            // Fetch events from API
            fetch(`api/calendar/get_week_events.php?start=${startStr}&end=${endStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendarEvents = data.events;
                        updateStats(data.stats);
                        renderWeekView();
                    } else {
                        console.error('Error loading events:', data.message);
                        // Render empty view
                        renderWeekView();
                    }
                    hideLoading();
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Render empty view with sample data
                    renderSampleWeekView();
                    hideLoading();
                });
        }
        
        function renderWeekView() {
            renderWeekHeader();
            renderTimelineGrid();
        }
        
        function renderWeekHeader() {
            const header = document.getElementById('weekHeader');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let headerHTML = '<div class="time-column-header"></div>';
            
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            for (let i = 0; i < 7; i++) {
                const date = new Date(currentWeekStart);
                date.setDate(date.getDate() + i);
                
                const isToday = date.getTime() === today.getTime();
                const dayClass = isToday ? 'day-header today' : 'day-header';
                
                headerHTML += `
                    <div class="${dayClass}">
                        <div class="day-name">${dayNames[i].substring(0, 3)}</div>
                        <div class="day-number">${date.getDate()}</div>
                    </div>
                `;
            }
            
            header.innerHTML = headerHTML;
        }
        
        function renderTimelineGrid() {
            const grid = document.getElementById('timelineGrid');
            let gridHTML = '';
            
            // Hours from 6 AM to 10 PM
            const startHour = 6;
            const endHour = 22;
            
            for (let hour = startHour; hour <= endHour; hour++) {
                const timeLabel = formatHour(hour);
                
                gridHTML += `<div class="time-slot">${timeLabel}</div>`;
                
                // Day columns
                for (let day = 0; day < 7; day++) {
                    const date = new Date(currentWeekStart);
                    date.setDate(date.getDate() + day);
                    const dateStr = formatDateForAPI(date);
                    
                    // Get events for this time slot and day
                    const eventsHTML = renderEventsForSlot(dateStr, hour);
                    
                    gridHTML += `
                        <div class="day-column" data-date="${dateStr}" data-hour="${hour}">
                            ${eventsHTML}
                        </div>
                    `;
                }
            }
            
            grid.innerHTML = gridHTML;
        }
        
        function renderEventsForSlot(dateStr, hour) {
            let html = '';
            
            // Filter events for this date and hour
            const slotEvents = calendarEvents.filter(event => {
                if (event.date !== dateStr) return false;
                
                const eventHour = parseInt(event.time.split(':')[0]);
                return eventHour === hour;
            });
            
            slotEvents.forEach((event, index) => {
                const topOffset = index * 85; // Stack events
                const eventType = event.type || 'booking';
                
                html += `
                    <div class="event-card ${eventType}" 
                         style="top: ${topOffset}px; height: 75px;" 
                         onclick="showEventDetails(${event.id})">
                        <div class="event-time">
                            <i class="bi bi-clock"></i>
                            ${event.time}
                        </div>
                        <div class="event-title">${event.title}</div>
                        ${renderParticipants(event.participants)}
                    </div>
                `;
            });
            
            return html;
        }
        
        function renderParticipants(participants) {
            if (!participants || participants.length === 0) return '';
            
            let html = '<div class="event-participants">';
            
            const maxShow = 3;
            const showCount = Math.min(participants.length, maxShow);
            
            for (let i = 0; i < showCount; i++) {
                html += `<img src="${participants[i].avatar}" class="participant-avatar" alt="${participants[i].name}">`;
            }
            
            if (participants.length > maxShow) {
                html += `<div class="participant-more">+${participants.length - maxShow}</div>`;
            }
            
            html += '</div>';
            return html;
        }
        
        function renderSampleWeekView() {
            // Render with sample data for demonstration
            const sampleEvents = generateSampleEvents();
            calendarEvents = sampleEvents;
            
            updateStats({
                bookings: 12,
                payments: 8,
                verifications: 5,
                vehicles: 15
            });
            
            renderWeekView();
        }
        
        function generateSampleEvents() {
            const events = [];
            const eventTypes = ['booking', 'payment', 'verification', 'vehicle', 'report', 'refund'];
            const titles = [
                'Morning Rent', 'Payment Verification', 'Vehicle Checkout',
                'Customer Meeting', 'Document Review', 'Refund Processing'
            ];
            
            for (let day = 0; day < 7; day++) {
                const date = new Date(currentWeekStart);
                date.setDate(date.getDate() + day);
                const dateStr = formatDateForAPI(date);
                
                // Add 2-4 random events per day
                const eventCount = 2 + Math.floor(Math.random() * 3);
                
                for (let i = 0; i < eventCount; i++) {
                    const hour = 8 + Math.floor(Math.random() * 10);
                    const minute = Math.random() > 0.5 ? '00' : '30';
                    
                    events.push({
                        id: events.length + 1,
                        date: dateStr,
                        time: `${hour.toString().padStart(2, '0')}:${minute}`,
                        title: titles[Math.floor(Math.random() * titles.length)],
                        type: eventTypes[Math.floor(Math.random() * eventTypes.length)],
                        participants: [
                            { name: 'User 1', avatar: 'https://i.pravatar.cc/150?img=1' },
                            { name: 'User 2', avatar: 'https://i.pravatar.cc/150?img=2' }
                        ]
                    });
                }
            }
            
            return events;
        }
        
        function showEventDetails(eventId) {
            const event = calendarEvents.find(e => e.id === eventId);
            if (!event) return;
            
            document.getElementById('modalTitle').textContent = event.title;
            
            let bodyHTML = `
                <div class="detail-row">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">${formatDate(event.date)} at ${event.time}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Type</div>
                    <div class="detail-value">${event.type}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Description</div>
                    <div class="detail-value">${event.description || 'No description available'}</div>
                </div>
            `;
            
            if (event.amount) {
                bodyHTML += `
                    <div class="detail-row">
                        <div class="detail-label">Amount</div>
                        <div class="detail-value">₱${parseFloat(event.amount).toLocaleString()}</div>
                    </div>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = bodyHTML;
            document.getElementById('eventModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        function changeWeek(delta) {
            currentWeekStart.setDate(currentWeekStart.getDate() + (delta * 7));
            loadWeekView();
        }
        
        function goToToday() {
            currentWeekStart = new Date();
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay());
            loadWeekView();
        }
        
        function setView(view) {
            // Update active button
            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // TODO: Implement different views
            console.log('Switching to', view, 'view');
        }
        
        function updateDateRange(start, end) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            
            const startMonth = monthNames[start.getMonth()];
            const endMonth = monthNames[end.getMonth()];
            const year = start.getFullYear();
            
            let rangeText;
            if (startMonth === endMonth) {
                rangeText = `${startMonth} ${start.getDate()} - ${end.getDate()}, ${year}`;
            } else {
                rangeText = `${startMonth} ${start.getDate()} - ${endMonth} ${end.getDate()}, ${year}`;
            }
            
            document.getElementById('dateRange').textContent = rangeText;
        }
        
        function updateStats(stats) {
            document.getElementById('stat-bookings').textContent = stats.bookings || 0;
            document.getElementById('stat-payments').textContent = stats.payments || 0;
            document.getElementById('stat-verifications').textContent = stats.verifications || 0;
            document.getElementById('stat-vehicles').textContent = stats.vehicles || 0;
        }
        
        function formatDateForAPI(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                              'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }
        
        function formatHour(hour) {
            if (hour === 0) return '12 AM';
            if (hour < 12) return `${hour} AM`;
            if (hour === 12) return '12 PM';
            return `${hour - 12} PM`;
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Search functionality
        let searchTimeout;
        function handleSearch(event) {
            clearTimeout(searchTimeout);
            const query = event.target.value.trim();
            
            if (query.length < 2) return;
            
            searchTimeout = setTimeout(() => {
                // TODO: Implement search
                console.log('Searching for:', query);
            }, 500);
        }
        
        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
            if (e.key === 't' && !e.target.matches('input')) {
                goToToday();
            }
            if (e.key === 'ArrowLeft' && !e.target.matches('input')) {
                changeWeek(-1);
            }
            if (e.key === 'ArrowRight' && !e.target.matches('input')) {
                changeWeek(1);
            }
        });
    </script>
</body>
</html>