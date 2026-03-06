// lib/USERS-UI/Owner/active_booking_page.dart
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dashboard/booking_service.dart';
import 'mycar/api_config.dart';
import 'live_tracking_screen.dart'; // Import the new tracking screen
import 'package:cargo/widgets/optimized_network_image.dart';
import 'package:cargo/USERS-UI/widgets/odometer_input_screen.dart'; // Odometer tracking
import 'package:cargo/USERS-UI/Owner/damage_report_screen.dart';

class ActiveBookingsPage extends StatefulWidget {
  const ActiveBookingsPage({super.key});

  @override
  State<ActiveBookingsPage> createState() => _ActiveBookingsPageState();
}

class _ActiveBookingsPageState extends State<ActiveBookingsPage> {
  final BookingService _bookingService = BookingService();

  DateTime? _tryParseScheduledPickup(String? pickupDateRaw, String? pickupTimeRaw) {
    if (pickupDateRaw == null || pickupDateRaw.trim().isEmpty) return null;

    // Expecting: pickupDateRaw = "YYYY-MM-DD" and pickupTimeRaw = "HH:MM" or "HH:MM:SS"
    String time = (pickupTimeRaw ?? '').trim();
    if (time.isEmpty) time = '00:00:00';
    if (RegExp(r'^\d{2}:\d{2}$').hasMatch(time)) {
      time = '$time:00';
    }

    final iso = '${pickupDateRaw.trim()}T$time';
    return DateTime.tryParse(iso);
  }
  
  String? _ownerId;
  bool _isLoading = true;
  List<Map<String, dynamic>> _activeBookings = [];
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadOwnerIdAndFetchBookings();
  }

  Future<void> _loadOwnerIdAndFetchBookings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getString('user_id');

      if (userId == null || userId.isEmpty) {
        if (mounted) {
          setState(() => _isLoading = false);
          _showLoginRequiredDialog();
        }
        return;
      }

      setState(() {
        _ownerId = userId;
        _isLoading = true;
        _errorMessage = null;
      });

      await _fetchActiveBookings();
    } catch (e) {
      debugPrint('❌ Error loading owner data: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Failed to load data. Please try again.';
        });
      }
    }
  }

  Future<void> _fetchActiveBookings() async {
    try {
      final bookings = await _bookingService.fetchActiveBookings(_ownerId!);
      
      if (mounted) {
        setState(() {
          _activeBookings = bookings;
          _isLoading = false;
          _errorMessage = null;
        });
      }
    } catch (e) {
      debugPrint('❌ Error fetching active bookings: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Failed to load active bookings. Pull to retry.';
        });
      }
    }
  }

  void _showLoginRequiredDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Login Required'),
        content: const Text('Please log in to view active bookings.'),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Navigator.pop(context);
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  Future<void> _handleRefresh() async {
    await _fetchActiveBookings();
  }

  Future<void> _handleStartTrip(Map<String, dynamic> booking) async {
    final isUnlimited = (booking['has_unlimited_mileage'] == 1) || (booking['has_unlimited_mileage'] == true);
    if (isUnlimited) {
      final result = await _bookingService.startTrip(
        booking['booking_id'].toString(),
        _ownerId!,
      );
      if (!mounted) return;
      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Trip started'),
            backgroundColor: Colors.green,
          ),
        );
        await _handleRefresh();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Failed to start trip'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }
    // Navigate to odometer input screen for START reading (limited mileage)
    final odometerResult = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => OdometerInputScreen(
          bookingId: int.tryParse(booking['booking_id'].toString()) ?? 0,
          vehicleName: booking['car_full_name'] ?? 'Vehicle',
          vehicleImage: booking['car_image'] ?? '',
          isStartOdometer: true,
          userId: int.tryParse(_ownerId ?? '0') ?? 0,
          userType: 'owner',
        ),
      ),
    );

    // If user cancelled odometer input, don't proceed
    if (odometerResult == null || !mounted) return;

    // Show confirmation dialog
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(Icons.play_circle_outline, color: Colors.green.shade600, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Start Rental?',
                style: GoogleFonts.outfit(fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Confirm that ${booking['renter_name']} has picked up the vehicle:',
              style: GoogleFonts.inter(fontSize: 14),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.green.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    booking['car_full_name'] ?? 'Vehicle',
                    style: GoogleFonts.outfit(
                      fontWeight: FontWeight.w600,
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Pickup: ${booking['pickup_date']}',
                    style: GoogleFonts.inter(fontSize: 13, color: Colors.grey.shade700),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Text(
              '✓ This will mark the rental as started\n✓ Renter will be notified\n✓ Trip tracking will begin',
              style: GoogleFonts.inter(fontSize: 12, color: Colors.grey.shade600),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(
              'Cancel',
              style: GoogleFonts.inter(color: Colors.grey.shade600),
            ),
          ),
          ElevatedButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.check_circle, size: 18),
            label: const Text('Confirm Pickup'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green.shade600,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    // ✅ Client-side guard (UX): block starting before scheduled pickup datetime.
    // Server will also enforce this, but we avoid an unnecessary API call.
    final String? pickupDateRaw = booking['pickup_date_raw']?.toString();
    final String? pickupTimeRaw = booking['pickup_time_raw']?.toString();
    final DateTime? scheduledPickup = _tryParseScheduledPickup(pickupDateRaw, pickupTimeRaw);
    if (scheduledPickup != null && DateTime.now().isBefore(scheduledPickup)) {
      final pretty = "${booking['pickup_date'] ?? pickupDateRaw ?? ''} ${booking['pickup_time'] ?? ''}".trim();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.schedule, color: Colors.white),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'You can only start pickup at the scheduled time: $pretty',
                  style: GoogleFonts.inter(),
                ),
              ),
            ],
          ),
          backgroundColor: Colors.orange.shade700,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          duration: const Duration(seconds: 4),
        ),
      );
      return;
    }

    // Show loading indicator
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Center(
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 40),
          padding: const EdgeInsets.all(32),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.1),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  shape: BoxShape.circle,
                ),
                child: CircularProgressIndicator(
                  color: Colors.green.shade600,
                  strokeWidth: 3,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Starting Rental',
                style: GoogleFonts.outfit(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Colors.black,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Please wait...',
                style: GoogleFonts.inter(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                ),
              ),
            ],
          ),
        ),
      ),
    );

    try {
      final result = await _bookingService.startTrip(
        booking['booking_id'].toString(),
        _ownerId!,
      );

      if (!mounted) return;

      // Close loading dialog
      Navigator.pop(context);

      if (result['success'] == true) {
        // Show success message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    result['message'] ?? 'Rental started successfully!',
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

        // Refresh the list
        await _handleRefresh();
      } else {
        // Show error message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.error_outline, color: Colors.white),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    result['message'] ?? 'Failed to start rental',
                    style: GoogleFonts.inter(),
                  ),
                ),
              ],
            ),
            backgroundColor: Colors.red.shade600,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            duration: const Duration(seconds: 4),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      
      // Close loading dialog
      Navigator.pop(context);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Network error. Please check your connection.',
            style: GoogleFonts.inter(),
          ),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 120,
            floating: false,
            pinned: true,
            backgroundColor: Theme.of(context).scaffoldBackgroundColor,
            elevation: 0,
            leading: IconButton(
              icon: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Theme.of(context).iconTheme.color,



                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.15),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Icon(Icons.arrow_back_ios_new, color: Colors.white, size: 18),
              ),
              onPressed: () => Navigator.pop(context),
            ),
            flexibleSpace: FlexibleSpaceBar(
              centerTitle: true,
              title: Text(
                'Active Bookings',
                style: GoogleFonts.outfit(
                  color: Theme.of(context).iconTheme.color,



                  fontWeight: FontWeight.w700,
                  fontSize: 22,
                ),
              ),
              background: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                ),
              ),
            ),
          ),
          SliverFillRemaining(
            child: _buildContent(),
          ),
        ],
      ),
    );
  }

  Widget _buildContent() {
    if (_isLoading) {
      return _buildLoadingState();
    }

    if (_errorMessage != null) {
      return _buildErrorState();
    }

    if (_activeBookings.isEmpty) {
      return RefreshIndicator(
        onRefresh: _handleRefresh,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: SizedBox(
            height: MediaQuery.of(context).size.height - 200,
            child: _buildEmptyState(),
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _handleRefresh,
      color: Theme.of(context).iconTheme.color,



      child: _buildBookingsList(),
    );
  }

  Widget _buildLoadingState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const CircularProgressIndicator(color: Colors.black),
          const SizedBox(height: 16),
          Text(
            'Loading active bookings...',
            style: GoogleFonts.inter(
              fontSize: 14,
              color: Colors.grey[600],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 80, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text(
              "Oops!",
              style: GoogleFonts.outfit(
                fontSize: 24,
                color: Theme.of(context).iconTheme.color,



                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _errorMessage ?? 'Something went wrong',
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(
                fontSize: 14,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _loadOwnerIdAndFetchBookings,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
              style: ElevatedButton.styleFrom(
                 backgroundColor: Theme.of(context).iconTheme.color,




                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.event_busy, size: 80, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            "No active bookings",
            style: GoogleFonts.outfit(
              fontSize: 18,
              color: Colors.grey[600],
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            "Active rentals will appear here",
            style: GoogleFonts.inter(
              fontSize: 14,
              color: Colors.grey[500],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBookingsList() {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      itemCount: _activeBookings.length,
      itemBuilder: (context, index) {
        return _buildModernBookingCard(_activeBookings[index]);
      },
    );
  }

  // Replace the _buildModernBookingCard method in active_booking_page.dart

Widget _buildModernBookingCard(Map<String, dynamic> booking) {
  final daysRemaining = int.tryParse(booking['days_remaining']?.toString() ?? '0') ?? 0;
  final progress = double.tryParse(booking['trip_progress']?.toString() ?? '0') ?? 0;
  
  // Safe image URL handling - ensure we never pass empty string to Image.network
  final carImage = booking['car_image'];
  final imageUrl = (carImage == null || carImage.toString().trim().isEmpty) 
      ? 'https://via.placeholder.com/300' 
      : ApiConfig.getCarImageUrl(carImage);
  
  // NEW: Check trip status
  final tripStatus = booking['trip_status'] ?? 'in_progress';
  final isUpcoming = tripStatus == 'upcoming';
  final isOverdue = tripStatus == 'overdue';
  final statusColor = isOverdue ? Colors.red : (isUpcoming ? Colors.orange : Colors.green);
  final statusLabel = isOverdue ? 'Overdue' : (isUpcoming ? 'Starts Soon' : 'Active');

  return GestureDetector(
    onTap: () {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ActiveBookingDetailsPage(
            booking: booking,
            ownerId: _ownerId!,
          ),
        ),
      ).then((_) => _handleRefresh());
    },
    child: Container(
      margin: const EdgeInsets.only(bottom: 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.grey.shade200, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 15,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(20),
                  topRight: Radius.circular(20),
                ),
                child: OptimizedNetworkImage(
                  imageUrl: imageUrl,
                  height: 220,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(20),
                    topRight: Radius.circular(20),
                  ),
                  errorIcon: Icons.directions_car,
                  errorIconSize: 80,
                ),
              ),
              
              // UPDATED: Dynamic status badge
              Positioned(
                top: 16,
                left: 16,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: statusColor,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        statusLabel,
                        style: GoogleFonts.inter(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              
              Positioned(
                top: 16,
                right: 16,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.schedule, color: Colors.grey.shade700, size: 14),
                      const SizedBox(width: 6),
                      Text(
                        (booking['time_remaining_label']?.toString().isNotEmpty == true)
                            ? booking['time_remaining_label']
                            : (daysRemaining > 0 ? '$daysRemaining days left' : 'Ending today'),
                        style: GoogleFonts.inter(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Colors.black,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.black,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '₱${booking['price_per_day']}/day',
                        style: GoogleFonts.outfit(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.black,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.white),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                
                Text(
                  booking['car_full_name'] ?? 'Car',
                  style: GoogleFonts.outfit(
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                    color: Colors.black,
                  ),
                ),
                const SizedBox(height: 12),
                
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    children: [
                      _buildInfoRow(Icons.person_outline, 'Renter', booking['renter_name'] ?? 'Unknown'),
                      const Divider(height: 24),
                      _buildInfoRow(
                        Icons.calendar_today_outlined, 
                        isUpcoming ? 'Starts' : 'Started', 
                        '${booking['pickup_date'] ?? ''} at ${booking['pickup_time_display'] ?? booking['pickup_time'] ?? ''}'
                      ),
                      const Divider(height: 24),
                      _buildInfoRow(
                        Icons.event_outlined, 
                        'End Date', 
                        '${booking['return_date'] ?? ''} at ${booking['return_time_display'] ?? booking['return_time'] ?? ''}'
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                
                // Only show progress for active bookings
                if (!isUpcoming) Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Trip Progress',
                            style: GoogleFonts.inter(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          Text(
                            '${progress.toStringAsFixed(0)}%',
                            style: GoogleFonts.outfit(
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: SizedBox(
                          height: 6,
                          child: LinearProgressIndicator(
                            value: progress / 100,
                            backgroundColor: Colors.grey.shade200,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              progress < 33 ? Colors.green :
                              progress < 66 ? Colors.orange : Colors.red
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                
                // Action Buttons (Start Rent or Live Tracking)
                const SizedBox(height: 16),
                if (isUpcoming)
                  // Start Rent Button for upcoming bookings
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () => _handleStartTrip(booking),
                      icon: const Icon(Icons.play_circle_outline, size: 22),
                      label: Text(
                        'Start Rent / Picked Up',
                        style: GoogleFonts.inter(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green.shade600,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        elevation: 2,
                        shadowColor: Colors.green.withValues(alpha: 0.4),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  )
                else
                  // Live Tracking + Report Damage for active bookings
                  Column(
                    children: [
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => LiveTrackingScreen(
                                  bookingId: booking['booking_id'].toString(),
                                  carName: booking['car_full_name'] ?? 'Car',
                                  renterName: booking['renter_name'] ?? 'Unknown',
                                ),
                              ),
                            );
                          },
                          icon: const Icon(Icons.map, size: 20),
                          label: Text(
                            'Track Car Location',
                            style: GoogleFonts.inter(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.blue.shade600,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => OwnerDamageReportScreen(
                                  bookingId: int.tryParse(
                                          booking['booking_id'].toString()) ??
                                      0,
                                  renterName:
                                      booking['renter_name'] ?? 'Unknown',
                                  carName:
                                      booking['car_full_name'] ?? 'Vehicle',
                                  ownerId:
                                      int.tryParse(_ownerId ?? '0') ?? 0,
                                ),
                              ),
                            );
                          },
                          icon: const Icon(Icons.car_crash, size: 20),
                          label: Text(
                            'Report Damage',
                            style: GoogleFonts.inter(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.red.shade700,
                            side: BorderSide(color: Colors.red.shade400),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: Colors.grey.shade700),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: Colors.grey.shade600,
                ),
              ),
              Text(
                value,
                style: GoogleFonts.outfit(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// DETAILS PAGE (unchanged, keeping original implementation)
class ActiveBookingDetailsPage extends StatefulWidget {
  final Map<String, dynamic> booking;
  final String ownerId;

  const ActiveBookingDetailsPage({
    super.key,
    required this.booking,
    required this.ownerId,
  });

  @override
  State<ActiveBookingDetailsPage> createState() => _ActiveBookingDetailsPageState();
}

class _ActiveBookingDetailsPageState extends State<ActiveBookingDetailsPage> {
  final BookingService _bookingService = BookingService();
  bool _isEnding = false;

  Future<void> _handleEndTrip() async {
    final isUnlimited = (widget.booking['has_unlimited_mileage'] == 1) || (widget.booking['has_unlimited_mileage'] == true);
    if (isUnlimited) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Text('End Trip?', style: GoogleFonts.outfit(fontWeight: FontWeight.bold)),
          content: Text(
            'End this rental now? Odometer tracking is not required for this vehicle.',
            style: GoogleFonts.inter(),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red,
                foregroundColor: Colors.white,
              ),
              child: const Text('End Trip'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      setState(() => _isEnding = true);
      try {
        final result = await _bookingService.endTrip(
          widget.booking['booking_id'].toString(),
          widget.ownerId,
        );
        if (!mounted) return;
        setState(() => _isEnding = false);
        if (result['success'] == true) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message'] ?? 'Trip ended'),
              backgroundColor: Colors.green,
            ),
          );
          Navigator.pop(context);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message'] ?? 'Failed to end trip'),
              backgroundColor: Colors.red,
            ),
          );
        }
      } catch (e) {
        if (!mounted) return;
        setState(() => _isEnding = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Network error. Please try again.'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }
    // Navigate to odometer input screen for END reading (limited mileage)
    final odometerResult = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => OdometerInputScreen(
          bookingId: int.tryParse(widget.booking['booking_id'].toString()) ?? 0,
          vehicleName: widget.booking['car_full_name'] ?? 'Vehicle',
          vehicleImage: widget.booking['car_image'] ?? '',
          isStartOdometer: false,
          startOdometer: int.tryParse(widget.booking['odometer_start']?.toString() ?? '0'),
          userId: int.tryParse(widget.ownerId) ?? 0,
          userType: 'owner',
        ),
      ),
    );

    // If user cancelled odometer input, don't proceed
    if (odometerResult == null || !mounted) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text('End Trip?', style: GoogleFonts.outfit(fontWeight: FontWeight.bold)),
        content: Text(
          'Are you sure you want to mark this rental as completed? This action cannot be undone.',
          style: GoogleFonts.inter(),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: const Text('End Trip'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _isEnding = true);

    try {
      final result = await _bookingService.endTrip(
        widget.booking['booking_id'].toString(),
        widget.ownerId,
      );

      if (!mounted) return;

      setState(() => _isEnding = false);

      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    result['message'] ?? 'Trip completed successfully',
                    style: GoogleFonts.inter(),
                  ),
                ),
              ],
            ),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );

        Navigator.pop(context);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              result['message'] ?? 'Failed to end trip',
              style: GoogleFonts.inter(),
            ),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      
      setState(() => _isEnding = false);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Network error. Please try again.',
            style: GoogleFonts.inter(),
          ),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final progress = double.tryParse(widget.booking['trip_progress']?.toString() ?? '0') ?? 0;
    final daysRemaining = int.tryParse(widget.booking['days_remaining']?.toString() ?? '0') ?? 0;
    
    // Safe image URL handling
    final carImage = widget.booking['car_image'];
    final imageUrl = (carImage == null || carImage.toString().trim().isEmpty) 
        ? 'https://via.placeholder.com/300' 
        : ApiConfig.getCarImageUrl(carImage);
    
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text('Booking Details', style: GoogleFonts.outfit(fontWeight: FontWeight.w700)),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        foregroundColor: Theme.of(context).iconTheme.color,


        actions: [
          IconButton(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => LiveTrackingScreen(
                    bookingId: widget.booking['booking_id'].toString(),
                    carName: widget.booking['car_full_name'] ?? 'Car',
                    renterName: widget.booking['renter_name'] ?? 'Unknown',
                  ),
                ),
              );
            },
            icon: const Icon(Icons.map),
            tooltip: 'Track Location',
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: OptimizedNetworkImage(
                imageUrl: imageUrl,
                height: 200,
                width: double.infinity,
                fit: BoxFit.cover,
                borderRadius: BorderRadius.circular(16),
                errorIcon: Icons.directions_car,
                errorIconSize: 80,
              ),
            ),
            const SizedBox(height: 20),
            
            Text(
              widget.booking['car_full_name'] ?? 'Car',
              style: GoogleFonts.outfit(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              () {
                final total = double.tryParse(
                      widget.booking['total_amount'].toString().replaceAll(',', '')) ?? 0;
                final deposit = (widget.booking['security_deposit'] as num?)?.toDouble() ?? 0;
                final grand = total + deposit;
                final formatted = grand.toStringAsFixed(0).replaceAllMapped(
                    RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},');
                return '₱$formatted Total';
              }(),
              style: GoogleFonts.outfit(
                fontSize: 20,
                fontWeight: FontWeight.w600,
                color: Colors.green.shade700,
              ),
            ),
            const SizedBox(height: 24),
            
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Trip Progress',
                        style: GoogleFonts.outfit(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        '${progress.toStringAsFixed(0)}%',
                        style: GoogleFonts.outfit(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  
                  // Trip Timeline
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.play_circle, size: 16, color: Colors.green.shade700),
                                const SizedBox(width: 6),
                                Text(
                                  'Start',
                                  style: GoogleFonts.inter(
                                    fontSize: 11,
                                    color: Colors.grey.shade600,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              widget.booking['pickup_time_display'] ?? widget.booking['pickup_time'] ?? 'N/A',
                              style: GoogleFonts.outfit(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: Colors.black,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                Text(
                                  'End',
                                  style: GoogleFonts.inter(
                                    fontSize: 11,
                                    color: Colors.grey.shade600,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Icon(Icons.stop_circle, size: 16, color: Colors.red.shade700),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              widget.booking['return_time_display'] ?? widget.booking['return_time'] ?? 'N/A',
                              style: GoogleFonts.outfit(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: Colors.black,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  
                  const SizedBox(height: 16),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: SizedBox(
                      height: 8,
                      child: LinearProgressIndicator(
                        value: progress / 100,
                        backgroundColor: Colors.grey.shade200,
                        valueColor: AlwaysStoppedAnimation<Color>(
                          progress < 33 ? Colors.green :
                          progress < 66 ? Colors.orange : Colors.red
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _buildStat('Days Left', '$daysRemaining'),
                      _buildStat('Total Days', widget.booking['rental_period'] ?? ''),
                      _buildStat('Days Elapsed', '${widget.booking['days_elapsed'] ?? '0'}'),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            
            _buildSection('Renter Information', [
              _buildDetailRow('Name', widget.booking['renter_name'] ?? ''),
              _buildDetailRow('Contact', widget.booking['renter_contact'] ?? ''),
              _buildDetailRow('Email', widget.booking['renter_email'] ?? ''),
            ]),
            const SizedBox(height: 20),
            
            _buildSection('Trip Details', [
              _buildDetailRow(
                'Pickup', 
                '${widget.booking['pickup_date'] ?? ''} at ${widget.booking['pickup_time_display'] ?? widget.booking['pickup_time'] ?? ''}'
              ),
              _buildDetailRow(
                'Return', 
                '${widget.booking['return_date'] ?? ''} at ${widget.booking['return_time_display'] ?? widget.booking['return_time'] ?? ''}'
              ),
              _buildDetailRow('Location', widget.booking['location'] ?? ''),
            ]),
            const SizedBox(height: 20),
            
            // Odometer/Mileage Section
            _buildMileageSection(),
            const SizedBox(height: 30),
            
            // Show End Trip button:
            // - Limited mileage: require start recorded and end not recorded
            // - Unlimited mileage: show always
            if (((widget.booking['has_unlimited_mileage'] == 1) || (widget.booking['has_unlimited_mileage'] == true)) ||
                (widget.booking['odometer_start'] != null && widget.booking['odometer_end'] == null))
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isEnding ? null : _handleEndTrip,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red.shade600,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.grey.shade300,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isEnding
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : Text(
                          'End Trip',
                          style: GoogleFonts.inter(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                ),
              )
            else if (widget.booking['odometer_end'] != null)
              // Show completion message if trip is already ended
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.green.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.check_circle, color: Colors.green.shade700, size: 24),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Trip completed - Odometer readings recorded',
                        style: GoogleFonts.inter(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Colors.green.shade700,
                        ),
                      ),
                    ),
                  ],
                ),
              )
            else
              // Show message that start odometer needs to be recorded first
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.orange.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.orange.shade700, size: 24),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Start odometer must be recorded before ending trip',
                        style: GoogleFonts.inter(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Colors.orange.shade700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStat(String label, String value) {
    return Column(
      children: [
        Text(
          value,
          style: GoogleFonts.outfit(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          label,
          style: GoogleFonts.inter(
            fontSize: 12,
            color: Colors.grey.shade600,
          ),
        ),
      ],
    );
  }

  Widget _buildSection(String title, List<Widget> children) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: GoogleFonts.outfit(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.grey.shade50,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(children: children),
        ),
      ],
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.inter(
              fontSize: 14,
              color: Colors.grey.shade600,
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: GoogleFonts.inter(
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMileageSection() {
    final isUnlimited = (widget.booking['has_unlimited_mileage'] == 1) || (widget.booking['has_unlimited_mileage'] == true);
    if (isUnlimited) {
      return const SizedBox.shrink();
    }
    final odometerStart = widget.booking['odometer_start'];
    final odometerEnd = widget.booking['odometer_end'];
    final startPhoto = widget.booking['odometer_start_photo'];
    final endPhoto = widget.booking['odometer_end_photo'];
    
    // Calculate distance if both readings exist
    final distance = (odometerStart != null && odometerEnd != null)
        ? (int.tryParse(odometerEnd.toString()) ?? 0) - (int.tryParse(odometerStart.toString()) ?? 0)
        : null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Mileage Tracking',
          style: GoogleFonts.outfit(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.grey.shade50,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            children: [
              // Start Odometer
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.play_circle_outline, size: 18, color: Colors.green.shade600),
                            const SizedBox(width: 8),
                            Text(
                              'Start Odometer',
                              style: GoogleFonts.inter(
                                fontSize: 14,
                                color: Colors.grey.shade600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          odometerStart != null ? '$odometerStart km' : 'Not recorded',
                          style: GoogleFonts.outfit(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: odometerStart != null ? Colors.black : Colors.grey.shade400,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (startPhoto != null && startPhoto.toString().trim().isNotEmpty)
                    GestureDetector(
                      onTap: () => _showOdometerPhoto(startPhoto.toString(), 'Start Odometer'),
                      child: Container(
                        width: 70,
                        height: 70,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.green.shade400, width: 2),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.green.withValues(alpha: 0.2),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: OptimizedNetworkImage(
                                imageUrl: 'https://cargoph.online/cargoAdmin/uploads/odometer/${startPhoto.toString().trim()}',
                                width: 70,
                                height: 70,
                                fit: BoxFit.cover,
                                borderRadius: BorderRadius.circular(8),
                                errorIcon: Icons.image_not_supported,
                                errorIconSize: 24,
                              ),
                            ),
                            Positioned(
                              bottom: 2,
                              right: 2,
                              child: Container(
                                padding: const EdgeInsets.all(2),
                                decoration: BoxDecoration(
                                  color: Colors.green.shade700,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Icon(Icons.search, color: Colors.white, size: 12),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
              
              const Divider(height: 24),
              
              // End Odometer
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.stop_circle_outlined, size: 18, color: Colors.red.shade600),
                            const SizedBox(width: 8),
                            Text(
                              'End Odometer',
                              style: GoogleFonts.inter(
                                fontSize: 14,
                                color: Colors.grey.shade600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          odometerEnd != null ? '$odometerEnd km' : 'Not recorded',
                          style: GoogleFonts.outfit(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: odometerEnd != null ? Colors.black : Colors.grey.shade400,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (endPhoto != null && endPhoto.toString().trim().isNotEmpty)
                    GestureDetector(
                      onTap: () => _showOdometerPhoto(endPhoto.toString(), 'End Odometer'),
                      child: Container(
                        width: 70,
                        height: 70,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.red.shade400, width: 2),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.red.withValues(alpha: 0.2),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: OptimizedNetworkImage(
                                imageUrl: 'https://cargoph.online/cargoAdmin/uploads/odometer/${endPhoto.toString().trim()}',
                                width: 70,
                                height: 70,
                                fit: BoxFit.cover,
                                borderRadius: BorderRadius.circular(8),
                                errorIcon: Icons.image_not_supported,
                                errorIconSize: 24,
                              ),
                            ),
                            Positioned(
                              bottom: 2,
                              right: 2,
                              child: Container(
                                padding: const EdgeInsets.all(2),
                                decoration: BoxDecoration(
                                  color: Colors.red.shade700,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Icon(Icons.search, color: Colors.white, size: 12),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
              
              // Distance Traveled
              if (distance != null) ...[
                const Divider(height: 24),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.blue.shade200),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.route, color: Colors.blue.shade700, size: 20),
                      const SizedBox(width: 8),
                      Text(
                        'Distance Traveled: ',
                        style: GoogleFonts.inter(
                          fontSize: 14,
                          color: Colors.grey.shade700,
                        ),
                      ),
                      Text(
                        '$distance km',
                        style: GoogleFonts.outfit(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.blue.shade700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  void _showOdometerPhoto(String photoPath, String title) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        backgroundColor: Colors.transparent,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        title,
                        style: GoogleFonts.outfit(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: OptimizedNetworkImage(
                      imageUrl: ApiConfig.getImageUrl(photoPath),
                      fit: BoxFit.contain,
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
