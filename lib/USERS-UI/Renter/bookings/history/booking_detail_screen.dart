// lib/USERS-UI/Renter/bookings/history/booking_detail_screen.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:cargo/config/api_config.dart';
import 'package:cargo/USERS-UI/Renter/models/booking.dart';
import 'package:cargo/USERS-UI/services/booking_service.dart';
import 'package:cargo/USERS-UI/Renter/payments/payment_status_tracker.dart';
import 'package:cargo/USERS-UI/Renter/payments/receipt_viewer_screen.dart';
import 'package:cargo/USERS-UI/Renter/payments/refund_request_screen.dart';
import 'package:cargo/USERS-UI/Renter/bookings/history/live_trip_tracker_screen.dart';
import 'package:cargo/USERS-UI/services/renter_gps_service.dart';
import 'package:cargo/USERS-UI/widgets/location_permission_helper.dart';
import 'package:cargo/USERS-UI/models/overdue_booking.dart';
import 'package:cargo/USERS-UI/services/overdue_service.dart';
import 'package:cargo/USERS-UI/widgets/overdue_badge.dart';
import 'package:cargo/USERS-UI/Renter/payments/late_fee_payment_screen.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:cargo/USERS-UI/Renter/chats/chat_detail_screen.dart';
import 'package:cargo/USERS-UI/Renter/insurance/insurance_policy_screen.dart';
import 'package:cargo/widgets/loading_widgets.dart';
import 'package:cargo/widgets/optimized_network_image.dart';

class BookingDetailScreen extends StatefulWidget {
  final Booking booking;
  final String status;

  const BookingDetailScreen({
    super.key,
    required this.booking,
    required this.status,
  });

  @override
  State<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends State<BookingDetailScreen> {
  Widget _buildLateFeePaymentStatusCard() {
    final booking = _lateFeeStatusData is Map ? _lateFeeStatusData!['booking'] : null;
    final lateFeePayment = _lateFeeStatusData is Map ? _lateFeeStatusData!['late_fee_payment'] : null;

    // If the endpoint hasn't been called yet, show a lightweight placeholder while loading.
    if (_isLoadingLateFeeStatus) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Row(
          children: [
            SizedBox(
              height: 18,
              width: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.red[700]),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Loading late fee payment status…',
                style: GoogleFonts.inter(fontSize: 13, color: Colors.grey[700]),
              ),
            ),
          ],
        ),
      );
    }

    // If not available (e.g., user not logged in), don't take space.
    if (_lateFeeStatusData == null || userId == null) {
      return const SizedBox.shrink();
    }

    // If API returned success=false, still show a neutral card (avoid confusing blank UI).
    final bool success = _lateFeeStatusData!['success'] == true;

    String statusLabel = 'Not submitted';
    Color statusColor = Colors.grey.shade700;
    IconData statusIcon = Icons.info_outline;

    if (success && booking != null) {
      final bookingStatus = (booking['late_fee_payment_status'] ?? booking['late_fee_payment_status'.toString()])
          ?.toString()
          .toLowerCase();
      final paymentStatus = lateFeePayment != null ? (lateFeePayment['payment_status']?.toString().toLowerCase()) : null;

      // Prefer the late_fee_payments table status if present; otherwise use booking field.
      final normalized = (paymentStatus ?? bookingStatus ?? '').toLowerCase();

      if (normalized == 'pending') {
        statusLabel = 'Pending verification';
        statusColor = Colors.orange.shade800;
        statusIcon = Icons.hourglass_top;
      } else if (normalized == 'verified' || normalized == 'paid') {
        statusLabel = 'Verified';
        statusColor = Colors.green.shade700;
        statusIcon = Icons.check_circle_outline;
      } else if (normalized == 'rejected') {
        statusLabel = 'Rejected';
        statusColor = Colors.red.shade700;
        statusIcon = Icons.cancel_outlined;
      } else if (normalized.isNotEmpty) {
        statusLabel = normalized;
        statusColor = Colors.blueGrey;
        statusIcon = Icons.info_outline;
      }
    }

    final String? ref = lateFeePayment != null ? lateFeePayment['payment_reference']?.toString() : null;
    final String? amount = lateFeePayment != null ? lateFeePayment['total_amount']?.toString() : null;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.receipt_long, color: Colors.red[700]),
              const SizedBox(width: 10),
              Text(
                'Late Fee Payment Status',
                style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              const Spacer(),
              Icon(statusIcon, color: statusColor, size: 20),
              const SizedBox(width: 6),
              Text(
                statusLabel,
                style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, color: statusColor),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (ref != null && ref.isNotEmpty)
            Text(
              'Reference: $ref',
              style: GoogleFonts.inter(fontSize: 13, color: Colors.grey[800]),
            ),
          if (amount != null && amount.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Submitted amount: ₱$amount',
                style: GoogleFonts.inter(fontSize: 13, color: Colors.grey[800]),
              ),
            ),
          if (!success)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                'Unable to load late fee status right now. Please try again later.',
                style: GoogleFonts.inter(fontSize: 12, color: Colors.grey[600]),
              ),
            ),
        ],
      ),
    );
  }

  String? userId;
  bool _isLoading = true;
  bool _isLoadingPayment = true;
  Map<String, dynamic>? _paymentData;
  bool _bookingChanged = false;
  
  // GPS Tracking
  final RenterGpsService _gpsService = renterGpsService;
  bool _isGpsTracking = false;

  // Overdue tracking
  final OverdueService _overdueService = OverdueService();
  OverdueBooking? _overdueInfo;
  bool _isCheckingOverdue = false;

  // Late fee payment status (separate from normal booking payment)
  bool _isLoadingLateFeeStatus = false;
  Map<String, dynamic>? _lateFeeStatusData;

  // Damage report filed by owner against this booking
  Map<String, dynamic>? _damageReport;

  final String baseUrl = "${GlobalApiConfig.baseUrl}/";

  @override
  void initState() {
    super.initState();
    debugPrint('🚀 BookingDetailScreen initState - Booking ID: ${widget.booking.bookingId}, Status: ${widget.booking.status}');
    _loadUserIdAndPayment();
    _checkAndStartGpsTracking();
    _checkOverdueStatus();
  }

  Future<void> _checkOverdueStatus() async {
    if (_isCheckingOverdue) return;
    
    // Only check for active/approved bookings
    if (widget.booking.status.toLowerCase() != 'approved' && 
        widget.status.toLowerCase() != 'active') {
      return;
    }
    
    setState(() => _isCheckingOverdue = true);
    
    try {
      // First, check using API
      final overdueInfo = await _overdueService.checkBookingOverdue(widget.booking.bookingId);
      
      // Fallback: Calculate client-side if API returns null but booking is actually overdue
      if (overdueInfo == null) {
        final isOverdueLocal = _overdueService.isBookingOverdueLocal(
          widget.booking.returnDate, 
          widget.booking.returnTime
        );
        
        if (isOverdueLocal) {
          final hoursOverdue = _overdueService.calculateHoursOverdue(
            widget.booking.returnDate,
            widget.booking.returnTime
          );
          
          final lateFee = _overdueService.calculateLateFee(hoursOverdue);
          final daysOverdue = (hoursOverdue / 24).floor();
          
          // Create a temporary overdue booking object for display
          if (mounted) {
            setState(() {
              _overdueInfo = OverdueBooking(
                bookingId: widget.booking.bookingId,
                userId: 0, // Unknown from client
                ownerId: 0,
                renterName: '',
                renterContact: '',
                ownerName: '',
                ownerContact: '',
                vehicleName: widget.booking.carName,
                vehicleImage: widget.booking.carImage,
                returnDate: DateTime.tryParse(widget.booking.returnDate) ?? DateTime.now(),
                returnTime: widget.booking.returnTime,
                overdueStatus: hoursOverdue > 72 ? 'severely_overdue' : 'overdue',
                daysOverdue: daysOverdue,
                hoursOverdue: hoursOverdue,
                lateFeeAmount: lateFee,
                lateFeeCharged: false,
                totalAmount: double.tryParse(widget.booking.totalPrice.replaceAll(',', '')) ?? 0, // Rental fee
                totalDue: lateFee, // FIXED: Only late fee is due (rental already paid)
                isRentalPaid: true, // FIXED: Assume rental is paid for approved bookings
              );
              _isCheckingOverdue = false;
            });
            
            debugPrint('⚠️ Booking is overdue (client-side detection): $hoursOverdue hours, Fee: ₱$lateFee');
          }
          return;
        }
      }
      
      if (mounted) {
        setState(() {
          _overdueInfo = overdueInfo;
          _isCheckingOverdue = false;
        });
        
        if (overdueInfo != null) {
          debugPrint('✅ Overdue info loaded from API: ${overdueInfo.hoursOverdue} hours, Fee: ₱${overdueInfo.lateFeeAmount}');
        }
      }
    } catch (e) {
      debugPrint('Error checking overdue status: $e');
      if (mounted) {
        setState(() => _isCheckingOverdue = false);
      }
    }
  }

  @override
  void dispose() {
    // Only stop GPS if booking is not active anymore
    if (widget.booking.status.toLowerCase() != 'approved') {
      _gpsService.stopTracking();
    }
    super.dispose();
  }

  Future<void> _checkAndStartGpsTracking() async {
    // Start GPS only for approved/active bookings
    if (widget.booking.status.toLowerCase() == 'approved' ||
        widget.status.toLowerCase() == 'active') {
      await _startGpsTracking();
    }
  }

  Future<void> _startGpsTracking() async {
    debugPrint('🚀 Checking GPS tracking for booking: ${widget.booking.bookingId}');

    // Check if already tracking this booking
    if (_gpsService.isTracking && 
        _gpsService.activeBookingId == widget.booking.bookingId.toString()) {
      debugPrint('✓ Already tracking this booking');
      setState(() => _isGpsTracking = true);
      return;
    }

    // Request permissions
    final hasPermission = await LocationPermissionHelper.showPermissionDialog(context);
    
    if (!hasPermission) {
      debugPrint('❌ Location permission not granted');
      return;
    }

    // Start tracking
    final success = await _gpsService.startTracking(widget.booking.bookingId.toString());

    if (mounted) {
      setState(() => _isGpsTracking = success);

      if (success) {
        debugPrint('✅ GPS tracking started successfully');
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.location_on, color: Colors.white),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'GPS tracking started for your rental',
                    style: GoogleFonts.inter(),
                  ),
                ),
              ],
            ),
            backgroundColor: Colors.green.shade600,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  void _stopGpsTracking() {
    debugPrint('🛑 Stopping GPS tracking');
    _gpsService.stopTracking();
    
    if (mounted) {
      setState(() => _isGpsTracking = false);
    }
  }

  Future<void> _loadUserIdAndPayment() async {
    debugPrint('📱 _loadUserIdAndPayment called');
    final prefs = await SharedPreferences.getInstance();
    final loadedUserId = prefs.getString('user_id');

    debugPrint('👤 User ID: $loadedUserId');

    setState(() {
      userId = loadedUserId;
      _isLoading = false;
    });

    // Fetch both the normal booking payment info and the late-fee-specific payment status
    debugPrint('💳 About to call _fetchPaymentInfo...');
    await _fetchPaymentInfo();
    await _fetchLateFeeStatus();
    await _fetchDamageReport();
    debugPrint('✅ _fetchPaymentInfo completed');
  }

  Future<void> _fetchLateFeeStatus() async {
    if (userId == null || userId!.isEmpty) return;
    if (_isLoadingLateFeeStatus) return;

    setState(() => _isLoadingLateFeeStatus = true);

    try {
      final url =
          '${baseUrl}api/payment/get_renter_late_fee_status.php?booking_id=${widget.booking.bookingId}&user_id=$userId';

      final response = await http.get(Uri.parse(url));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (mounted) {
          setState(() {
            _lateFeeStatusData = data;
          });
        }
      }
    } catch (e) {
      debugPrint('Error fetching late fee status: $e');
    } finally {
      if (mounted) {
        setState(() => _isLoadingLateFeeStatus = false);
      }
    }
  }

  Future<void> _fetchDamageReport() async {
    if (userId == null || userId!.isEmpty) return;
    try {
      final uri = Uri.parse(
        '${GlobalApiConfig.getRenterDamageReportEndpoint}'
        '?booking_id=${widget.booking.bookingId}&renter_id=$userId',
      );
      final response = await http.get(uri).timeout(GlobalApiConfig.apiTimeout);
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (mounted && data['success'] == true) {
          setState(() => _damageReport = data['report']);
        }
      }
    } catch (e) {
      debugPrint('Error fetching damage report: $e');
    }
  }

  Widget _buildDamageReportStatusCard() {
    final report = _damageReport;
    if (report == null) return const SizedBox.shrink();

    final status = report['status'] as String? ?? 'pending';
    final estimatedCost = double.tryParse(report['estimated_cost'].toString()) ?? 0;
    final approvedAmount = double.tryParse(report['approved_amount']?.toString() ?? '0') ?? 0;
    final adminNotes = report['admin_notes'] as String?;
    final ownerName = report['owner_name'] as String? ?? 'Owner';
    final createdAt = report['created_at'] as String? ?? '';

    Color statusColor;
    IconData statusIcon;
    String statusLabel;
    Color bgColor;
    Color borderColor;

    switch (status) {
      case 'approved':
        statusColor = Colors.red.shade700;
        statusIcon = Icons.gavel;
        statusLabel = 'Approved — Deduction Applied';
        bgColor = Colors.red.shade50;
        borderColor = Colors.red.shade200;
        break;
      case 'rejected':
        statusColor = Colors.green.shade700;
        statusIcon = Icons.check_circle_outline;
        statusLabel = 'Dismissed — No Deduction';
        bgColor = Colors.green.shade50;
        borderColor = Colors.green.shade200;
        break;
      default:
        statusColor = Colors.orange.shade700;
        statusIcon = Icons.hourglass_top;
        statusLabel = 'Under Review';
        bgColor = Colors.orange.shade50;
        borderColor = Colors.orange.shade200;
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.car_crash, color: statusColor, size: 20),
              const SizedBox(width: 8),
              Text(
                'Damage Report Filed',
                style: GoogleFonts.outfit(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: statusColor,
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(statusIcon, size: 12, color: statusColor),
                    const SizedBox(width: 4),
                    Text(
                      statusLabel,
                      style: GoogleFonts.inter(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: statusColor,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Filed by $ownerName on ${createdAt.split(' ').first}',
            style: GoogleFonts.inter(fontSize: 12, color: Colors.grey.shade600),
          ),
          const SizedBox(height: 8),
          _damageInfoRow('Estimated Cost', '₱${estimatedCost.toStringAsFixed(2)}'),
          if (status == 'approved')
            _damageInfoRow(
              'Deducted from Deposit',
              '₱${approvedAmount.toStringAsFixed(2)}',
              valueColor: Colors.red.shade700,
              bold: true,
            ),
          if (adminNotes != null && adminNotes.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'Admin Notes:',
              style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade700),
            ),
            const SizedBox(height: 2),
            Text(
              adminNotes,
              style: GoogleFonts.inter(fontSize: 13, color: Colors.grey.shade800),
            ),
          ],
          if (status == 'pending') ...[
            const SizedBox(height: 8),
            Text(
              'Our admin team is reviewing the report. You will be notified of the outcome.',
              style: GoogleFonts.inter(fontSize: 12, color: Colors.orange.shade800),
            ),
          ],
        ],
      ),
    );
  }

  Widget _damageInfoRow(String label, String value, {Color? valueColor, bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: GoogleFonts.inter(fontSize: 13, color: Colors.grey.shade700)),
          Text(
            value,
            style: GoogleFonts.inter(
              fontSize: 13,
              fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
              color: valueColor ?? Colors.grey.shade900,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _fetchPaymentInfo() async {
    debugPrint('💳💳💳 _fetchPaymentInfo STARTED for booking ${widget.booking.bookingId}');
    setState(() => _isLoadingPayment = true);

    try {
      final url = '${baseUrl}api/payment/get_booking_payment.php?booking_id=${widget.booking.bookingId}';
      debugPrint('🌐 Making request to: $url');
      
      final response = await http.get(Uri.parse(url));

      debugPrint('📡 Response Status Code: ${response.statusCode}');
      debugPrint('📡 Response Body: ${response.body}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        debugPrint('✅ Decoded JSON: $data');
        
        if (data['success'] == true && data['payment'] != null) {
          debugPrint('💳 Payment Data Found: ${data['payment']}');
          debugPrint('💳 Refund Status from API: ${data['payment']['refund_status']}');
          debugPrint('💳 Refund Requested: ${data['payment']['refund_requested']}');
          
          setState(() {
            _paymentData = data['payment'];
            debugPrint('✅ _paymentData SET in state');
          });
        } else {
          debugPrint('❌ API returned success=false or payment is null');
        }
      } else {
        debugPrint('❌ API returned non-200 status');
      }
    } catch (e) {
      debugPrint('❌❌❌ Error fetching payment info: $e');
    } finally {
      if (mounted) {
        setState(() => _isLoadingPayment = false);
        debugPrint('✅ _isLoadingPayment = false');
      }
    }
  }

  String _getStatusText() {
    switch (widget.booking.status.toLowerCase()) {
      case 'approved':
        return 'Active';
      case 'pending':
        return 'Pending Approval';
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      case 'rejected':
        return 'Rejected';
      default:
        return widget.booking.status;
    }
  }

  Color _getStatusColor() {
    switch (widget.booking.status) {
      case 'approved':
        return Theme.of(context).colorScheme.primary;
      case 'pending':
        return Theme.of(context).colorScheme.tertiary;
      case 'completed':
        return Colors.grey;
      case 'cancelled':
        return Colors.red.shade400;
      case 'rejected':
        return Colors.red.shade700;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
   
    if (_isLoading) {
      return Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        body: const LoadingScreen(message: 'Loading booking details...'),
      );
    }

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(context),
          SliverToBoxAdapter(child: _buildContent(context)),
        ],
      ),
      bottomNavigationBar: _buildBottomBar(context),
    );
  }

  SliverAppBar _buildAppBar(BuildContext context) {
    return SliverAppBar(
      expandedHeight: 300,
      pinned: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      leading: IconButton(
        icon: _circleIcon(Icons.arrow_back),
        onPressed: () => Navigator.pop(context, _bookingChanged),
      ),
      actions: [
        // GPS Status Indicator for Active Bookings
        if (widget.status.toLowerCase() == 'active')
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: LocationPermissionHelper.buildGpsStatusBadge(
              isTracking: _isGpsTracking,
              successCount: _gpsService.successCount,
              failCount: _gpsService.failCount,
              lastUpdate: _gpsService.lastUpdate,
            ),
          ),
      ],
      flexibleSpace: FlexibleSpaceBar(
        background: Stack(
          fit: StackFit.expand,
          children: [
            OptimizedNetworkImage(
              imageUrl: widget.booking.carImage,
              width: double.infinity,
              height: 300,
              fit: BoxFit.cover,
              errorIcon: Icons.directions_car,
              errorIconSize: 100,
            ),
            _imageGradient(),
            _statusBadge(),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _carInfo(),
        _rentalPeriod(),
        const SizedBox(height: 20),
        
        // Overdue Banner - Show whenever booking is overdue (even if late fee is not yet set)
        if (_overdueInfo != null && _overdueInfo!.hoursOverdue > 0) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: OverdueWarningBanner(
              daysOverdue: _overdueInfo!.daysOverdue,
              lateFee: _overdueInfo!.lateFeeAmount,
              onPayNow: _overdueInfo!.lateFeeAmount > 0
                  ? () async {
                      final result = await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => LateFeePaymentScreen(
                            bookingId: widget.booking.bookingId,
                            rentalAmount: _overdueInfo!.rentalFee,  // Use rentalFee instead of totalAmount
                            lateFee: _overdueInfo!.lateFeeAmount,
                            hoursOverdue: _overdueInfo!.hoursOverdue,
                            vehicleName: _overdueInfo!.vehicleName,
                            isRentalPaid: _overdueInfo!.isRentalPaid,
                          ),
                        ),
                      );

                      if (result == true) {
                        // Refresh overdue status and payment info
                        await _checkOverdueStatus();
                        await _fetchPaymentInfo();
                        await _fetchLateFeeStatus();
                      }
                    }
                  : null,
            ),
          ),

          // Late fee payment status (Pending / Verified / etc.)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: _buildLateFeePaymentStatusCard(),
          ),

          // Damage report filed by owner (if any)
          if (_damageReport != null) ...[
            const SizedBox(height: 12),
            _buildDamageReportStatusCard(),
          ],

          const SizedBox(height: 20),
        ],
        
        // GPS Tracking Section - Only for Active Bookings
        if (widget.status.toLowerCase() == 'active') ...[
          _buildGpsTrackingSection(),
          const SizedBox(height: 20),
        ],
        
        // Insurance Policy Section - Only for Active/Approved Bookings
        if (widget.booking.status.toLowerCase() == 'approved') ...[
          _buildInsurancePolicySection(),
          const SizedBox(height: 20),
        ],
        
        _locationCard(),
        const SizedBox(height: 20),
        
        // Payment Status Section
        if (_paymentData != null) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: PaymentStatusTracker(
              paymentData: _paymentData!,
              onViewReceipt: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ReceiptViewerScreen(
                      bookingId: widget.booking.bookingId,
                    ),
                  ),
                );
              },
              onRequestRefund: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => RefundRequestScreen(
                      bookingId: widget.booking.bookingId,
                      bookingReference: '#BK-${widget.booking.bookingId}',
                      totalAmount: double.tryParse(_paymentData!['amount'].toString()) ?? 0,
                      cancellationDate: DateTime.now().toString(),
                      paymentMethod: _paymentData!['payment_method'] ?? 'N/A',
                      paymentReference: _paymentData!['payment_reference'] ?? 'N/A',
                    ),
                  ),
                ).then((result) {
                  if (result == true) {
                    _fetchPaymentInfo();
                  }
                });
              },
            ),
          ),
          const SizedBox(height: 20),
        ] else if (_isLoadingPayment) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Center(
              child: CircularProgressIndicator(
  color: Theme.of(context).colorScheme.primary,
)
,
            ),
          ),
          const SizedBox(height: 20),
        ],
        
        _paymentDetails(),
        const SizedBox(height: 20),
        _helpSection(),
        const SizedBox(height: 120),
      ],
    );
  }

  // NEW: Insurance Policy Section
  Widget _buildInsurancePolicySection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [Colors.orange.shade50, Colors.orange.shade100],
          ),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Colors.orange.shade200,
            width: 1.5,
          ),
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade700,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.shield,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Insurance Coverage',
                        style: GoogleFonts.outfit(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'View your policy details and file claims',
                        style: GoogleFonts.inter(
                          fontSize: 12,
                          color: Colors.grey.shade700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _viewInsurancePolicy,
                icon: const Icon(Icons.description, size: 18),
                label: Text(
                  'View Insurance Policy',
                  style: GoogleFonts.inter(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange.shade700,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _viewInsurancePolicy() async {
    try {
      if (userId == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('User ID not found. Please login again.'),
              backgroundColor: Colors.red,
            ),
          );
        }
        return;
      }

      // Navigate to Insurance Policy Screen
      if (mounted) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => InsurancePolicyScreen(
              bookingId: widget.booking.bookingId,
              userId: int.parse(userId!),
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  // NEW: GPS Tracking Section
  Widget _buildGpsTrackingSection() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
         gradient: LinearGradient(
  colors: isDark
      ? [
          colors.surfaceContainerHighest,
          colors.surface,
        ]
      : _isGpsTracking
          ? [Colors.green.shade50, Colors.green.shade100]
          : [Colors.orange.shade50, Colors.orange.shade100],
),

          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: _isGpsTracking 
                ? Colors.green.shade200 
                : Colors.orange.shade200,
            width: 1.5,
          ),
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: _isGpsTracking ? Colors.green : Colors.orange,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    _isGpsTracking ? Icons.gps_fixed : Icons.gps_off,
                    color: isDark ? colors.surface : Theme.of(context).cardColor,                    size: 24,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'GPS Tracking',
                        style: GoogleFonts.outfit(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _isGpsTracking
                            ? 'Your location is being shared with the owner'
                            : 'Start GPS tracking to share your location',
                        style: GoogleFonts.inter(
                          fontSize: 12,
                          color: isDark ? colors.onSurfaceVariant : Colors.grey.shade700,

                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            
            if (_isGpsTracking) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: isDark ? colors.surface : Theme.of(context).cardColor,                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        children: [
                          Icon(Icons.cloud_upload, 
                              size: 20, color: Colors.green.shade700),
                          const SizedBox(height: 4),
                          Text(
                            '${_gpsService.successCount}',
                            style: GoogleFonts.outfit(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Updates Sent',
                            style: GoogleFonts.inter(
                              fontSize: 11,
                              color: Theme.of(context).hintColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                   Container(
  width: 1,
  height: 40,
  color: Theme.of(context).dividerColor,
),

                    Expanded(
                      child: Column(
                        children: [
                          Icon(Icons.error_outline, 
                              size: 20, color: Colors.red.shade700),
                          const SizedBox(height: 4),
                          Text(
                            '${_gpsService.failCount}',
                            style: GoogleFonts.outfit(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Failed',
                            style: GoogleFonts.inter(
                              fontSize: 11,
                              color: Theme.of(context).hintColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
            
            const SizedBox(height: 16),
            
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _isGpsTracking ? _stopGpsTracking : _startGpsTracking,
                icon: Icon(
                  _isGpsTracking ? Icons.location_off : Icons.location_on,
                  size: 18,
                ),
                label: Text(
                  _isGpsTracking ? 'Stop GPS Tracking' : 'Start GPS Tracking',
                  style: GoogleFonts.inter(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _isGpsTracking 
                      ? Colors.red.shade600 
                      : Colors.green.shade600,
                  foregroundColor: isDark ? colors.surface : Theme.of(context).cardColor, padding: const EdgeInsets.symmetric(vertical: 14),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _carInfo() {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            widget.booking.carName,
            style: GoogleFonts.poppins(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.receipt_long, size: 16, color: Theme.of(context).hintColor),
              const SizedBox(width: 6),
              Text(
                'Booking ID: ${widget.booking.bookingId}',
                style: GoogleFonts.poppins(fontSize: 13, color: Theme.of(context).hintColor),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _rentalPeriod() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: isDark ? colors.surface : Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(16),
          
        ),
        child: Column(
          children: [
            Row(
              children: [
                Icon(Icons.calendar_today, color: Colors.blue.shade700),
                const SizedBox(width: 8),
                Text(
                  'Rental Period',
                  style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: _dateCard(
                    'Pick Up',
                    widget.booking.pickupDate,
                    widget.booking.pickupTime,
                    Icons.arrow_circle_up,
                    Colors.green,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _dateCard(
                    'Return',
                    widget.booking.returnDate,
                    widget.booking.returnTime,
                    Icons.arrow_circle_down,
                    Colors.orange,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomButton(BuildContext context) {
    // Show refund button for rejected/cancelled
    if (widget.booking.status == 'rejected' || widget.booking.status == 'cancelled') {
      return _buildRefundButton(context);
    }

    switch (widget.status) {
      case 'active':
       final isDark = Theme.of(context).brightness == Brightness.dark;
  final colors = Theme.of(context).colorScheme;
        return _twoButtons(
          context,
          'Cancel Booking',
          Colors.red,
          _showCancelDialog,
          'View Trip',
          isDark ? colors.primary : Colors.black,
          rightAction: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => LiveTripTrackerScreen(
                  booking: widget.booking,
                ),
              ),
            );
          },
        );

     case 'pending': {
  final isDark = Theme.of(context).brightness == Brightness.dark;
  final colors = Theme.of(context).colorScheme;

  return ElevatedButton(
    onPressed: () {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Awaiting owner approval...'),
          backgroundColor: Colors.orange,
        ),
      );
    },
    style: ElevatedButton.styleFrom(
      backgroundColor: Colors.orange,
      padding: const EdgeInsets.symmetric(vertical: 16),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      elevation: 0,
    ),
    child: Text(
      'Awaiting Approval',
      style: GoogleFonts.poppins(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        color: isDark ? colors.surface : Theme.of(context).cardColor,
      ),
    ),
  );
}

      case 'upcoming':
        return _twoButtons(
          context,
          'Modify Booking',
          Colors.grey,
          _showCancelDialog,
          'Get Directions',
          Colors.black,
          rightAction: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => LiveTripTrackerScreen(
                  booking: widget.booking,
                ),
              ),
            );
          },
        );

      case 'past':
      case 'completed':
        return _singleButton(
          'Book This Car Again',
          Colors.black,
          () {
            ScaffoldMessenger.of(context).showSnackBar(
             SnackBar(
                content: Text('Rebooking feature coming soon!'),
                 backgroundColor: Theme.of(context).iconTheme.color,




              ),
            );
          },
        );

      default:
        return const SizedBox.shrink();
    }
  }

  Widget _locationCard() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
         
        ),
        child: Row(
          children: [
            _iconBox(Icons.location_on, Colors.red),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Location',
                    style: GoogleFonts.poppins(fontSize: 12, color: Colors.grey),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.booking.location,
                    style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios, size: 16, color: isDark ? colors.onSurfaceVariant : Colors.grey.shade400
),
          ],
        ),
      ),
    );
  }

  Widget _paymentDetails() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    // Compute breakdown from booking fields
    final totalAmount = double.tryParse(
          widget.booking.totalPrice.replaceAll(',', '')) ?? 0;
    final pricePerDay = widget.booking.pricePerDay;
    final securityDeposit = widget.booking.securityDeposit;

    final pickupDate = DateTime.tryParse(widget.booking.pickupDate);
    final returnDate = DateTime.tryParse(widget.booking.returnDate);
    final days = (pickupDate != null && returnDate != null)
        ? returnDate.difference(pickupDate).inDays
        : 0;

    final baseRental = pricePerDay > 0 ? pricePerDay * days : 0.0;
    final serviceFee = pricePerDay > 0 ? totalAmount - baseRental : 0.0;
    final grandTotal = totalAmount + securityDeposit;

    String fmt(double v) => v.toStringAsFixed(2).replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},');

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Payment Breakdown',
            style: GoogleFonts.poppins(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: isDark ? colors.surface : Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                if (pricePerDay > 0) ...[
                  _paymentRow('Base Rental (${days}d × ₱${fmt(pricePerDay)})', '₱${fmt(baseRental)}'),
                  const Divider(height: 24),
                  _paymentRow('Service Fee (5%)', '₱${fmt(serviceFee)}', isSubtotal: true),
                  const Divider(height: 24),
                ] else ...[
                  _paymentRow('Rental Amount', '₱${fmt(totalAmount)}'),
                  const Divider(height: 24),
                ],
                _paymentRow('Total Amount', '₱${fmt(totalAmount)}'),
                if (securityDeposit > 0) ...[
                  const Divider(height: 24),
                  _paymentRow('Security Deposit (20%)', '₱${fmt(securityDeposit)}', isSubtotal: true),
                  const Divider(height: 24),
                  _paymentRow('Grand Total', '₱${fmt(grandTotal)}', isTotal: true),
                ] else ...[
                  // No security deposit data — just show total as the final figure
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _helpSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Need Help?',
            style: GoogleFonts.poppins(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _contactButton(
                  'Message Owner',
                  Icons.chat_bubble_outline,
                  Colors.blue,
                  _messageOwner,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _contactButton(
                  'Call Support',
                  Icons.phone,
                  Colors.green,
                  _callSupport,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBottomBar(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? colors.surface : Theme.of(context).cardColor,        boxShadow: [
          BoxShadow(
           color: isDark
    ? Colors.black.withAlpha((0.6 * 255).round())
    : Colors.black.withAlpha((0.08 * 255).round()),

            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(top: false, child: _buildBottomButton(context)),
    );
  }

  Widget _buildRefundButton(BuildContext context) {
    // Check if refund has been requested
    final refundStatus = _paymentData?['refund_status'] ?? 'not_requested';
    final hasRefundRequested = refundStatus != 'not_requested' && refundStatus.isNotEmpty;
    
    debugPrint('🔍 Building Refund Button:');
    debugPrint('   Payment Data: $_paymentData');
    debugPrint('   Refund Status: $refundStatus');
    debugPrint('   Has Refund Requested: $hasRefundRequested');
    
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: hasRefundRequested ? Colors.orange.shade50 : Colors.red.shade50,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: hasRefundRequested ? Colors.orange.shade200 : Colors.red.shade200,
            ),
          ),
          child: Row(
            children: [
              Icon(
                hasRefundRequested ? Icons.pending : Icons.info_outline,
                color: hasRefundRequested ? Colors.orange.shade700 : Colors.red.shade700,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  hasRefundRequested
                      ? 'Your refund request is being processed'
                      : (widget.booking.status == 'rejected'
                          ? 'This booking was rejected by the owner'
                          : 'This booking was cancelled'),
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    color: hasRefundRequested ? Colors.orange.shade900 : Colors.red.shade900,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: hasRefundRequested ? null : () async {
              final result = await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => RefundRequestScreen(
                    bookingId: widget.booking.bookingId,
                    bookingReference: '#BK-${widget.booking.bookingId.toString().padLeft(4, '0')}',
                    totalAmount: double.tryParse(
                          widget.booking.totalPrice.replaceAll(',', ''),
                        ) ?? 0,
                    cancellationDate: DateTime.now().toString(),
                    paymentMethod: _paymentData?['payment_method'] ?? 'gcash',
                    paymentReference: _paymentData?['payment_reference'] ?? widget.booking.bookingId.toString(),
                  ),
                ),
              );

              if (result == true) {
                await _fetchPaymentInfo();
                setState(() {}); // Refresh to show updated button
              }
            },
            icon: Icon(
              hasRefundRequested ? Icons.hourglass_empty : Icons.undo,
              size: 20,
            ),
            label: Text(
              hasRefundRequested ? 'Request Pending' : 'Request Refund',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: hasRefundRequested ? Colors.orange.shade600 : Colors.red.shade600,
              foregroundColor: Colors.white,
              disabledBackgroundColor: Colors.grey.shade400,
              disabledForegroundColor: Colors.grey.shade600,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              elevation: 0,
            ),
          ),
        ),
      ],
    );
  }

  // Message Owner - Opens chat with owner
  void _messageOwner() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final currentUserId = prefs.getString('user_id');
      
      if (currentUserId == null) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Please login to message the owner'),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      // Generate chat ID (consistent format: smaller_id_larger_id)
      final ownerId = widget.booking.ownerId.toString();
      final userIdInt = int.parse(currentUserId);
      final ownerIdInt = int.parse(ownerId);
      final chatId = userIdInt < ownerIdInt
          ? '${currentUserId}_$ownerId'
          : '${ownerId}_$currentUserId';

      // Navigate to chat screen
      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => ChatDetailScreen(
            chatId: chatId,
            peerId: ownerId,
            peerName: widget.booking.ownerName,
            peerAvatar: widget.booking.ownerAvatar,
          ),
        ),
      );
    } catch (e) {
      debugPrint('Error opening chat: $e');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to open chat'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  // Call Support - Makes a phone call
  void _callSupport() async {
    // You can use owner's phone or support number
    final phoneNumber = widget.booking.ownerPhone.isNotEmpty 
        ? widget.booking.ownerPhone 
        : '09123456789'; // Fallback support number
    
    final uri = Uri.parse('tel:$phoneNumber');
    
    try {
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri);
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Cannot make phone call'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      debugPrint('Error making call: $e');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to make call: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _showCancelDialog(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    if (userId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('User session expired. Please login again.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Cancel Booking?'),
        content: const Text(
          'Are you sure you want to cancel this booking? GPS tracking will be stopped. This action cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('No'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);

              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (_) => Center(
                  child: Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: isDark ? colors.surface : Theme.of(context).cardColor,borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                       CircularProgressIndicator(
  color: Theme.of(context).colorScheme.primary,
)
,
                        const SizedBox(height: 16),
                        Text(
                          'Cancelling booking...',
                          style: GoogleFonts.poppins(fontSize: 14),
                        ),
                      ],
                    ),
                  ),
                ),
              );

              // Stop GPS tracking
              _stopGpsTracking();

              final result = await BookingService.cancelBooking(
                bookingId: widget.booking.bookingId,
                userId: userId!,
              );

              if (!mounted) return;
              Navigator.pop(this.context);

              if (result['success'] == true) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(
                    content: Text(result['message'] ?? 'Booking cancelled successfully. GPS tracking stopped.'),
                    backgroundColor: Colors.green,
                  ),
                );

                _bookingChanged = true;
                Navigator.pop(this.context, true);
              } else {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(
                    content: Text(result['message'] ?? 'Failed to cancel booking'),
                    backgroundColor: Colors.red,
                    duration: const Duration(seconds: 5),
                  ),
                );
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Cancel Booking'),
          ),
        ],
      ),
    );
  }

  Widget _circleIcon(IconData icon) {
  final isDark = Theme.of(context).brightness == Brightness.dark;
  final colors = Theme.of(context).colorScheme;

  return Container(
    padding: const EdgeInsets.all(8),
    decoration: BoxDecoration(
      color: isDark ? colors.surface : Theme.of(context).cardColor,
      shape: BoxShape.circle,
    ),
    child: Icon(icon),
  );
}


  Widget _imageGradient() => Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.transparent,
              Colors.black.withAlpha((0.3 * 255).round())
            ],
          ),
        ),
      );

  Widget _statusBadge() => Positioned(
        top: 60,
        right: 16,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: _getStatusColor(),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            _getStatusText(),
            style: GoogleFonts.poppins(color: Colors.white, fontSize: 12),
          ),
        ),
      );

  Widget _iconBox(IconData icon, Color color) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withAlpha((0.1 * 255).round()),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: color),
      );

  Widget _dateCard(
    String label,
    String date,
    String time,
    IconData icon,
    Color color,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 6),
              Text(label, style: GoogleFonts.poppins(fontSize: 11)),
            ],
          ),
          const SizedBox(height: 8),
          Text(date, style: GoogleFonts.poppins(fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          Text(time, style: GoogleFonts.poppins(fontSize: 11)),
        ],
      ),
    );
  }

  Widget _paymentRow(
    String label,
    String value, {
    bool isSubtotal = false,
    bool isTotal = false,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
          ),
        ),
        Text(
          value,
          style: GoogleFonts.poppins(
            fontWeight: isTotal ? FontWeight.bold : FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _contactButton(
    String label,
    IconData icon,
    Color color,
    VoidCallback onTap,
  ) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: color.withAlpha((0.1 * 255).round()),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 8),
            Text(label, style: GoogleFonts.poppins(color: color)),
          ],
        ),
      ),
    );
  }

  Widget _singleButton(String label, Color color, VoidCallback onTap) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return ElevatedButton(
      onPressed: onTap,
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),
      child: Text(
        label,
        style: GoogleFonts.poppins(
          color: isDark ? colors.surface : Theme.of(context).cardColor,          fontSize: 16,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _twoButtons(
    BuildContext context,
    String leftLabel,
    Color leftColor,
    Function(BuildContext) leftAction,
    String rightLabel,
    Color rightColor, {
    VoidCallback? rightAction,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;

    return Row(
      children: [
        Expanded(
          child: OutlinedButton(
            onPressed: () => leftAction(context),
            style: OutlinedButton.styleFrom(
              side: BorderSide(color: leftColor),
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: Text(
              leftLabel,
              style: GoogleFonts.poppins(
                color: leftColor,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: ElevatedButton(
            onPressed: rightAction ?? () {},
            style: ElevatedButton.styleFrom(
              backgroundColor: rightColor,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: Text(
              rightLabel,
              style: GoogleFonts.poppins(
                color: isDark ? colors.surface : Theme.of(context).cardColor,                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ),
      ],
    );
  }
}