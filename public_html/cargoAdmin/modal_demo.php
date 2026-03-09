<?php
session_start();
require_once 'include/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Modal Variations Demo";
include 'include/header.php';
include 'include/sidebar.php';
?>

<link rel="stylesheet" href="include/modal-theme-standardized.css">

<style>
    .demo-section {
        background: white;
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .demo-section h2 {
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
    }
    .demo-button {
        margin: 10px 10px 10px 0;
    }
    .code-preview {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-top: 15px;
        font-family: 'Courier New', monospace;
        font-size: 0.85em;
        overflow-x: auto;
    }
    .color-box {
        display: inline-block;
        width: 30px;
        height: 30px;
        border-radius: 4px;
        vertical-align: middle;
        margin-right: 10px;
        border: 1px solid #dee2e6;
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-window-restore"></i> Modal Variations Demo</h1>
                <p class="text-muted">A comprehensive showcase of all standardized modal types and variations</p>
            </div>
        </div>

        <!-- 1. CONFIRMATION MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-check-circle"></i> 1. Confirmation Modals</h2>
            <p>Used for actions that require user confirmation before proceeding.</p>
            
            <button class="btn btn-success demo-button" data-bs-toggle="modal" data-bs-target="#confirmSuccessModal">
                <i class="fas fa-check"></i> Success Confirmation
            </button>
            <button class="btn btn-danger demo-button" data-bs-toggle="modal" data-bs-target="#confirmDangerModal">
                <i class="fas fa-trash"></i> Danger Confirmation
            </button>
            <button class="btn btn-warning demo-button" data-bs-toggle="modal" data-bs-target="#confirmWarningModal">
                <i class="fas fa-exclamation-triangle"></i> Warning Confirmation
            </button>
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                <i class="fas fa-info-circle"></i> Action Confirmation
            </button>

            <div class="code-preview">
&lt;!-- Success Confirmation Modal --&gt;
&lt;div class="modal fade" id="confirmSuccessModal"&gt;
    &lt;div class="modal-dialog"&gt;
        &lt;div class="modal-content modal-success"&gt;
            &lt;div class="modal-header"&gt;
                &lt;h5 class="modal-title"&gt;&lt;i class="fas fa-check-circle"&gt;&lt;/i&gt; Confirm Action&lt;/h5&gt;
                &lt;button type="button" class="btn-close" data-bs-dismiss="modal"&gt;&lt;/button&gt;
            &lt;/div&gt;
            &lt;div class="modal-body"&gt;
                &lt;p&gt;Are you sure you want to approve this request?&lt;/p&gt;
            &lt;/div&gt;
            &lt;div class="modal-footer"&gt;
                &lt;button type="button" class="btn btn-secondary" data-bs-dismiss="modal"&gt;Cancel&lt;/button&gt;
                &lt;button type="button" class="btn btn-success"&gt;Confirm&lt;/button&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;
            </div>
        </div>

        <!-- 2. FORM MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-edit"></i> 2. Form Modals</h2>
            <p>Used for data entry and editing operations.</p>
            
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#formBasicModal">
                <i class="fas fa-plus"></i> Basic Form
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#formLargeModal">
                <i class="fas fa-file-alt"></i> Large Form
            </button>

            <div class="code-preview">
&lt;!-- Form Modal --&gt;
&lt;div class="modal fade" id="formBasicModal"&gt;
    &lt;div class="modal-dialog"&gt;
        &lt;div class="modal-content"&gt;
            &lt;div class="modal-header"&gt;
                &lt;h5 class="modal-title"&gt;Add New Item&lt;/h5&gt;
                &lt;button type="button" class="btn-close" data-bs-dismiss="modal"&gt;&lt;/button&gt;
            &lt;/div&gt;
            &lt;div class="modal-body"&gt;
                &lt;form id="itemForm"&gt;
                    &lt;div class="mb-3"&gt;
                        &lt;label class="form-label"&gt;Item Name&lt;/label&gt;
                        &lt;input type="text" class="form-control" required&gt;
                    &lt;/div&gt;
                &lt;/form&gt;
            &lt;/div&gt;
            &lt;div class="modal-footer"&gt;
                &lt;button type="button" class="btn btn-secondary" data-bs-dismiss="modal"&gt;Cancel&lt;/button&gt;
                &lt;button type="submit" form="itemForm" class="btn btn-primary"&gt;Save&lt;/button&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;
            </div>
        </div>

        <!-- 3. INFORMATION MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-info-circle"></i> 3. Information Modals</h2>
            <p>Used for displaying detailed information without user input.</p>
            
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#infoDetailsModal">
                <i class="fas fa-eye"></i> View Details
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#infoStatsModal">
                <i class="fas fa-chart-bar"></i> View Statistics
            </button>

            <div class="code-preview">
&lt;!-- Information Modal --&gt;
&lt;div class="modal fade" id="infoDetailsModal"&gt;
    &lt;div class="modal-dialog modal-lg"&gt;
        &lt;div class="modal-content"&gt;
            &lt;div class="modal-header"&gt;
                &lt;h5 class="modal-title"&gt;Booking Details&lt;/h5&gt;
                &lt;button type="button" class="btn-close" data-bs-dismiss="modal"&gt;&lt;/button&gt;
            &lt;/div&gt;
            &lt;div class="modal-body"&gt;
                &lt;div class="info-grid"&gt;
                    &lt;div class="info-item"&gt;
                        &lt;span class="info-label"&gt;Booking ID:&lt;/span&gt;
                        &lt;span class="info-value"&gt;#12345&lt;/span&gt;
                    &lt;/div&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;div class="modal-footer"&gt;
                &lt;button type="button" class="btn btn-secondary" data-bs-dismiss="modal"&gt;Close&lt;/button&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;
            </div>
        </div>

        <!-- 4. ACTION MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-cogs"></i> 4. Action Modals</h2>
            <p>Modals with multiple action buttons in grid layout.</p>
            
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#actionGridModal">
                <i class="fas fa-th"></i> Action Grid
            </button>
            <button class="btn btn-secondary demo-button" data-bs-toggle="modal" data-bs-target="#actionListModal">
                <i class="fas fa-list"></i> Action List
            </button>

            <div class="code-preview">
&lt;!-- Action Grid Modal --&gt;
&lt;div class="modal fade" id="actionGridModal"&gt;
    &lt;div class="modal-dialog"&gt;
        &lt;div class="modal-content"&gt;
            &lt;div class="modal-header"&gt;
                &lt;h5 class="modal-title"&gt;Choose Action&lt;/h5&gt;
                &lt;button type="button" class="btn-close" data-bs-dismiss="modal"&gt;&lt;/button&gt;
            &lt;/div&gt;
            &lt;div class="modal-body"&gt;
                &lt;div class="action-grid"&gt;
                    &lt;button class="action-btn"&gt;&lt;i class="fas fa-check"&gt;&lt;/i&gt; Approve&lt;/button&gt;
                    &lt;button class="action-btn"&gt;&lt;i class="fas fa-times"&gt;&lt;/i&gt; Reject&lt;/button&gt;
                &lt;/div&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;
            </div>
        </div>

        <!-- 5. ALERT MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-bell"></i> 5. Alert Modals</h2>
            <p>System alerts and notifications with appropriate styling.</p>
            
            <button class="btn btn-success demo-button" data-bs-toggle="modal" data-bs-target="#alertSuccessModal">
                <i class="fas fa-check-circle"></i> Success Alert
            </button>
            <button class="btn btn-danger demo-button" data-bs-toggle="modal" data-bs-target="#alertErrorModal">
                <i class="fas fa-exclamation-circle"></i> Error Alert
            </button>
            <button class="btn btn-warning demo-button" data-bs-toggle="modal" data-bs-target="#alertWarningModal">
                <i class="fas fa-exclamation-triangle"></i> Warning Alert
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#alertInfoModal">
                <i class="fas fa-info-circle"></i> Info Alert
            </button>
        </div>

        <!-- 6. IMAGE/MEDIA MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-image"></i> 6. Image & Media Modals</h2>
            <p>For displaying images, galleries, and media content.</p>
            
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#imageViewerModal">
                <i class="fas fa-camera"></i> Image Viewer
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#galleryModal">
                <i class="fas fa-images"></i> Image Gallery
            </button>
        </div>

        <!-- 7. SPECIAL MODALS -->
        <div class="demo-section">
            <h2><i class="fas fa-star"></i> 7. Special Purpose Modals</h2>
            <p>Specialized modals for specific use cases.</p>
            
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-money-bill"></i> Payment Modal
            </button>
            <button class="btn btn-success demo-button" data-bs-toggle="modal" data-bs-target="#verificationModal">
                <i class="fas fa-shield-alt"></i> Verification Modal
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#documentModal">
                <i class="fas fa-file-pdf"></i> Document Viewer
            </button>
        </div>

        <!-- 8. MODAL SIZES -->
        <div class="demo-section">
            <h2><i class="fas fa-expand-arrows-alt"></i> 8. Modal Sizes</h2>
            <p>Different modal sizes for various content requirements.</p>
            
            <button class="btn btn-secondary demo-button" data-bs-toggle="modal" data-bs-target="#modalSmall">
                <i class="fas fa-compress"></i> Small
            </button>
            <button class="btn btn-primary demo-button" data-bs-toggle="modal" data-bs-target="#modalDefault">
                <i class="fas fa-window-maximize"></i> Default
            </button>
            <button class="btn btn-info demo-button" data-bs-toggle="modal" data-bs-target="#modalLarge">
                <i class="fas fa-expand"></i> Large
            </button>
            <button class="btn btn-dark demo-button" data-bs-toggle="modal" data-bs-target="#modalXLarge">
                <i class="fas fa-expand-arrows-alt"></i> Extra Large
            </button>
            <button class="btn btn-warning demo-button" data-bs-toggle="modal" data-bs-target="#modalFullscreen">
                <i class="fas fa-desktop"></i> Fullscreen
            </button>
        </div>

        <!-- COLOR REFERENCE -->
        <div class="demo-section">
            <h2><i class="fas fa-palette"></i> 9. Color Theme Reference</h2>
            <p>Standardized color scheme for modal variants.</p>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h5>Modal Types</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="color-box" style="background: #d4edda;"></span> <code>.modal-success</code> - Success/Approval actions</li>
                        <li class="mb-2"><span class="color-box" style="background: #f8d7da;"></span> <code>.modal-danger</code> - Destructive/Delete actions</li>
                        <li class="mb-2"><span class="color-box" style="background: #fff3cd;"></span> <code>.modal-warning</code> - Warning/Caution actions</li>
                        <li class="mb-2"><span class="color-box" style="background: #d1ecf1;"></span> <code>.modal-info</code> - Informational content</li>
                    </ul>
                </div>
                <div class="col-md-6 mb-3">
                    <h5>Special Types</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="color-box" style="background: #e7f3ff;"></span> <code>.modal-payment</code> - Payment processing</li>
                        <li class="mb-2"><span class="color-box" style="background: #f0e6ff;"></span> <code>.modal-verification</code> - User verification</li>
                        <li class="mb-2"><span class="color-box" style="background: #fff5e6;"></span> <code>.modal-document</code> - Document viewing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     MODAL DEFINITIONS
     ======================================== -->

<!-- 1. CONFIRMATION MODALS -->

<!-- Success Confirmation Modal -->
<div class="modal fade" id="confirmSuccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-success">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Confirm Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this request?</p>
                <p class="text-muted small">This action will notify the user and update the status.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="alert('Approved!')">
                    <i class="fas fa-check"></i> Confirm Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Danger Confirmation Modal -->
<div class="modal fade" id="confirmDangerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-danger">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Warning:</strong> This action cannot be undone!</p>
                <p>Are you sure you want to delete this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="alert('Deleted!')">
                    <i class="fas fa-trash"></i> Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Warning Confirmation Modal -->
<div class="modal fade" id="confirmWarningModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-warning">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Proceed with Caution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This action may have unintended consequences.</p>
                <p>Do you want to continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="alert('Proceeded!')">
                    <i class="fas fa-forward"></i> Continue Anyway
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Action Confirmation Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please confirm that you want to proceed with this action.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="alert('Confirmed!')">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 2. FORM MODALS -->

<!-- Basic Form Modal -->
<div class="modal fade" id="formBasicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="basicForm">
                    <div class="mb-3">
                        <label for="itemName" class="form-label">Item Name *</label>
                        <input type="text" class="form-control" id="itemName" required>
                    </div>
                    <div class="mb-3">
                        <label for="itemDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="itemDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="itemCategory" class="form-label">Category *</label>
                        <select class="form-select" id="itemCategory" required>
                            <option value="">Select category...</option>
                            <option value="1">Category 1</option>
                            <option value="2">Category 2</option>
                            <option value="3">Category 3</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="basicForm" class="btn btn-primary" onclick="alert('Form submitted!')">
                    <i class="fas fa-save"></i> Save Item
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Large Form Modal -->
<div class="modal fade" id="formLargeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Detailed Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="largeForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" class="form-control">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="largeForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 3. INFORMATION MODALS -->

<!-- Details Modal -->
<div class="modal fade" id="infoDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Booking ID:</span>
                        <span class="info-value">#12345</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Customer:</span>
                        <span class="info-value">John Doe</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Vehicle:</span>
                        <span class="info-value">Toyota Camry 2023</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Start Date:</span>
                        <span class="info-value">2024-02-15</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">End Date:</span>
                        <span class="info-value">2024-02-20</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Amount:</span>
                        <span class="info-value">₱5,000.00</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><span class="badge bg-success">Active</span></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Status:</span>
                        <span class="info-value"><span class="badge bg-info">Paid</span></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="infoStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-bar"></i> Statistics Overview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary">150</h3>
                                <p class="mb-0">Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success">120</h3>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning">20</h3>
                                <p class="mb-0">Active</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-danger">10</h3>
                                <p class="mb-0">Cancelled</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- 4. ACTION MODALS -->

<!-- Action Grid Modal -->
<div class="modal fade" id="actionGridModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cogs"></i> Choose Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Select an action for booking #12345</p>
                <div class="action-grid">
                    <button class="action-btn btn-success" onclick="alert('Approved!')">
                        <i class="fas fa-check-circle"></i>
                        <span>Approve</span>
                    </button>
                    <button class="action-btn btn-danger" onclick="alert('Rejected!')">
                        <i class="fas fa-times-circle"></i>
                        <span>Reject</span>
                    </button>
                    <button class="action-btn btn-warning" onclick="alert('On Hold!')">
                        <i class="fas fa-pause-circle"></i>
                        <span>Hold</span>
                    </button>
                    <button class="action-btn btn-info" onclick="alert('Details!')">
                        <i class="fas fa-info-circle"></i>
                        <span>Details</span>
                    </button>
                    <button class="action-btn btn-primary" onclick="alert('Edited!')">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </button>
                    <button class="action-btn btn-secondary" onclick="alert('Cancelled!')">
                        <i class="fas fa-ban"></i>
                        <span>Cancel</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action List Modal -->
<div class="modal fade" id="actionListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list"></i> Available Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" onclick="alert('Viewed!')">
                        <i class="fas fa-eye text-primary"></i> View Details
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="alert('Edited!')">
                        <i class="fas fa-edit text-info"></i> Edit Information
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="alert('Downloaded!')">
                        <i class="fas fa-download text-success"></i> Download Report
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="alert('Shared!')">
                        <i class="fas fa-share text-warning"></i> Share
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-danger" onclick="alert('Deleted!')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. ALERT MODALS -->

<!-- Success Alert Modal -->
<div class="modal fade" id="alertSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-success">
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h4>Success!</h4>
                <p>Your operation has been completed successfully.</p>
                <button type="button" class="btn btn-success mt-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Alert Modal -->
<div class="modal fade" id="alertErrorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-danger">
            <div class="modal-body text-center py-4">
                <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                <h4>Error Occurred</h4>
                <p>Something went wrong. Please try again.</p>
                <p class="text-muted small">Error Code: ERR_500</p>
                <button type="button" class="btn btn-danger mt-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Warning Alert Modal -->
<div class="modal fade" id="alertWarningModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-warning">
            <div class="modal-body text-center py-4">
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h4>Warning</h4>
                <p>Please review the following before proceeding.</p>
                <div class="alert alert-warning text-start">
                    <ul class="mb-0">
                        <li>This action may affect other records</li>
                        <li>Changes cannot be easily undone</li>
                    </ul>
                </div>
                <button type="button" class="btn btn-warning mt-3" data-bs-dismiss="modal">Understood</button>
            </div>
        </div>
    </div>
</div>

<!-- Info Alert Modal -->
<div class="modal fade" id="alertInfoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-info">
            <div class="modal-body text-center py-4">
                <i class="fas fa-info-circle fa-4x text-info mb-3"></i>
                <h4>Information</h4>
                <p>Here's some important information you should know.</p>
                <div class="alert alert-info text-start">
                    The system will undergo maintenance on Sunday, 2:00 AM - 4:00 AM.
                </div>
                <button type="button" class="btn btn-info mt-3" data-bs-dismiss="modal">Got It</button>
            </div>
        </div>
    </div>
</div>

<!-- 6. IMAGE/MEDIA MODALS -->

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-image"></i> Image Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img src="https://via.placeholder.com/800x600/3498db/ffffff?text=Vehicle+Image" 
                     class="img-fluid" alt="Vehicle Image">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Gallery Modal -->
<div class="modal fade" id="galleryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-images"></i> Image Gallery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/3498db/ffffff?text=Image+1" 
                             class="img-fluid rounded" alt="Image 1">
                    </div>
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/2ecc71/ffffff?text=Image+2" 
                             class="img-fluid rounded" alt="Image 2">
                    </div>
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/e74c3c/ffffff?text=Image+3" 
                             class="img-fluid rounded" alt="Image 3">
                    </div>
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/f39c12/ffffff?text=Image+4" 
                             class="img-fluid rounded" alt="Image 4">
                    </div>
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/9b59b6/ffffff?text=Image+5" 
                             class="img-fluid rounded" alt="Image 5">
                    </div>
                    <div class="col-md-4">
                        <img src="https://via.placeholder.com/300x200/1abc9c/ffffff?text=Image+6" 
                             class="img-fluid rounded" alt="Image 6">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download All
                </button>
            </div>
        </div>
    </div>
</div>


<!-- 7. SPECIAL PURPOSE MODALS -->

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-payment">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Payment Processing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Total Amount: <strong>₱5,000.00</strong>
                </div>
                <form id="paymentForm">
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" required>
                            <option value="">Select payment method...</option>
                            <option value="gcash">GCash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" placeholder="Enter reference number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Proof of Payment</label>
                        <input type="file" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="paymentForm" class="btn btn-primary">
                    <i class="fas fa-check"></i> Submit Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-verification">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt"></i> User Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6>Personal Information</h6>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value">John Doe</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ID Type:</span>
                                <span class="info-value">Driver's License</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ID Number:</span>
                                <span class="info-value">N01-12-123456</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6>Submitted Documents</h6>
                        <div class="text-center">
                            <img src="https://via.placeholder.com/250x150/6c757d/ffffff?text=ID+Photo" 
                                 class="img-fluid rounded mb-2" alt="ID Photo">
                            <img src="https://via.placeholder.com/250x150/6c757d/ffffff?text=Selfie" 
                                 class="img-fluid rounded" alt="Selfie">
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Please verify all information carefully before approval.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button type="button" class="btn btn-warning">
                    <i class="fas fa-clock"></i> Request More Info
                </button>
                <button type="button" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Document Viewer Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modal-document">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-pdf"></i> Document Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light text-center p-5" style="min-height: 500px;">
                    <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                    <h4>Contract Agreement</h4>
                    <p class="text-muted">Document preview would appear here</p>
                    <div class="btn-group mt-3">
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-search-minus"></i> Zoom Out
                        </button>
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-search-plus"></i> Zoom In
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button type="button" class="btn btn-success">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 8. MODAL SIZES -->

<!-- Small Modal -->
<div class="modal fade" id="modalSmall" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Small Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This is a small modal dialog.</p>
                <p class="text-muted small">Best for quick confirmations.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Default Modal -->
<div class="modal fade" id="modalDefault" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Default Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This is the default modal size.</p>
                <p class="text-muted">Standard width, suitable for most content.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Large Modal -->
<div class="modal fade" id="modalLarge" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Large Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This is a large modal dialog.</p>
                <p class="text-muted">Provides more space for complex content like forms and tables.</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Item 1</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>₱1,000</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Item 2</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>₱2,000</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Extra Large Modal -->
<div class="modal fade" id="modalXLarge" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extra Large Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This is an extra large modal dialog.</p>
                <p class="text-muted">Maximum width for dashboard-style content and data visualization.</p>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="text-primary">150</h5>
                                <p class="mb-0 small">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="text-success">120</h5>
                                <p class="mb-0 small">Active</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="text-warning">20</h5>
                                <p class="mb-0 small">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="text-danger">10</h5>
                                <p class="mb-0 small">Cancelled</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen Modal -->
<div class="modal fade" id="modalFullscreen" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fullscreen Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This modal takes up the entire screen - perfect for immersive experiences!
                    </div>
                    <h3>Fullscreen Content Area</h3>
                    <p>Use this for complex workflows, multi-step processes, or when you need maximum screen real estate.</p>
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Section 1</h5>
                                </div>
                                <div class="card-body">
                                    Content for section 1
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Section 2</h5>
                                </div>
                                <div class="card-body">
                                    Content for section 2
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Section 3</h5>
                                </div>
                                <div class="card-body">
                                    Content for section 3
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
