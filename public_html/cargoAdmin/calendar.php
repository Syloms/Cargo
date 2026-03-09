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
    <link href="include/modal-theme-standardized.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            background: #f8f9fb;
            color: #1f2937;
            overflow-x: hidden;
        }
        
        /* Override calendar custom modal with MUCH LARGER contact-modal design */
        .event-modal .modal-content {
            background: #ffffff !important;
            max-width: 1000px !important;
            margin: 2rem auto !important;
            border-radius: 20px !important;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.14), 0 4px 16px rgba(0, 0, 0, 0.06) !important;
            animation: slideUp 0.28s cubic-bezier(0.22, 1, 0.36, 1) !important;
            border: none !important;
        }

        .event-modal .modal-header {
            padding: 40px 40px 32px 40px !important;
            background: #ffffff !important;
            border-bottom: none !important;
            position: relative !important;
        }

        .event-modal .modal-header-bg {
            display: none !important;
        }

        .event-modal .modal-header-content {
            padding: 0 !important;
            color: #111827 !important;
        }

        .event-modal .modal-title {
            font-size: 32px !important;
            font-weight: 700 !important;
            color: #111827 !important;
            letter-spacing: -0.5px !important;
            text-shadow: none !important;
            margin-bottom: 12px !important;
            font-family: 'Sora', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }

        .event-modal .modal-type-badge {
            background: #ede9fe !important;
            color: #7c3aed !important;
            border: none !important;
            backdrop-filter: none !important;
            padding: 6px 16px !important;
            font-size: 13px !important;
            margin-bottom: 12px !important;
            font-weight: 600 !important;
        }

        .event-modal .modal-subtitle {
            color: #6b7280 !important;
            font-size: 16px !important;
        }

        .event-modal .modal-subtitle span {
            background: transparent !important;
            padding: 0 !important;
            border-radius: 0 !important;
            backdrop-filter: none !important;
        }

        .event-modal .modal-close {
            position: absolute !important;
            top: 40px !important;
            right: 40px !important;
            background: #f3f4f6 !important;
            backdrop-filter: none !important;
            border: none !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 10px !important;
            color: #6b7280 !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            font-size: 20px !important;
            line-height: 1 !important;
            text-align: center !important;
            z-index: 1000 !important;
        }

        .event-modal .modal-close::before {
            content: '✕' !important;
            display: block !important;
            font-size: 20px !important;
            font-weight: 400 !important;
            color: #6b7280 !important;
        }

        .event-modal .modal-close i {
            display: none !important;
        }

        .event-modal .modal-close:hover {
            background: #e5e7eb !important;
        }

        .event-modal .modal-close:hover::before {
            color: #111827 !important;
        }

        .event-modal .modal-body {
            padding: 40px !important;
            background: #ffffff !important;
            font-size: 18px !important;
            line-height: 1.7 !important;
        }

        .event-modal .modal-body p {
            font-size: 18px !important;
            line-height: 1.7 !important;
        }

        .event-modal .modal-section {
            margin-bottom: 28px !important;
        }

        .event-modal .section-title {
            font-size: 20px !important;
            font-weight: 650 !important;
            color: #111827 !important;
            letter-spacing: -0.3px !important;
            margin-bottom: 16px !important;
        }

        /* Contact-modal style info grids for calendar - LARGER */
        .event-modal .info-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0 !important;
            border: 1px solid #f0f0f0 !important;
            border-radius: 14px !important;
            overflow: hidden !important;
        }

        .event-modal .info-item {
            padding: 20px 24px !important;
            background: #ffffff !important;
            border-right: 1px solid #f0f0f0 !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }

        .event-modal .info-item:nth-child(2n) {
            border-right: none !important;
        }

        .event-modal .info-label {
            font-size: 15px !important;
            color: #9ca3af !important;
            font-weight: 500 !important;
            margin-bottom: 6px !important;
        }

        .event-modal .info-value {
            font-size: 18px !important;
            font-weight: 600 !important;
            color: #111827 !important;
            letter-spacing: -0.2px !important;
        }

        .event-modal .modal-actions {
            padding: 28px 40px 40px 40px !important;
            border-top: 1px solid #f0f0f0 !important;
            background: #ffffff !important;
            gap: 14px !important;
        }

        .event-modal .modal-action-btn {
            background: #f3f4f6 !important;
            color: #374151 !important;
            border: none !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            padding: 16px 24px !important;
            border-radius: 12px !important;
        }

        .event-modal .modal-action-btn:hover {
            background: #e5e7eb !important;
            color: #111827 !important;
        }

        .event-modal .modal-action-btn.primary {
            background: #111827 !important;
            color: #ffffff !important;
        }

        .event-modal .modal-action-btn.primary:hover {
            background: #1f2937 !important;
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
            padding: 1rem 2rem;
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
            gap: 1.5rem;
        }
        
        .date-range-selector {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f9fafb;
            padding: 0.4rem 0.875rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .nav-arrow {
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.125rem;
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
            font-size: 0.875rem;
            min-width: 180px;
            text-align: center;
        }
        
        .view-toggles {
            display: flex;
            gap: 0.35rem;
            background: #f9fafb;
            padding: 0.25rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .view-toggle {
            padding: 0.4rem 0.875rem;
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 6px;
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
            gap: 0.625rem;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding: 0.5rem 0.75rem 0.5rem 2.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            font-size: 0.8125rem;
            width: 240px;
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
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.8125rem;
        }
        
        .today-btn {
            padding: 0.5rem 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .today-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .add-event-btn {
            padding: 0.5rem 1rem;
            background: #6366f1;
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .add-event-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        /* Week View Container */
        .week-view-container {
            padding: 1.5rem 2rem;
        }
        
        .week-header {
            display: grid;
            grid-template-columns: 60px repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            margin-bottom: 1px;
        }
        
        .time-column-header {
            background: #ffffff;
            padding: 0.75rem;
        }
        
        .day-header {
            background: #ffffff;
            padding: 0.75rem;
            text-align: center;
        }
        
        .day-name {
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }
        
        .day-number {
            font-size: 1.25rem;
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
        
        /* Timeline Grid - Compact */
        .timeline-grid {
            display: grid;
            grid-template-columns: 60px repeat(7, 1fr);
            gap: 1px;
            background: #d1d5db;
            position: relative;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        
        .time-slot {
            background: #ffffff;
            padding: 0.4rem 0.4rem;
            border-right: 1px solid #e5e7eb;
            font-size: 0.65rem;
            color: #9ca3af;
            font-weight: 500;
            height: 50px;
            display: flex;
            align-items: flex-start;
        }
        
        .day-column {
            background: #ffffff;
            position: relative;
            min-height: 50px;
            border-right: 1px solid #e5e7eb;
        }
        
        .day-column:hover {
            background: #f9fafb;
        }
        
        /* Month View */
        .month-view {
            display: none;
        }
        
        .month-view.active {
            display: block;
        }
        
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .month-day-header {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid #e5e7eb;
        }
        
        .month-day-header:last-child {
            border-right: none;
        }
        
        .month-day-cell {
            background: #ffffff;
            min-height: 120px;
            padding: 0.5rem;
            border-right: 1px solid #e5e7eb;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .month-day-cell:hover {
            background: #f9fafb;
        }
        
        .month-day-cell.other-month {
            background: #f9fafb;
            opacity: 0.5;
        }
        
        .month-day-cell.today {
            background: #eff6ff;
        }
        
        .month-day-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .month-day-cell.today .month-day-number {
            color: #3b82f6;
        }
        
        .month-events {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .month-event {
            font-size: 0.7rem;
            padding: 0.25rem 0.4rem;
            border-radius: 4px;
            border-left: 2px solid;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .month-event:hover {
            transform: translateX(2px);
        }
        
        .month-event.booking {
            background: #ecfdf5;
            border-left-color: #10b981;
            color: #059669;
        }
        
        .month-event.payment {
            background: #eff6ff;
            border-left-color: #3b82f6;
            color: #2563eb;
        }
        
        .month-event.verification {
            background: #f5f3ff;
            border-left-color: #8b5cf6;
            color: #7c3aed;
        }
        
        .month-event.vehicle {
            background: #fffbeb;
            border-left-color: #f59e0b;
            color: #d97706;
        }
        
        .month-event-more {
            font-size: 0.65rem;
            color: #6b7280;
            font-weight: 600;
            padding: 0.25rem 0.4rem;
            cursor: pointer;
        }
        
        .month-event-more:hover {
            color: #3b82f6;
        }
        
        /* Day View */
        .day-view {
            display: none;
        }
        
        .day-view.active {
            display: block;
        }
        
        .day-view-header {
            background: #ffffff;
            padding: 1.5rem 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .day-view-date {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .day-view-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .day-timeline {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        
        .day-time-slot {
            display: grid;
            grid-template-columns: 80px 1fr;
            border-bottom: 1px solid #e5e7eb;
            min-height: 80px;
        }
        
        .day-time-slot:last-child {
            border-bottom: none;
        }
        
        .day-time-label {
            padding: 1rem;
            background: #f9fafb;
            border-right: 1px solid #e5e7eb;
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
        }
        
        .day-time-content {
            padding: 1rem;
            position: relative;
        }
        
        .day-event-card {
            background: #ffffff;
            border-left: 3px solid;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .day-event-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Year View */
        .year-view {
            display: none;
        }
        
        .year-view.active {
            display: block;
        }
        
        .year-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .year-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .year-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .mini-month {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 1rem;
            transition: all 0.2s;
        }
        
        .mini-month:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        
        .mini-month-header {
            font-size: 0.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
            text-align: center;
        }
        
        .mini-month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .mini-month-day-header {
            font-size: 0.65rem;
            font-weight: 600;
            color: #9ca3af;
            text-align: center;
            padding: 0.25rem;
        }
        
        .mini-month-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #4b5563;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .mini-month-day:hover {
            background: #f3f4f6;
        }
        
        .mini-month-day.other-month {
            color: #d1d5db;
        }
        
        .mini-month-day.today {
            background: #6366f1;
            color: #ffffff;
            font-weight: 700;
        }
        
        .mini-month-day.has-events::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 4px;
            height: 4px;
            background: #6366f1;
            border-radius: 50%;
        }
        
        .mini-month-day.today.has-events::after {
            background: #ffffff;
        }
        
        /* Week View Container */
        .week-view {
            display: none;
        }
        
        .week-view.active {
            display: block;
        }
        
        /* Event Cards - Compact */
        .event-card {
            position: absolute;
            left: 3px;
            right: 3px;
            padding: 0.5rem;
            border-radius: 8px;
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
            font-size: 0.65rem;
            font-weight: 600;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        
        .event-card.booking .event-time { color: #059669; }
        .event-card.payment .event-time { color: #2563eb; }
        .event-card.verification .event-time { color: #7c3aed; }
        .event-card.vehicle .event-time { color: #d97706; }
        .event-card.report .event-time { color: #dc2626; }
        .event-card.refund .event-time { color: #db2777; }
        
        .event-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.3rem;
            line-height: 1.2;
        }
        
        .event-participants {
            display: flex;
            align-items: center;
            margin-top: 0.3rem;
        }
        
        .participant-avatar {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            margin-left: -5px;
            object-fit: cover;
        }
        
        .participant-avatar:first-child {
            margin-left: 0;
        }
        
        .participant-more {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e5e7eb;
            border: 2px solid #ffffff;
            margin-left: -5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 600;
            color: #6b7280;
        }
        
        /* Stats Overview - Compact */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: #ffffff;
            padding: 1.25rem;
            border-radius: 10px;
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
            margin-bottom: 0.75rem;
        }
        
        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .stat-icon.booking { background: #ecfdf5; color: #10b981; }
        .stat-icon.payment { background: #eff6ff; color: #3b82f6; }
        .stat-icon.verification { background: #f5f3ff; color: #8b5cf6; }
        .stat-icon.vehicle { background: #fffbeb; color: #f59e0b; }
        .stat-icon.report { background: #fef2f2; color: #ef4444; }
        .stat-icon.refund { background: #fdf2f8; color: #ec4899; }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.2rem;
        }
        
        .stat-label {
            font-size: 0.8125rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Enhanced Event Detail Modal */
        .event-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(12px);
            z-index: 9999;
            padding: 1.5rem;
            overflow-y: auto;
            animation: fadeIn 0.25s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: #ffffff;
            max-width: 850px;
            margin: 2rem auto;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.35);
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.8);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.92);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.95;
        }
        
        .modal-header-content {
            position: relative;
            padding: 2rem 2.5rem 1.5rem;
            color: white;
        }
        
        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            z-index: 10;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg) scale(1.1);
        }
        
        .modal-title-section {
            max-width: 90%;
        }
        
        .modal-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 1rem;
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .modal-title {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.75rem;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .modal-subtitle {
            font-size: 1rem;
            color: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .modal-subtitle span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.4rem 0.875rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .modal-subtitle i {
            font-size: 0.9rem;
        }
        
        .modal-body {
            padding: 2.5rem;
            background: #ffffff;
        }
        
        .modal-section {
            margin-bottom: 2.5rem;
        }
        
        .modal-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: #9ca3af;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        
        .detail-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #6366f1, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .detail-card:hover {
            border-color: #6366f1;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }
        
        .detail-card:hover::before {
            opacity: 1;
        }
        
        .detail-card.highlight {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #93c5fd;
        }
        
        .detail-card.highlight::before {
            background: linear-gradient(180deg, #3b82f6, #2563eb);
            opacity: 1;
        }
        
        .detail-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-label i {
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .detail-value {
            font-size: 1.125rem;
            color: #1f2937;
            font-weight: 700;
            line-height: 1.4;
        }
        
        .detail-value.extra-large {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .detail-value.large {
            font-size: 1.75rem;
            font-weight: 800;
        }
        
        .detail-value.success {
            color: #10b981;
        }
        
        .detail-value.warning {
            color: #f59e0b;
        }
        
        .detail-value.danger {
            color: #ef4444;
        }
        
        .detail-value.info {
            color: #3b82f6;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.875rem;
            border: 2px solid;
        }
        
        .status-badge i {
            font-size: 0.875rem;
        }
        
        .status-badge.pulse {
            animation: statusPulse 2s ease-in-out infinite;
        }
        
        @keyframes statusPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .info-list-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        
        .info-list-item:hover {
            background: #f9fafb;
            border-color: #6366f1;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        
        .info-list-item:last-child {
            margin-bottom: 0;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.125rem;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 600;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 2rem;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: #6366f1;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 3px #e0e7ff;
        }
        
        .timeline-content {
            background: #ffffff;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #6366f1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .timeline-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .timeline-time {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* Modal Actions */
        .modal-actions {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 2px solid #f3f4f6;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        
        .modal-action-btn {
            padding: 0.875rem 1.25rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            min-width: fit-content;
            flex: 0 1 auto;
        }
        
        .modal-action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .modal-action-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .modal-action-btn i {
            position: relative;
            z-index: 1;
        }
        
        .modal-action-btn span {
            position: relative;
            z-index: 1;
        }
        
        .modal-action-btn.primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }
        
        .modal-action-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
        }
        
        .modal-action-btn.secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }
        
        .modal-action-btn.secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.5);
        }
        
        .modal-action-btn.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }
        
        .modal-action-btn.success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.5);
        }
        
        .modal-action-btn.tertiary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .modal-action-btn.tertiary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
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
        
        /* Search Results Dropdown */
        .search-results-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .search-results-header {
            padding: 0.875rem 1rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 12px 12px 0 0;
        }
        
        .search-result-item {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 3px solid transparent;
        }
        
        .search-result-item:hover {
            background: #f9fafb;
            border-left-color: currentColor;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
            border-radius: 0 0 12px 12px;
        }
        
        .search-result-icon {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-result-content {
            flex: 1;
        }
        
        .search-result-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .search-result-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .search-result-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .search-no-results,
        .search-error {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
        }
        
        .search-no-results i,
        .search-error i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .search-no-results p,
        .search-error p {
            margin: 0;
            font-size: 0.875rem;
        }
        
        /* Highlight animation for events */
        .highlight-event {
            animation: highlightPulse 2s ease;
        }
        
        @keyframes highlightPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
            }
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
                grid-template-columns: 55px repeat(7, 1fr);
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
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
                grid-template-columns: 45px repeat(3, 1fr);
            }
            
            .day-header:nth-child(n+5),
            .day-column:nth-child(n+5) {
                display: none;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .modal-content {
                margin: 1rem auto;
                border-radius: 16px;
            }
            
            .modal-header-content {
                padding: 1.5rem;
            }
            
            .modal-title {
                font-size: 1.5rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .modal-action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-subtitle {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .event-modal,
            .event-modal * {
                visibility: visible;
            }
            
            .event-modal {
                position: fixed;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: white;
                padding: 0;
            }
            
            .modal-content {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
            
            .modal-close {
                display: none;
            }
            
            .modal-actions {
                display: none;
            }
            
            .modal-header-bg {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
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
                    <input type="text" placeholder="Search events..." id="searchInput" onkeyup="handleSearch(event)">
                </div>
                <button class="today-btn" onclick="goToToday()">Today</button>
                <button class="add-event-btn" onclick="exportFilteredEvents()">
                    <i class="bi bi-download"></i>
                    Export
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
            
            <!-- Week View -->
            <div class="week-view active" id="weekView">
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
            
            <!-- Month View -->
            <div class="month-view" id="monthView">
                <div class="month-grid" id="monthGrid">
                    <!-- Month calendar will be inserted here -->
                </div>
            </div>
            
            <!-- Day View -->
            <div class="day-view" id="dayView">
                <div class="day-view-header">
                    <div class="day-view-date" id="dayViewDate">Monday, January 15, 2025</div>
                    <div class="day-view-subtitle" id="dayViewSubtitle">No events scheduled</div>
                </div>
                <div class="day-timeline" id="dayTimeline">
                    <!-- Day timeline will be inserted here -->
                </div>
            </div>
            
            <!-- Year View -->
            <div class="year-view" id="yearView">
                <div class="year-header">
                    <div class="year-title" id="yearTitle">2025</div>
                </div>
                <div class="year-grid" id="yearGrid">
                    <!-- Year grid will be inserted here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Event Detail Modal -->
    <div class="event-modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-bg"></div>
                <div class="modal-header-content">
                    <div class="modal-title-section">
                        <div class="modal-type-badge" id="modalTypeBadge">
                            <i class="bi bi-circle-fill"></i>
                            <span>Event</span>
                        </div>
                        <h3 class="modal-title" id="modalTitle">Event Details</h3>
                        <div class="modal-subtitle" id="modalSubtitle">
                            <span><i class="bi bi-calendar3"></i> Date & Time</span>
                        </div>
                    </div>
                </div>
                <button class="modal-close" onclick="closeModal()"></button>
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
        let currentDate = new Date();
        let currentView = 'week';
        let calendarEvents = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set to start of week (Sunday)
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay());
            loadView();
        });
        
        function loadView() {
            switch(currentView) {
                case 'week':
                    loadWeekView();
                    break;
                case 'month':
                    loadMonthView();
                    break;
                case 'day':
                    loadDayView();
                    break;
                case 'year':
                    loadYearView();
                    break;
            }
        }
        
        function setView(view) {
            currentView = view;
            
            // Update active button
            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Hide all views
            document.querySelectorAll('.week-view, .month-view, .day-view, .year-view').forEach(v => {
                v.classList.remove('active');
            });
            
            // Load the selected view
            loadView();
        }
        
        function loadWeekView() {
            showLoading();
            document.getElementById('weekView').classList.add('active');
            
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
                        calendarEvents = [];
                        updateStats({bookings: 0, payments: 0, verifications: 0, vehicles: 0});
                        renderWeekView();
                    }
                    hideLoading();
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    calendarEvents = [];
                    updateStats({bookings: 0, payments: 0, verifications: 0, vehicles: 0});
                    renderWeekView();
                    hideLoading();
                });
        }
        
        function loadMonthView() {
            showLoading();
            document.getElementById('monthView').classList.add('active');
            
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Get first and last day of month view (including prev/next month days)
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - startDate.getDay());
            const endDate = new Date(lastDay);
            endDate.setDate(endDate.getDate() + (6 - endDate.getDay()));
            
            const startStr = formatDateForAPI(startDate);
            const endStr = formatDateForAPI(endDate);
            
            updateDateRange(new Date(year, month, 1), new Date(year, month + 1, 0));
            
            fetch(`api/calendar/get_week_events.php?start=${startStr}&end=${endStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendarEvents = data.events;
                        updateStats(data.stats);
                    } else {
                        calendarEvents = [];
                        updateStats({bookings: 0, payments: 0, verifications: 0, vehicles: 0});
                    }
                    renderMonthView();
                    hideLoading();
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    calendarEvents = [];
                    renderMonthView();
                    hideLoading();
                });
        }
        
        function loadDayView() {
            showLoading();
            document.getElementById('dayView').classList.add('active');
            
            const dateStr = formatDateForAPI(currentDate);
            updateDateRange(currentDate, currentDate);
            
            fetch(`api/calendar/get_week_events.php?start=${dateStr}&end=${dateStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendarEvents = data.events;
                        updateStats(data.stats);
                    } else {
                        calendarEvents = [];
                        updateStats({bookings: 0, payments: 0, verifications: 0, vehicles: 0});
                    }
                    renderDayView();
                    hideLoading();
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    calendarEvents = [];
                    renderDayView();
                    hideLoading();
                });
        }
        
        function loadYearView() {
            showLoading();
            document.getElementById('yearView').classList.add('active');
            
            const year = currentDate.getFullYear();
            const startStr = `${year}-01-01`;
            const endStr = `${year}-12-31`;
            
            document.getElementById('dateRange').textContent = year.toString();
            
            fetch(`api/calendar/get_week_events.php?start=${startStr}&end=${endStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendarEvents = data.events;
                        updateStats(data.stats);
                    } else {
                        calendarEvents = [];
                        updateStats({bookings: 0, payments: 0, verifications: 0, vehicles: 0});
                    }
                    renderYearView();
                    hideLoading();
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    calendarEvents = [];
                    renderYearView();
                    hideLoading();
                });
        }
        
        function renderWeekView() {
            renderWeekHeader();
            renderTimelineGrid();
        }
        
        function renderMonthView() {
            const grid = document.getElementById('monthGrid');
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - startDate.getDay());
            
            let html = '';
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            // Day headers
            dayNames.forEach(day => {
                html += `<div class="month-day-header">${day}</div>`;
            });
            
            // Calendar days
            const current = new Date(startDate);
            for (let i = 0; i < 42; i++) {
                const isCurrentMonth = current.getMonth() === month;
                const isToday = current.getTime() === today.getTime();
                const dateStr = formatDateForAPI(current);
                const dayEvents = calendarEvents.filter(e => e.date === dateStr);
                
                let cellClass = 'month-day-cell';
                if (!isCurrentMonth) cellClass += ' other-month';
                if (isToday) cellClass += ' today';
                
                html += `
                    <div class="${cellClass}" onclick="viewDayFromMonth('${dateStr}')">
                        <div class="month-day-number">${current.getDate()}</div>
                        <div class="month-events">
                `;
                
                const maxShow = 3;
                dayEvents.slice(0, maxShow).forEach(event => {
                    html += `
                        <div class="month-event ${event.type}" onclick="event.stopPropagation(); showEventDetails(${event.id})">
                            ${event.time} ${event.title}
                        </div>
                    `;
                });
                
                if (dayEvents.length > maxShow) {
                    html += `
                        <div class="month-event-more" onclick="event.stopPropagation(); viewDayFromMonth('${dateStr}')">
                            +${dayEvents.length - maxShow} more
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
                
                current.setDate(current.getDate() + 1);
            }
            
            grid.innerHTML = html;
        }
        
        function renderDayView() {
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            
            const dayName = dayNames[currentDate.getDay()];
            const monthName = monthNames[currentDate.getMonth()];
            const date = currentDate.getDate();
            const year = currentDate.getFullYear();
            
            document.getElementById('dayViewDate').textContent = `${dayName}, ${monthName} ${date}, ${year}`;
            
            const dateStr = formatDateForAPI(currentDate);
            const dayEvents = calendarEvents.filter(e => e.date === dateStr);
            
            document.getElementById('dayViewSubtitle').textContent = 
                dayEvents.length === 0 ? 'No events scheduled' : 
                `${dayEvents.length} event${dayEvents.length > 1 ? 's' : ''} scheduled`;
            
            const timeline = document.getElementById('dayTimeline');
            let html = '';
            
            for (let hour = 6; hour <= 22; hour++) {
                const timeLabel = formatHour(hour);
                const hourEvents = dayEvents.filter(e => {
                    const eventHour = parseInt(e.time.split(':')[0]);
                    return eventHour === hour;
                });
                
                html += `
                    <div class="day-time-slot">
                        <div class="day-time-label">${timeLabel}</div>
                        <div class="day-time-content">
                `;
                
                hourEvents.forEach(event => {
                    const color = getColorForType(event.type);
                    html += `
                        <div class="day-event-card ${event.type}" 
                             style="border-left-color: ${color}"
                             onclick="showEventDetails(${event.id})">
                            <div class="event-time">
                                <i class="bi bi-clock"></i>
                                ${event.time}
                            </div>
                            <div class="event-title">${event.title}</div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            timeline.innerHTML = html;
        }
        
        function renderYearView() {
            const year = currentDate.getFullYear();
            document.getElementById('yearTitle').textContent = year;
            
            const grid = document.getElementById('yearGrid');
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let html = '';
            
            for (let month = 0; month < 12; month++) {
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const startDate = new Date(firstDay);
                startDate.setDate(startDate.getDate() - startDate.getDay());
                
                html += `
                    <div class="mini-month" onclick="viewMonthFromYear(${month})">
                        <div class="mini-month-header">${monthNames[month]}</div>
                        <div class="mini-month-grid">
                `;
                
                // Day headers
                dayNames.forEach(day => {
                    html += `<div class="mini-month-day-header">${day}</div>`;
                });
                
                // Calendar days
                const current = new Date(startDate);
                for (let i = 0; i < 42; i++) {
                    const isCurrentMonth = current.getMonth() === month;
                    const isToday = current.getTime() === today.getTime();
                    const dateStr = formatDateForAPI(current);
                    const hasEvents = calendarEvents.some(e => e.date === dateStr);
                    
                    let cellClass = 'mini-month-day';
                    if (!isCurrentMonth) cellClass += ' other-month';
                    if (isToday) cellClass += ' today';
                    if (hasEvents) cellClass += ' has-events';
                    
                    html += `<div class="${cellClass}">${current.getDate()}</div>`;
                    
                    current.setDate(current.getDate() + 1);
                    if (current.getMonth() !== month && i >= 27) break;
                }
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            grid.innerHTML = html;
        }
        
        function viewDayFromMonth(dateStr) {
            currentDate = new Date(dateStr);
            currentView = 'day';
            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === 'Day') btn.classList.add('active');
            });
            document.querySelectorAll('.week-view, .month-view, .day-view, .year-view').forEach(v => {
                v.classList.remove('active');
            });
            loadDayView();
        }
        
        function viewMonthFromYear(month) {
            currentDate = new Date(currentDate.getFullYear(), month, 1);
            currentView = 'month';
            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === 'Month') btn.classList.add('active');
            });
            document.querySelectorAll('.week-view, .month-view, .day-view, .year-view').forEach(v => {
                v.classList.remove('active');
            });
            loadMonthView();
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
                const topOffset = index * 55;
                const eventType = event.type || 'booking';
                const displayTitle = event.title.length > 30 ? event.title.substring(0, 27) + '...' : event.title;
                
                html += `
                    <div class="event-card ${eventType}" 
                         style="top: ${topOffset}px; height: 48px;" 
                         data-event-id="${event.id}"
                         onclick="showEventDetails(${event.id})"
                         title="${event.title}">
                        <div class="event-time">
                            <i class="bi bi-clock"></i>
                            ${event.time}
                        </div>
                        <div class="event-title">${displayTitle}</div>
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
        
        function showEventDetails(eventId) {
            const event = calendarEvents.find(e => e.id === eventId);
            if (!event) {
                console.error('Event not found:', eventId);
                return;
            }
            
            const typeIcons = {
                'booking': 'bi-calendar-check',
                'payment': 'bi-credit-card',
                'verification': 'bi-shield-check',
                'vehicle': 'bi-car-front',
                'report': 'bi-exclamation-triangle',
                'refund': 'bi-arrow-counterclockwise'
            };
            
            const typeGradients = {
                'booking': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                'payment': 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
                'verification': 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
                'vehicle': 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                'report': 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                'refund': 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)'
            };
            
            const typeColors = {
                'booking': '#10b981',
                'payment': '#3b82f6',
                'verification': '#8b5cf6',
                'vehicle': '#f59e0b',
                'report': '#ef4444',
                'refund': '#ec4899'
            };
            
            const icon = typeIcons[event.type] || 'bi-info-circle';
            const gradient = typeGradients[event.type] || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            const color = typeColors[event.type] || '#6b7280';
            
            // Update modal header background
            const headerBg = document.querySelector('.modal-header-bg');
            if (headerBg) {
                headerBg.style.background = gradient;
            }
            
            // Set type badge
            document.getElementById('modalTypeBadge').innerHTML = `
                <i class="bi ${icon}"></i>
                <span>${event.type.charAt(0).toUpperCase() + event.type.slice(1)}</span>
            `;
            
            // Set modal title
            document.getElementById('modalTitle').textContent = event.title;
            
            // Set modal subtitle with enhanced info
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const eventDate = new Date(event.date);
            const dayName = dayNames[eventDate.getDay()];
            
            document.getElementById('modalSubtitle').innerHTML = `
                <span><i class="bi bi-calendar3"></i> ${dayName}, ${formatDate(event.date)}</span>
                <span><i class="bi bi-clock"></i> ${event.time}</span>
                ${event.event_type ? `<span><i class="bi bi-tag"></i> ${event.event_type.charAt(0).toUpperCase() + event.event_type.slice(1)}</span>` : ''}
            `;
            
            let bodyHTML = '';
            
            // Primary Information Section
            if (event.amount || event.status) {
                bodyHTML += '<div class="modal-section">';
                bodyHTML += '<h4 class="section-title"><i class="bi bi-info-circle"></i> Primary Information</h4>';
                bodyHTML += '<div class="detail-grid">';
                
                // Amount (if exists)
                if (event.amount) {
                    bodyHTML += `
                        <div class="detail-card highlight">
                            <div class="detail-label">
                                <span class="currency-symbol">₱</span>
                                Amount
                            </div>
                            <div class="detail-value extra-large">
                                ₱${parseFloat(event.amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </div>
                        </div>
                    `;
                }
                
                // Status (if exists)
                if (event.status) {
                    const statusConfig = getStatusConfig(event.status);
                    bodyHTML += `
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="bi bi-check-circle"></i>
                                Current Status
                            </div>
                            <div class="detail-value">
                                <span class="status-badge ${statusConfig.pulse ? 'pulse' : ''}" style="background: ${statusConfig.bgColor}; color: ${statusConfig.color}; border-color: ${statusConfig.borderColor};">
                                    <i class="bi ${statusConfig.icon}"></i>
                                    ${event.status.charAt(0).toUpperCase() + event.status.slice(1)}
                                </span>
                            </div>
                        </div>
                    `;
                }
                
                bodyHTML += '</div></div>';
            }
            
            // Additional Details Section
            bodyHTML += '<div class="modal-section">';
            bodyHTML += '<h4 class="section-title"><i class="bi bi-list-ul"></i> Event Details</h4>';
            bodyHTML += '<ul class="info-list">';
            
            // Event Type
            if (event.event_type) {
                bodyHTML += `
                    <li class="info-list-item">
                        <div class="info-icon" style="background: ${getColorForType(event.type)}20; color: ${getColorForType(event.type)};">
                            <i class="bi bi-tag"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Action Type</div>
                            <div class="info-value">${event.event_type.charAt(0).toUpperCase() + event.event_type.slice(1)}</div>
                        </div>
                    </li>
                `;
            }
            
            // Payment Method
            if (event.payment_method) {
                bodyHTML += `
                    <li class="info-list-item">
                        <div class="info-icon" style="background: #eff6ff; color: #3b82f6;">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Payment Method</div>
                            <div class="info-value">${event.payment_method}</div>
                        </div>
                    </li>
                `;
            }
            
            // Vehicle Type
            if (event.vehicle_type) {
                bodyHTML += `
                    <li class="info-list-item">
                        <div class="info-icon" style="background: #fffbeb; color: #f59e0b;">
                            <i class="bi bi-car-front"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Vehicle Type</div>
                            <div class="info-value">${event.vehicle_type.charAt(0).toUpperCase() + event.vehicle_type.slice(1)}</div>
                        </div>
                    </li>
                `;
            }
            
            // Booking ID
            if (event.booking_id) {
                bodyHTML += `
                    <li class="info-list-item">
                        <div class="info-icon" style="background: #ecfdf5; color: #10b981;">
                            <i class="bi bi-hash"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Booking Reference</div>
                            <div class="info-value">#BK${String(event.booking_id).padStart(6, '0')}</div>
                        </div>
                    </li>
                `;
            }
            
            // Payment ID
            if (event.payment_id) {
                bodyHTML += `
                    <li class="info-list-item">
                        <div class="info-icon" style="background: #eff6ff; color: #3b82f6;">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Payment Reference</div>
                            <div class="info-value">#PAY${String(event.payment_id).padStart(6, '0')}</div>
                        </div>
                    </li>
                `;
            }
            
            bodyHTML += '</ul></div>';
            
            // Description Section
            if (event.description) {
                bodyHTML += '<div class="modal-section">';
                bodyHTML += '<h4 class="section-title"><i class="bi bi-file-text"></i> Description</h4>';
                bodyHTML += `
                    <div class="detail-card">
                        <div class="detail-value" style="font-size: 0.95rem; font-weight: 500; line-height: 1.6;">
                            ${event.description}
                        </div>
                    </div>
                `;
                bodyHTML += '</div>';
            }
            
            // Event Timeline (mock - can be replaced with real data)
            if (event.status) {
                bodyHTML += '<div class="modal-section">';
                bodyHTML += '<h4 class="section-title"><i class="bi bi-clock-history"></i> Event Timeline</h4>';
                bodyHTML += generateEventTimeline(event);
                bodyHTML += '</div>';
            }
            
            // Action Buttons
            bodyHTML += '<div class="modal-actions">';
            
            if (event.booking_id) {
                bodyHTML += `
                    <button onclick="viewBookingDetails(${event.booking_id})" class="modal-action-btn primary">
                        <i class="bi bi-eye"></i>
                        <span>View Full Booking</span>
                    </button>
                `;
            }
            
            if (event.payment_id) {
                bodyHTML += `
                    <button onclick="viewPaymentDetails(${event.payment_id})" class="modal-action-btn secondary">
                        <i class="bi bi-receipt"></i>
                        <span>View Payment Details</span>
                    </button>
                `;
            }
            
            // Print/Export button
            bodyHTML += `
                <button onclick="printEventDetails()" class="modal-action-btn success">
                    <i class="bi bi-printer"></i>
                    <span>Print Details</span>
                </button>
            `;
            
            bodyHTML += `
                <button onclick="closeModal()" class="modal-action-btn tertiary">
                    <i class="bi bi-x-lg"></i>
                    <span>Close</span>
                </button>
            `;
            
            bodyHTML += '</div>';
            
            document.getElementById('modalBody').innerHTML = bodyHTML;
            document.getElementById('eventModal').style.display = 'block';
            document.body.classList.add('modal-open');
            
            // Smooth scroll to top of modal
            setTimeout(() => {
                document.querySelector('.modal-content').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        
        function getStatusConfig(status) {
            const configs = {
                'approved': {
                    color: '#10b981',
                    bgColor: '#ecfdf5',
                    borderColor: '#10b981',
                    icon: 'bi-check-circle-fill',
                    pulse: false
                },
                'pending': {
                    color: '#f59e0b',
                    bgColor: '#fffbeb',
                    borderColor: '#f59e0b',
                    icon: 'bi-clock-fill',
                    pulse: true
                },
                'completed': {
                    color: '#6b7280',
                    bgColor: '#f3f4f6',
                    borderColor: '#6b7280',
                    icon: 'bi-check-all',
                    pulse: false
                },
                'cancelled': {
                    color: '#ef4444',
                    bgColor: '#fef2f2',
                    borderColor: '#ef4444',
                    icon: 'bi-x-circle-fill',
                    pulse: false
                },
                'verified': {
                    color: '#10b981',
                    bgColor: '#ecfdf5',
                    borderColor: '#10b981',
                    icon: 'bi-shield-check',
                    pulse: false
                },
                'rejected': {
                    color: '#ef4444',
                    bgColor: '#fef2f2',
                    borderColor: '#ef4444',
                    icon: 'bi-x-octagon-fill',
                    pulse: false
                }
            };
            
            return configs[status] || {
                color: '#6b7280',
                bgColor: '#f3f4f6',
                borderColor: '#9ca3af',
                icon: 'bi-circle-fill',
                pulse: false
            };
        }
        
        function generateEventTimeline(event) {
            // This is a mock timeline - you can replace with real data from your API
            let html = '<div>';
            
            const timelineSteps = [];
            
            if (event.type === 'booking') {
                timelineSteps.push(
                    { title: 'Booking Created', time: event.date + ' ' + event.time, completed: true },
                    { title: 'Payment Processed', time: 'Pending', completed: event.status === 'approved' },
                    { title: 'Vehicle Assigned', time: 'Pending', completed: false },
                    { title: 'Pickup Scheduled', time: 'Pending', completed: false }
                );
            } else if (event.type === 'payment') {
                timelineSteps.push(
                    { title: 'Payment Initiated', time: event.date + ' ' + event.time, completed: true },
                    { title: 'Processing Payment', time: '2 mins ago', completed: true },
                    { title: 'Payment Verified', time: event.status === 'completed' ? 'Just now' : 'Pending', completed: event.status === 'completed' },
                    { title: 'Receipt Generated', time: event.status === 'completed' ? 'Just now' : 'Pending', completed: event.status === 'completed' }
                );
            } else {
                timelineSteps.push(
                    { title: 'Event Created', time: event.date + ' ' + event.time, completed: true },
                    { title: 'In Progress', time: 'Current', completed: event.status === 'approved' || event.status === 'completed' },
                    { title: 'Completed', time: event.status === 'completed' ? 'Completed' : 'Pending', completed: event.status === 'completed' }
                );
            }
            
            timelineSteps.forEach((step, index) => {
                html += `
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: ${step.completed ? '#10b981' : '#e5e7eb'}; ${!step.completed ? 'box-shadow: 0 0 0 3px #f3f4f6;' : ''}"></div>
                        <div class="timeline-content" style="${!step.completed ? 'opacity: 0.6;' : ''}">
                            <div class="timeline-title">${step.title}</div>
                            <div class="timeline-time">${step.time}</div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        function printEventDetails() {
            window.print();
        }
        
        function viewBookingDetails(bookingId) {
            window.location.href = `bookings.php?id=${bookingId}`;
        }
        
        function viewPaymentDetails(paymentId) {
            window.location.href = `payment.php?id=${paymentId}`;
        }
        
        function closeModal() {
            document.getElementById('eventModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        function changeWeek(delta) {
            if (currentView === 'week') {
                currentWeekStart.setDate(currentWeekStart.getDate() + (delta * 7));
                loadWeekView();
            } else if (currentView === 'month') {
                currentDate.setMonth(currentDate.getMonth() + delta);
                loadMonthView();
            } else if (currentView === 'day') {
                currentDate.setDate(currentDate.getDate() + delta);
                loadDayView();
            } else if (currentView === 'year') {
                currentDate.setFullYear(currentDate.getFullYear() + delta);
                loadYearView();
            }
        }
        
        function goToToday() {
            const today = new Date();
            currentDate = new Date(today);
            currentWeekStart = new Date(today);
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay());
            loadView();
        }
        
        function updateDateRange(start, end) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                              'July', 'August', 'September', 'October', 'November', 'December'];
            
            let rangeText;
            
            if (currentView === 'year') {
                rangeText = start.getFullYear().toString();
            } else if (currentView === 'month') {
                rangeText = `${monthNames[start.getMonth()]} ${start.getFullYear()}`;
            } else if (currentView === 'day') {
                rangeText = `${monthNames[start.getMonth()]} ${start.getDate()}, ${start.getFullYear()}`;
            } else {
                // Week view
                const startMonth = monthNames[start.getMonth()];
                const endMonth = monthNames[end.getMonth()];
                const year = start.getFullYear();
                
                if (startMonth === endMonth) {
                    rangeText = `${startMonth} ${start.getDate()} - ${end.getDate()}, ${year}`;
                } else {
                    rangeText = `${startMonth} ${start.getDate()} - ${endMonth} ${end.getDate()}, ${year}`;
                }
            }
            
            document.getElementById('dateRange').textContent = rangeText;
        }
        
        function getIconForType(type) {
            const icons = {
                'booking': 'bi-calendar-check',
                'payment': 'bi-credit-card',
                'verification': 'bi-shield-check',
                'vehicle': 'bi-car-front',
                'report': 'bi-exclamation-triangle',
                'refund': 'bi-arrow-counterclockwise'
            };
            return icons[type] || 'bi-circle-fill';
        }
        
        function getColorForType(type) {
            const colors = {
                'booking': '#10b981',
                'payment': '#3b82f6',
                'verification': '#8b5cf6',
                'vehicle': '#f59e0b',
                'report': '#ef4444',
                'refund': '#ec4899'
            };
            return colors[type] || '#6b7280';
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
        let searchResults = [];
        
        function handleSearch(event) {
            clearTimeout(searchTimeout);
            const query = event.target.value.trim();
            
            if (query.length < 2) {
                hideSearchResults();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 500);
        }
        
        function performSearch(query) {
            showLoading();
            
            fetch(`api/calendar/search_events.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    
                    if (data.success && data.results.length > 0) {
                        searchResults = data.results;
                        displaySearchResults(data.results);
                    } else {
                        displayNoResults(query);
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    hideLoading();
                    displaySearchError();
                });
        }
        
        function displaySearchResults(results) {
            let existingDropdown = document.getElementById('searchResultsDropdown');
            if (existingDropdown) {
                existingDropdown.remove();
            }
            
            const searchBox = document.querySelector('.search-box');
            const dropdown = document.createElement('div');
            dropdown.id = 'searchResultsDropdown';
            dropdown.className = 'search-results-dropdown';
            
            let html = '<div class="search-results-header">Search Results (' + results.length + ')</div>';
            
            results.forEach(result => {
                const icon = getIconForType(result.type);
                const color = getColorForType(result.type);
                
                html += `
                    <div class="search-result-item" onclick="jumpToEvent('${result.date}', ${result.id})" style="border-left-color: ${color}">
                        <div class="search-result-icon" style="color: ${color}">
                            <i class="bi ${icon}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${result.title}</div>
                            <div class="search-result-meta">
                                <span><i class="bi bi-calendar3"></i> ${formatDate(result.date)}</span>
                                <span><i class="bi bi-clock"></i> ${result.time}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            dropdown.innerHTML = html;
            searchBox.appendChild(dropdown);
        }
        
        function displayNoResults(query) {
            let existingDropdown = document.getElementById('searchResultsDropdown');
            if (existingDropdown) {
                existingDropdown.remove();
            }
            
            const searchBox = document.querySelector('.search-box');
            const dropdown = document.createElement('div');
            dropdown.id = 'searchResultsDropdown';
            dropdown.className = 'search-results-dropdown';
            dropdown.innerHTML = `
                <div class="search-no-results">
                    <i class="bi bi-search"></i>
                    <p>No results found for "${query}"</p>
                </div>
            `;
            searchBox.appendChild(dropdown);
        }
        
        function displaySearchError() {
            let existingDropdown = document.getElementById('searchResultsDropdown');
            if (existingDropdown) {
                existingDropdown.remove();
            }
            
            const searchBox = document.querySelector('.search-box');
            const dropdown = document.createElement('div');
            dropdown.id = 'searchResultsDropdown';
            dropdown.className = 'search-results-dropdown';
            dropdown.innerHTML = `
                <div class="search-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>Error performing search. Please try again.</p>
                </div>
            `;
            searchBox.appendChild(dropdown);
        }
        
        function hideSearchResults() {
            const dropdown = document.getElementById('searchResultsDropdown');
            if (dropdown) {
                dropdown.remove();
            }
        }
        
        function jumpToEvent(eventDate, eventId) {
            hideSearchResults();
            document.getElementById('searchInput').value = '';
            
            // Navigate to the week containing this event
            const targetDate = new Date(eventDate);
            currentWeekStart = new Date(targetDate);
            currentWeekStart.setDate(targetDate.getDate() - targetDate.getDay());
            
            loadWeekView();
            
            // Highlight the event after a short delay
            setTimeout(() => {
                const eventCard = document.querySelector(`.event-card[data-event-id="${eventId}"]`);
                if (eventCard) {
                    eventCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    eventCard.classList.add('highlight-event');
                    setTimeout(() => {
                        eventCard.classList.remove('highlight-event');
                    }, 2000);
                }
            }, 500);
        }
        
        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Export currently loaded events (already filtered by current view date range).
        // If user typed something in the search box, we further filter exported rows by that query.
        function exportFilteredEvents() {
            try {
                const query = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();

                let eventsToExport = Array.isArray(calendarEvents) ? [...calendarEvents] : [];

                if (query.length >= 2) {
                    eventsToExport = eventsToExport.filter(e => {
                        const haystack = [
                            e.type,
                            e.title,
                            e.description,
                            e.status,
                            e.vehicle_type,
                            e.payment_method,
                            e.event_type
                        ].filter(Boolean).join(' ').toLowerCase();
                        return haystack.includes(query);
                    });
                }

                if (eventsToExport.length === 0) {
                    alert('No events to export for the current view/search.');
                    return;
                }

                // Sort by date+time for a clean export
                eventsToExport.sort((a, b) => {
                    const aKey = `${a.date || ''} ${a.time || ''}`;
                    const bKey = `${b.date || ''} ${b.time || ''}`;
                    return aKey.localeCompare(bKey);
                });

                const header = ['Date', 'Time', 'Type', 'Title', 'Description', 'Status', 'Amount'];
                const rows = [header];

                for (const e of eventsToExport) {
                    const amountNumber = (e.amount !== undefined && e.amount !== null && e.amount !== '')
                        ? Number(e.amount)
                        : NaN;
                    const amount = Number.isFinite(amountNumber) ? `₱${amountNumber.toFixed(2)}` : '';

                    rows.push([
                        e.date || '',
                        e.time || '',
                        e.type || '',
                        e.title || '',
                        e.description || '',
                        e.status || '',
                        amount
                    ]);
                }

                const filename = buildCalendarExportFilename(query);
                downloadCSV(rows, filename);
            } catch (err) {
                console.error('Export error:', err);
                alert('Export failed. Please try again.');
            }
        }

        function buildCalendarExportFilename(query) {
            // Create a filename that matches the current view range text
            const rangeText = (document.getElementById('dateRange')?.textContent || 'calendar').trim();
            const safeRange = rangeText.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '').toLowerCase();
            const safeQuery = query && query.length >= 2 ? `_search_${query.replace(/[^a-z0-9]+/gi, '_')}` : '';
            const ts = new Date();
            const pad = n => String(n).padStart(2, '0');
            const stamp = `${ts.getFullYear()}${pad(ts.getMonth() + 1)}${pad(ts.getDate())}_${pad(ts.getHours())}${pad(ts.getMinutes())}${pad(ts.getSeconds())}`;
            return `calendar_export_${safeRange}${safeQuery}_${stamp}.csv`;
        }

        function downloadCSV(rows, filename) {
            // RFC4180-ish CSV escaping
            const csv = rows.map(r => r.map(cell => {
                const s = String(cell ?? '');
                if (/[\n\r",]/.test(s)) {
                    return '"' + s.replace(/"/g, '""') + '"';
                }
                return s;
            }).join(',')).join('\r\n');

            // Add UTF-8 BOM for Excel compatibility
            const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();

            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }
        
        // Close search results on click outside
        document.addEventListener('click', function(e) {
            const searchBox = document.querySelector('.search-box');
            const searchInput = document.getElementById('searchInput');
            
            if (searchBox && !searchBox.contains(e.target)) {
                hideSearchResults();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                hideSearchResults();
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
            // View shortcuts
            if (e.key === 'w' && !e.target.matches('input')) {
                document.querySelector('.view-toggle:nth-child(2)').click();
            }
            if (e.key === 'm' && !e.target.matches('input')) {
                document.querySelector('.view-toggle:nth-child(3)').click();
            }
            if (e.key === 'd' && !e.target.matches('input')) {
                document.querySelector('.view-toggle:nth-child(4)').click();
            }
            if (e.key === 'y' && !e.target.matches('input')) {
                document.querySelector('.view-toggle:nth-child(1)').click();
            }
        });
    </script>
</body>
</html>