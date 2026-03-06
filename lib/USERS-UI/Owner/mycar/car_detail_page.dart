import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import './status_helper.dart';
import './api_constants.dart';
import './delete_car_dialog.dart';
import './car_services.dart';
import '../calendar/enhanced_vehicle_calendar.dart';

class CarDetailPage extends StatefulWidget {
  final Map<String, dynamic> car;
  final VoidCallback? onDelete;
  final int? ownerId;

  const CarDetailPage({
    super.key,
    required this.car,
    this.onDelete,
    this.ownerId,
  });

  @override
  State<CarDetailPage> createState() => _CarDetailPageState();
}

class _CarDetailPageState extends State<CarDetailPage> {
  late double _currentPrice;

  String get status => (widget.car['status'] ?? 'Unknown').toString().toLowerCase();
  bool get isApproved => status == 'approved';
  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';
  bool get isRented => status == 'rented';

  @override
  void initState() {
    super.initState();
    _currentPrice = double.tryParse(widget.car['price_per_day']?.toString() ?? '0') ?? 0;
  }

  // -------------------------------------------------------
  // EDIT PRICE BOTTOM SHEET
  // -------------------------------------------------------
  void _showEditPriceSheet() {
    final ownerIdFromCar = int.tryParse(widget.car['owner_id']?.toString() ?? '0') ?? 0;
    final finalOwnerId = ownerIdFromCar > 0 ? ownerIdFromCar : (widget.ownerId ?? 0);
    final vehicleId = int.tryParse(widget.car['id']?.toString() ?? '0') ?? 0;
    final vehicleType = widget.car['vehicle_type']?.toString() ?? 'car';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditPriceSheet(
        carId: vehicleId,
        ownerId: finalOwnerId,
        vehicleType: vehicleType,
        currentPrice: _currentPrice,
        onPriceUpdated: (newPrice) {
          setState(() => _currentPrice = newPrice);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade50,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(context),
          SliverToBoxAdapter(
            child: Column(
              children: [
                _buildStatusBanner(context),
                _buildCarInfo(context),
                _buildSpecifications(context),
                _buildPricing(context),
                if (isRented) _buildRentalInfo(context),
                if (isRejected) _buildRejectionReason(context),
                const SizedBox(height: 100),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: _buildActionButtons(context),
    );
  }

  Widget _buildAppBar(BuildContext context) {
    final imageUrl = ApiConstants.getCarImageUrl(widget.car['image']);

    return SliverAppBar(
      expandedHeight: 280,
      pinned: true,
      backgroundColor: Theme.of(context).iconTheme.color,
      leading: IconButton(
        icon: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: 0.5),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.arrow_back, color: Colors.white),
        ),
        onPressed: () => Navigator.pop(context),
      ),
      flexibleSpace: FlexibleSpaceBar(
        background: Stack(
          fit: StackFit.expand,
          children: [
            Image.network(
              imageUrl,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                color: Colors.grey.shade300,
                child: Icon(Icons.directions_car, size: 80, color: Colors.grey.shade500),
              ),
            ),
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black.withValues(alpha: 0.7)],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      margin: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: StatusHelper.getStatusColor(status).withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: StatusHelper.getStatusColor(status).withValues(alpha: 0.3),
          width: 2,
        ),
      ),
      child: Row(
        children: [
          Icon(StatusHelper.getStatusIcon(status), color: StatusHelper.getStatusColor(status), size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  status.toUpperCase(),
                  style: GoogleFonts.poppins(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: StatusHelper.getStatusColor(status),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _getStatusMessage(),
                  style: GoogleFonts.poppins(fontSize: 13, color: Colors.grey.shade700),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _getStatusMessage() {
    if (isPending) return "Your car is under review by admin";
    if (isApproved) return "Your car is live and available for rent";
    if (isRejected) return "Your car listing was not approved";
    if (isRented) return "This car is currently being rented";
    return "Status unknown";
  }

  Widget _buildCarInfo(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            "${widget.car['brand']} ${widget.car['model']}",
            style: GoogleFonts.poppins(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.black87),
          ),
          const SizedBox(height: 8),
          Text(
            (widget.car['car_year'] ?? widget.car['motorcycle_year'])?.toString() ?? 'N/A',
            style: GoogleFonts.poppins(fontSize: 16, color: Colors.grey.shade600),
          ),
        ],
      ),
    );
  }

  Widget _buildSpecifications(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text("Specifications", style: GoogleFonts.poppins(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black87)),
          const SizedBox(height: 16),
          if (widget.car['vehicle_type'] == 'motorcycle') ...[
            _buildSpecRow(Icons.speed_outlined, "Engine", widget.car['engine_displacement']?.toString() ?? 'N/A'),
            _buildSpecRow(Icons.settings_outlined, "Transmission", widget.car['transmission_type']?.toString() ?? 'N/A'),
          ] else ...[
            _buildSpecRow(Icons.local_gas_station_outlined, "Fuel Type", widget.car['fuel_type']?.toString() ?? 'N/A'),
            _buildSpecRow(Icons.settings_outlined, "Transmission", widget.car['transmission']?.toString() ?? 'N/A'),
            _buildSpecRow(Icons.event_seat_outlined, "Seats", (int.tryParse(widget.car['seat']?.toString() ?? '0') ?? 0) > 0 ? widget.car['seat'].toString() : 'N/A'),
          ],
          _buildSpecRow(Icons.palette_outlined, "Color", widget.car['color']?.toString() ?? 'N/A'),
          _buildSpecRow(Icons.confirmation_number_outlined, "Plate Number", widget.car['plate_number']?.toString() ?? 'N/A'),
        ],
      ),
    );
  }

  Widget _buildSpecRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Colors.grey.shade600),
          const SizedBox(width: 12),
          Expanded(child: Text(label, style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey.shade700))),
          Text(value, style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.black87)),
        ],
      ),
    );
  }

  Widget _buildPricing(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [Colors.black, Colors.grey.shade800], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("Rental Price", style: GoogleFonts.poppins(fontSize: 14, color: Colors.white70)),
              const SizedBox(height: 4),
              Text(
                "₱${_currentPrice % 1 == 0 ? _currentPrice.toInt() : _currentPrice.toStringAsFixed(2)}/day",
                style: GoogleFonts.poppins(fontSize: 28, fontWeight: FontWeight.bold, color: Colors.white),
              ),
            ],
          ),
          // Edit Price Button
          if (!isRented)
            GestureDetector(
              onTap: _showEditPriceSheet,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.edit, color: Colors.white, size: 16),
                    const SizedBox(width: 6),
                    Text("Edit Price", style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white)),
                  ],
                ),
              ),
            )
          else
            Icon(Icons.payments_rounded, size: 48, color: Colors.white.withValues(alpha: 0.2)),
        ],
      ),
    );
  }

  Widget _buildRentalInfo(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.blue.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.blue.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.info_outline, color: Colors.blue.shade700),
              const SizedBox(width: 8),
              Text("Current Rental", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.blue.shade900)),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            "This car is currently being rented. You cannot edit or delete it until the rental period ends.",
            style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey.shade700),
          ),
        ],
      ),
    );
  }

  Widget _buildRejectionReason(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.error_outline, color: Colors.red.shade700),
              const SizedBox(width: 8),
              Text("Rejection Reason", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.red.shade900)),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            widget.car['rejection_reason']?.toString() ??
                "Your car listing did not meet the requirements. Please review and resubmit with correct information.",
            style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey.shade700),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context) {
    final ownerIdFromCar = int.tryParse(widget.car['owner_id']?.toString() ?? '0') ?? 0;
    final finalOwnerId = ownerIdFromCar > 0 ? ownerIdFromCar : (widget.ownerId ?? 0);
    final vehicleId = int.tryParse(widget.car['id']?.toString() ?? '0') ?? 0;
    final vehicleType = widget.car['vehicle_type']?.toString() ?? 'car';
    final vehicleName = "${widget.car['brand']} ${widget.car['model']}";

    debugPrint('🔍 CarDetailPage - Full car data: ${widget.car}');
    debugPrint('🔍 CarDetailPage - ownerIdFromCar: $ownerIdFromCar, fallback: ${widget.ownerId}, final: $finalOwnerId');
    debugPrint('🔍 CarDetailPage - vehicleId: $vehicleId, vehicleType: $vehicleType');

    if (finalOwnerId == 0 || vehicleId == 0) {
      debugPrint('❌ Invalid owner_id or vehicle_id - car data might be corrupted');
    }

    if (isRented) {
      return _bottomBar(
        child: SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => EnhancedVehicleCalendar(ownerId: finalOwnerId, vehicleId: vehicleId, vehicleType: vehicleType, vehicleName: vehicleName),
            )),
            icon: const Icon(Icons.calendar_month),
            label: Text("View Availability", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600)),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.blue, foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), elevation: 0,
            ),
          ),
        ),
      );
    }

    if (isApproved) {
      return _bottomBar(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(
                  builder: (_) => EnhancedVehicleCalendar(ownerId: finalOwnerId, vehicleId: vehicleId, vehicleType: vehicleType, vehicleName: vehicleName),
                )),
                icon: const Icon(Icons.calendar_month),
                label: Text("Manage Availability", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green.shade600, foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), elevation: 0,
                ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () async {
                  final confirm = await DeleteCarDialog.show(context);
                  if (confirm == true && widget.onDelete != null) {
                    widget.onDelete!();
                    if (context.mounted) Navigator.pop(context);
                  }
                },
                icon: const Icon(Icons.delete_outline),
                label: Text("Delete Vehicle", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red, side: BorderSide(color: Colors.red.shade300, width: 2),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ),
      );
    }

    return _bottomBar(
      child: SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          onPressed: () async {
            final confirm = await DeleteCarDialog.show(context);
            if (confirm == true && widget.onDelete != null) {
              widget.onDelete!();
              if (context.mounted) Navigator.pop(context);
            }
          },
          icon: const Icon(Icons.delete_outline),
          label: Text("Delete Vehicle", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600)),
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.red, side: BorderSide(color: Colors.red.shade300, width: 2),
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
      ),
    );
  }

  Widget _bottomBar({required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10, offset: const Offset(0, -2))],
      ),
      child: SafeArea(child: child),
    );
  }
}

// -------------------------------------------------------
// EDIT PRICE BOTTOM SHEET (separate StatefulWidget)
// -------------------------------------------------------
class _EditPriceSheet extends StatefulWidget {
  final int carId;
  final int ownerId;
  final String vehicleType;
  final double currentPrice;
  final ValueChanged<double> onPriceUpdated;

  const _EditPriceSheet({
    required this.carId,
    required this.ownerId,
    required this.vehicleType,
    required this.currentPrice,
    required this.onPriceUpdated,
  });

  @override
  State<_EditPriceSheet> createState() => _EditPriceSheetState();
}

class _EditPriceSheetState extends State<_EditPriceSheet> {
  final _priceController = TextEditingController();
  final _service = CarService();

  bool _loadingPrediction = true;
  bool _saving = false;
  Map<String, dynamic>? _prediction;
  String? _error;

  @override
  void initState() {
    super.initState();
    _priceController.text = widget.currentPrice.toInt().toString();
    _fetchPrediction();
  }

  @override
  void dispose() {
    _priceController.dispose();
    super.dispose();
  }

  Future<void> _fetchPrediction() async {
    final result = await _service.getPricePrediction(widget.carId, widget.vehicleType);
    if (!mounted) return;
    setState(() {
      _loadingPrediction = false;
      if (result['success'] == true) {
        _prediction = result;
      } else {
        _error = result['message'] ?? 'Could not load predictions';
      }
    });
  }

  Future<void> _save() async {
    final price = double.tryParse(_priceController.text.trim());
    if (price == null || price < 300) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Minimum price is ₱300/day'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() => _saving = true);
    final result = await _service.updateCarPrice(widget.carId, widget.ownerId, widget.vehicleType, price);
    if (!mounted) return;
    setState(() => _saving = false);

    if (result['success'] == true) {
      widget.onPriceUpdated(price);
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Price updated successfully'), backgroundColor: Colors.green),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['message'] ?? 'Failed to update price'), backgroundColor: Colors.red),
      );
    }
  }

  void _applysuggested(double price) {
    _priceController.text = price.toInt().toString();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        left: 24, right: 24, top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle bar
            Center(
              child: Container(
                width: 40, height: 4,
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const SizedBox(height: 20),

            Text("Edit Rental Price", style: GoogleFonts.poppins(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text("Current: ₱${widget.currentPrice.toInt()}/day", style: GoogleFonts.poppins(fontSize: 13, color: Colors.grey.shade600)),
            const SizedBox(height: 20),

            // Price input
            TextField(
              controller: _priceController,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: GoogleFonts.poppins(fontSize: 22, fontWeight: FontWeight.bold),
              decoration: InputDecoration(
                prefixText: '₱ ',
                prefixStyle: GoogleFonts.poppins(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.black87),
                suffixText: '/day',
                suffixStyle: GoogleFonts.poppins(fontSize: 14, color: Colors.grey),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                filled: true,
                fillColor: Colors.grey.shade50,
              ),
            ),
            const SizedBox(height: 24),

            // Prediction section
            Text("Smart Price Suggestions", style: GoogleFonts.poppins(fontSize: 15, fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),

            if (_loadingPrediction)
              const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()))
            else if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Text(_error!, style: GoogleFonts.poppins(fontSize: 13, color: Colors.red)),
              )
            else if (_prediction != null) ...[
              // Suggestion chips
              Row(
                children: [
                  _PriceChip(
                    label: "Budget",
                    price: (_prediction!['suggested_min'] as num).toDouble(),
                    color: Colors.green,
                    onTap: _applysuggested,
                  ),
                  const SizedBox(width: 10),
                  _PriceChip(
                    label: "Recommended",
                    price: (_prediction!['suggested'] as num).toDouble(),
                    color: Colors.blue,
                    onTap: _applysuggested,
                    highlighted: true,
                  ),
                  const SizedBox(width: 10),
                  _PriceChip(
                    label: "Premium",
                    price: (_prediction!['suggested_max'] as num).toDouble(),
                    color: Colors.orange,
                    onTap: _applysuggested,
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Factors breakdown
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.grey.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("Price Factors", style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.black87)),
                    const SizedBox(height: 8),
                    ...(_prediction!['factors'] as List).map((f) {
                      final mult = (f['multiplier'] as num).toDouble();
                      final isBoost = mult > 1.0;
                      final isNeutral = mult == 1.0;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          children: [
                            Icon(
                              isNeutral ? Icons.remove_circle_outline : (isBoost ? Icons.arrow_upward : Icons.arrow_downward),
                              size: 14,
                              color: isNeutral ? Colors.grey : (isBoost ? Colors.green : Colors.red),
                            ),
                            const SizedBox(width: 6),
                            Expanded(child: Text(f['label'], style: GoogleFonts.poppins(fontSize: 12, color: Colors.grey.shade700))),
                            Text(
                              isNeutral ? "neutral" : "${isBoost ? '+' : ''}${((mult - 1) * 100).toStringAsFixed(0)}%",
                              style: GoogleFonts.poppins(
                                fontSize: 12, fontWeight: FontWeight.w600,
                                color: isNeutral ? Colors.grey : (isBoost ? Colors.green : Colors.red),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 24),

            // Save button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _saving ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.black,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  elevation: 0,
                ),
                child: _saving
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Text("Save Price", style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PriceChip extends StatelessWidget {
  final String label;
  final double price;
  final Color color;
  final ValueChanged<double> onTap;
  final bool highlighted;

  const _PriceChip({
    required this.label,
    required this.price,
    required this.color,
    required this.onTap,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: () => onTap(price),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          decoration: BoxDecoration(
            color: highlighted ? color : color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: color.withValues(alpha: highlighted ? 1.0 : 0.4)),
          ),
          child: Column(
            children: [
              Text(label, style: GoogleFonts.poppins(fontSize: 11, color: highlighted ? Colors.white : color, fontWeight: FontWeight.w500)),
              const SizedBox(height: 2),
              Text(
                "₱${price.toInt()}",
                style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.bold, color: highlighted ? Colors.white : color),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
