import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:cargo/config/api_config.dart';
import 'package:cargo/widgets/loading_widgets.dart';

class ReceiptViewerScreen extends StatefulWidget {
  final int bookingId;

  const ReceiptViewerScreen({
    super.key,
    required this.bookingId,
  });

  @override
  State<ReceiptViewerScreen> createState() => _ReceiptViewerScreenState();
}

class _ReceiptViewerScreenState extends State<ReceiptViewerScreen> {
  bool _isLoading = true;
  Map<String, dynamic>? _receiptData;
  String? _error;

  final String baseUrl = "${GlobalApiConfig.baseUrl}/";

  @override
  void initState() {
    super.initState();
    _fetchReceipt();
  }

  Future<void> _fetchReceipt() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final url = Uri.parse("${baseUrl}api/receipts/get_receipt.php?booking_id=${widget.bookingId}");
      final response = await http.get(url);

      if (response.statusCode == 200) {
        final result = jsonDecode(response.body);

        if (result['success'] == true) {
          setState(() {
            _receiptData = result['receipt'];
            _isLoading = false;
          });
        } else {
          setState(() {
            _error = result['message'] ?? 'Failed to load receipt';
            _isLoading = false;
          });
        }
      } else {
        setState(() {
          _error = 'Server error: ${response.statusCode}';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Network error: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _downloadReceipt() async {
    if (_receiptData == null) return;

    try {
      final receiptUrl = _receiptData!['receipt_url'];
      
      // Check if receipt_url is empty or null
      if (receiptUrl == null || receiptUrl.toString().trim().isEmpty) {
        _showSnackBar('Receipt PDF not available. Viewing on-screen only.', isError: true);
        return;
      }

      final url = Uri.parse(receiptUrl);

      if (await canLaunchUrl(url)) {
        await launchUrl(url, mode: LaunchMode.externalApplication);
        _showSnackBar('Opening receipt...', isError: false);
      } else {
        _showSnackBar('Cannot open receipt URL', isError: true);
      }
    } catch (e) {
      _showSnackBar('Receipt download not available: ${e.toString()}', isError: true);
    }
  }

  bool _hasReceiptUrl() {
    if (_receiptData == null) return false;
    final receiptUrl = _receiptData!['receipt_url'];
    return receiptUrl != null && receiptUrl.toString().trim().isNotEmpty;
  }

  void _showSnackBar(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : Colors.green,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: isDark ? colors.surfaceContainerLowest : Colors.grey.shade100,
      appBar: AppBar(
        backgroundColor: isDark ? colors.surface : Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: colors.onSurface),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Payment Receipt',
          style: GoogleFonts.poppins(
            color: colors.onSurface,
            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
        actions: [
          if (!_isLoading && _receiptData != null && _hasReceiptUrl())
            IconButton(
              icon: Icon(Icons.download, color: colors.onSurface),
              onPressed: _downloadReceipt,
            ),
        ],
      ),
      body: _isLoading
          ? const LoadingScreen(message: 'Loading receipt...')
          : _error != null
              ? _buildErrorState()
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: _buildReceiptContent(),
                ),
    );
  }

  Widget _buildErrorState() {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 80, color: Colors.red.shade300),
          const SizedBox(height: 16),
          Text(
            'Failed to load receipt',
            style: GoogleFonts.poppins(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: colors.onSurface,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _error ?? 'Unknown error',
            style: GoogleFonts.poppins(
              fontSize: 14,
              color: colors.onSurfaceVariant,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _fetchReceipt,
            style: ElevatedButton.styleFrom(
              backgroundColor: colors.primary,
              foregroundColor: colors.onPrimary,
              padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
            ),
            child: Text('Retry', style: GoogleFonts.poppins()),
          ),
        ],
      ),
    );
  }

  Widget _buildReceiptContent() {
    if (_receiptData == null) return const SizedBox();

    final receiptNo = _receiptData!['receipt_no'] ?? 'N/A';
    final bookingId = _receiptData!['booking_id'] ?? 'N/A';
    final dateIssued = _receiptData!['created_at'] ?? '';
    final status = _receiptData!['status'] ?? 'PAID';
    final paymentStatus = _receiptData!['payment_status'] ?? 'N/A';
    final verifiedAt = _receiptData!['payment_verified_at'] ?? _receiptData!['verified_at'];

    // Renter info
    final renterName = _receiptData!['renter_name'] ?? 'N/A';
    final renterEmail = _receiptData!['renter_email'] ?? 'N/A';
    final renterContact = _receiptData!['renter_contact'] ?? 'N/A';

    // Rental details
    final carName = _receiptData!['car_name'] ?? 'N/A';
    final plateNumber = _receiptData!['plate_number'] ?? 'N/A';
    final pickupDate = _receiptData!['pickup_date'] ?? '';
    final returnDate = _receiptData!['return_date'] ?? '';
    final duration = _receiptData!['duration'] ?? 'N/A';
    final pickupTime = _receiptData!['pickup_time'] ?? '';
    final returnTime = _receiptData!['return_time'] ?? '';

    // Payment details
    final dailyRate       = double.tryParse(_receiptData!['daily_rate']?.toString() ?? '') ?? 0;
    final rentalDays      = int.tryParse(_receiptData!['rental_days']?.toString() ?? '') ?? 0;
    final baseRental      = double.tryParse(_receiptData!['base_rental']?.toString() ?? '') ?? 0;
    final serviceFee      = double.tryParse(_receiptData!['service_fee']?.toString() ?? '') ?? 0;
    final baseAmount      = double.tryParse(_receiptData!['amount']?.toString() ?? '') ?? 0;
    final securityDeposit = double.tryParse(_receiptData!['security_deposit']?.toString() ?? '') ?? 0;
    final grandTotal      = double.tryParse(_receiptData!['grand_total']?.toString() ?? '') ?? (baseAmount + securityDeposit);
    final paymentMethod   = _receiptData!['payment_method'] ?? 'N/A';
    final paymentReference = _receiptData!['payment_reference'] ?? 'N/A';
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Column(
      children: [
        // Header Card — always dark for receipt look
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
          decoration: BoxDecoration(
            color: Colors.grey.shade900,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            children: [
              Text(
                'CarGo',
                style: GoogleFonts.poppins(
                  fontSize: 32,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                  letterSpacing: 2,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'PAYMENT RECEIPT',
                style: GoogleFonts.poppins(
                  fontSize: 13,
                  color: Colors.white60,
                  letterSpacing: 2,
                ),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.green.shade600,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  status.toUpperCase(),
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Receipt Information
        _buildSection(
          title: 'Receipt Information',
          children: [
            _buildInfoRow('Receipt No', receiptNo),
            _buildInfoRow('Booking ID', '#BK-$bookingId'),
            _buildInfoRow('Date Issued', _formatDateTime(dateIssued)),
            _buildInfoRow('Payment Status', paymentStatus.toString().toUpperCase()),
            if (verifiedAt != null && verifiedAt.toString().isNotEmpty)
              _buildInfoRow('Verified At', _formatDateTime(verifiedAt.toString())),
          ],
        ),

        const SizedBox(height: 16),

        // Renter Information
        _buildSection(
          title: 'Renter Information',
          children: [
            _buildInfoRow('Name', renterName),
            _buildInfoRow('Email', renterEmail),
            _buildInfoRow('Contact', renterContact),
          ],
        ),

        const SizedBox(height: 16),

        // Rental Details
        _buildSection(
          title: 'Rental Details',
          children: [
            _buildInfoRow('Vehicle', carName),
            _buildInfoRow('Plate Number', plateNumber),
            _buildInfoRow('Pickup Date', _formatDate(pickupDate)),
            _buildInfoRow('Return Date', _formatDate(returnDate)),
            if (pickupTime.toString().isNotEmpty)
              _buildInfoRow('Pickup Time', pickupTime),
            if (returnTime.toString().isNotEmpty)
              _buildInfoRow('Return Time', returnTime),
            _buildInfoRow('Duration', duration),
          ],
        ),

        const SizedBox(height: 16),

        // Payment Summary
        _buildSection(
          title: 'Payment Summary',
          children: [
            if (dailyRate > 0)
              _buildInfoRow('Daily Rate', '₱${dailyRate.toStringAsFixed(2)}/day'),
            if (rentalDays > 0)
              _buildInfoRow('Rental Days', '$rentalDays day${rentalDays != 1 ? "s" : ""}'),
            _buildInfoRow(
              'Base Rental',
              '₱${(baseRental > 0 ? baseRental : baseAmount).toStringAsFixed(2)}',
            ),
            if (serviceFee > 0)
              _buildInfoRow('Service Fee (5%)', '₱${serviceFee.toStringAsFixed(2)}'),
            if (securityDeposit > 0)
              _buildInfoRow('Security Deposit (Held)', '₱${securityDeposit.toStringAsFixed(2)}'),
            const Divider(height: 20, thickness: 1),
            _buildInfoRow('Grand Total', '₱${grandTotal.toStringAsFixed(2)}', isHighlighted: true),
            const SizedBox(height: 8),
            _buildInfoRow('Payment Method', paymentMethod.toUpperCase()),
            _buildInfoRow('Reference', paymentReference),
          ],
        ),

        const SizedBox(height: 24),

        // Footer
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? colors.surfaceContainer : Colors.grey.shade200,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            children: [
              Text(
                'Thank you for choosing CarGo!',
                style: GoogleFonts.poppins(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: colors.onSurface,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              Text(
                'This is a computer-generated receipt and does not require a signature.',
                style: GoogleFonts.poppins(
                  fontSize: 11,
                  color: colors.onSurfaceVariant,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Download Button (only show if receipt URL exists)
        if (_hasReceiptUrl())
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _downloadReceipt,
              icon: const Icon(Icons.download),
              label: Text(
                'Download Receipt PDF',
                style: GoogleFonts.poppins(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.grey.shade900,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          )
        else
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: isDark ? colors.surfaceContainer : Colors.orange.shade50,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isDark ? colors.outlineVariant : Colors.orange.shade200,
              ),
            ),
            child: Row(
              children: [
                Icon(Icons.info_outline,
                    color: isDark ? colors.onSurfaceVariant : Colors.orange.shade700),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'PDF download not available. You can view and screenshot this receipt.',
                    style: GoogleFonts.poppins(
                      fontSize: 12,
                      color: isDark ? colors.onSurfaceVariant : Colors.orange.shade900,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _buildSection({
    required String title,
    required List<Widget> children,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? colors.surfaceContainer : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: isDark
            ? Border.all(color: colors.outlineVariant.withValues(alpha: 0.4))
            : null,
        boxShadow: isDark
            ? null
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.06),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: GoogleFonts.poppins(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: colors.onSurface,
              letterSpacing: 0.3,
            ),
          ),
          const Divider(height: 20),
          ...children,
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value, {bool isHighlighted = false}) {
    final colors = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: GoogleFonts.poppins(
                fontSize: 12,
                color: colors.onSurfaceVariant,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: GoogleFonts.poppins(
                fontSize: 13,
                fontWeight: isHighlighted ? FontWeight.bold : FontWeight.w500,
                color: isHighlighted ? Colors.green.shade600 : colors.onSurface,
              ),
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(String dateString) {
    try {
      final date = DateTime.parse(dateString);
      return DateFormat('MMMM dd, yyyy').format(date);
    } catch (e) {
      return dateString;
    }
  }

  String _formatDateTime(String dateString) {
    try {
      final date = DateTime.parse(dateString);
      return DateFormat('MMMM dd, yyyy hh:mm a').format(date);
    } catch (e) {
      return dateString;
    }
  }
}
