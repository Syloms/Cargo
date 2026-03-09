import 'package:flutter/material.dart';
import 'package:animate_do/animate_do.dart';
import 'package:google_fonts/google_fonts.dart';
import 'car_listing/vehicle_type_selection_screen.dart';
import 'verification/personal_info_screen.dart';

// Services
import '../Owner/mycar/car_services.dart';
import '../Owner/mycar/verification_service.dart';

// Widgets
import '../Owner/mycar/car_card.dart';
import '../Owner/mycar/car_filter_chips.dart';
import '../Owner/mycar/car_stats_section.dart';
import '../Owner/mycar/empty_car_state.dart';
import '../Owner/mycar/car_shimmer.dart';
import '../Owner/mycar/car_detail_page.dart';

// Dialogs
import '../Owner/mycar/verification_dialog.dart';

class MyCarPage extends StatefulWidget {
  final int ownerId;
  const MyCarPage({super.key, required this.ownerId});

  @override
  State<MyCarPage> createState() => _MyCarPageState();
}

class _MyCarPageState extends State<MyCarPage> {
  final CarService _carService = CarService();
  final VerificationService _verificationService = VerificationService();

  List<Map<String, dynamic>> cars = [];
  List<Map<String, dynamic>> filteredCars = [];

  bool isLoading = true;
  bool isVerified = false;
  bool canAddCar = false;
  bool isCheckingVerification = true;
  String searchQuery = "";
  String selectedFilter = "All";

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  /* ---------------- INITIALIZE ---------------- */
  Future<void> _initialize() async {
    await Future.wait([
      checkVerificationStatus(),
      fetchCars(),
    ]);
  }

  /* ---------------- CHECK VERIFICATION STATUS ---------------- */
  Future<void> checkVerificationStatus() async {
    setState(() => isCheckingVerification = true);

    final result = await _verificationService.checkVerification();

    setState(() {
      isVerified = result['isVerified'] ?? false;
      canAddCar = result['canAddCar'] ?? false;
      isCheckingVerification = false;
    });
  }

  /* ---------------- FETCH DATA ---------------- */
  Future<void> fetchCars() async {
    setState(() => isLoading = true);

    final fetchedCars = await _carService.fetchCars(widget.ownerId);

    setState(() {
      cars = fetchedCars;
      isLoading = false;
    });

    applyFilters();
  }

  /* ---------------- FILTER LOGIC ---------------- */
  void applyFilters() {
    String query = searchQuery.toLowerCase();

    filteredCars = cars.where((car) {
      final brand = car['brand'].toString().toLowerCase();
      final model = car['model'].toString().toLowerCase();

      final matchesSearch = brand.contains(query) || model.contains(query);
      final matchesFilter =
          selectedFilter == "All" ||
              selectedFilter.toLowerCase() ==
                  car["status"].toString().toLowerCase();

      return matchesSearch && matchesFilter;
    }).toList();

    setState(() {});
  }

  /* ---------------- DELETE VEHICLE ---------------- */
  Future<void> deleteVehicle(Map<String, dynamic> vehicle) async {
    final vehicleId = int.tryParse(vehicle["id"].toString()) ?? 0;
    final vehicleType = vehicle["vehicle_type"]?.toString() ?? 'car';
    final vehicleName = "${vehicle['brand']} ${vehicle['model']}";
    
    if (vehicleId == 0) {
      _showSnackBar('Invalid vehicle ID', Colors.red);
      return;
    }
    
    // Show confirmation dialog
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Colors.orange.shade700),
            const SizedBox(width: 12),
            Text('Delete Vehicle', style: GoogleFonts.poppins(fontSize: 18)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Are you sure you want to delete this vehicle?',
              style: GoogleFonts.poppins(fontSize: 14),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(
                    vehicleType == 'motorcycle' ? Icons.two_wheeler : Icons.directions_car,
                    color: Colors.grey.shade700,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      vehicleName,
                      style: GoogleFonts.poppins(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'This action cannot be undone.',
              style: GoogleFonts.poppins(
                fontSize: 12,
                color: Colors.red.shade700,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('Cancel', style: GoogleFonts.poppins()),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: Text('Delete', style: GoogleFonts.poppins()),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    // Show loading
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              ),
              const SizedBox(width: 12),
              Text('Deleting vehicle...', style: GoogleFonts.poppins(fontSize: 14)),
            ],
          ),
          duration: const Duration(seconds: 30),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }

    final result = await _carService.deleteVehicle(vehicleId, vehicleType, widget.ownerId);

    // Remove loading snackbar
    if (mounted) {
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
    }

    if (result['success']) {
      // Remove from local list
      cars.removeWhere((car) => car["id"].toString() == vehicleId.toString());
      applyFilters();

      if (mounted) {
        _showSnackBar(result['message'] ?? 'Vehicle deleted successfully', Colors.green);
      }
    } else {
      if (mounted) {
        _showSnackBar(result['message'] ?? 'Failed to delete vehicle', Colors.red);
      }
    }
  }
  
  /* ---------------- SHOW SNACKBAR ---------------- */
  void _showSnackBar(String message, Color backgroundColor) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              backgroundColor == Colors.green 
                  ? Icons.check_circle_outline 
                  : Icons.error_outline,
              color: Colors.white,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(message, style: GoogleFonts.poppins(fontSize: 14)),
            ),
          ],
        ),
        backgroundColor: backgroundColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }


  /* ---------------- HANDLE ADD CAR ---------------- */
  Future<void> handleAddCar() async {
    if (!canAddCar) {
      VerificationDialog.show(
        context,
        onVerifyPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => const PersonalInfoScreen(),
            ),
          );

          if (result == true || mounted) {
            checkVerificationStatus();
          }
        },
      );
      return;
    }

    final result = await Navigator.push(
      context,
      PageRouteBuilder(
        pageBuilder: (_, _, _) =>
            VehicleTypeSelectionScreen(ownerId: widget.ownerId),
        transitionsBuilder: (_, animation, _, child) =>
            FadeTransition(opacity: animation, child: child),
      ),
    );

    if (result == true) {
      fetchCars();
      checkVerificationStatus();
    }
  }


  /* ---------------- BUILD UI ---------------- */
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? const Color(0xFF121212) : Colors.grey.shade50,
      appBar: _buildAppBar(context, colors, isDark),
      floatingActionButton: _buildFAB(isDark),
      body: RefreshIndicator(
        onRefresh: _initialize,
        color: colors.primary,
        child: Column(
          children: [
            // Stats Section
            CarStatsSection(cars: cars),

            // Search Bar
            _buildSearchBar(context, colors, isDark),

            // Filter Chips
            CarFilterChips(
              selectedFilter: selectedFilter,
              onFilterChanged: (filter) {
                setState(() => selectedFilter = filter);
                applyFilters();
              },
            ),

            const SizedBox(height: 8),

            // Car List
            Expanded(
              child: isLoading
                  ? const CarShimmerLoader()
                  : filteredCars.isEmpty
                      ? EmptyCarState(searchQuery: searchQuery)
                      : _buildCarGrid(),
            ),
          ],
        ),
      ),
    );
  }

  /* ---------------- APP BAR ---------------- */
  AppBar _buildAppBar(
      BuildContext context, ColorScheme colors, bool isDark) {
    return AppBar(
      backgroundColor:
          isDark ? const Color(0xFF121212) : colors.surface,
      elevation: 0,
      automaticallyImplyLeading: false,
      title: Text(
        "My Cars",
        style: GoogleFonts.poppins(
          color: colors.onSurface,
          fontWeight: FontWeight.bold,
          fontSize: 22,
          letterSpacing: -0.5,
        ),
      ),
      actions: [
        Padding(
          padding: const EdgeInsets.only(right: 16),
          child: Image.asset("assets/cargo.png", width: 32),
        )
      ],
    );
  }

  /* ---------------- FLOATING ACTION BUTTON ---------------- */
  Widget _buildFAB(bool isDark) {
    return FloatingActionButton.extended(
      backgroundColor:
          canAddCar ? (isDark ? Colors.white : Colors.black) : Colors.grey.shade400,
      onPressed: isCheckingVerification ? null : handleAddCar,
      icon: Icon(
        Icons.add,
        color: canAddCar
            ? (isDark ? Colors.black : Colors.white)
            : Colors.grey.shade600,
      ),
      label: Text(
        canAddCar ? "Add Car" : "Verify First",
        style: GoogleFonts.poppins(
          fontWeight: FontWeight.w600,
          color: canAddCar
              ? (isDark ? Colors.black : Colors.white)
              : Colors.grey.shade600,
        ),
      ),
    );
  }

  /* ---------------- SEARCH BAR ---------------- */
  Widget _buildSearchBar(
      BuildContext context, ColorScheme colors, bool isDark) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: TextField(
        onChanged: (value) {
          searchQuery = value;
          applyFilters();
        },
        decoration: InputDecoration(
          hintText: "Search car...",
          hintStyle: GoogleFonts.poppins(
            color: isDark
                ? colors.onSurface.withValues(alpha :0.5)
                : Colors.grey.shade500,
            fontSize: 14,
          ),
          prefixIcon: Icon(Icons.search,
              color: isDark
                  ? colors.onSurface.withValues(alpha :0.7)
                  : Colors.grey.shade600),
          suffixIcon: searchQuery.isNotEmpty
              ? IconButton(
                  icon: Icon(Icons.clear,
                      color: isDark
                          ? colors.onSurface.withValues(alpha :0.7)
                          : Colors.grey.shade600),
                  onPressed: () {
                    setState(() {
                      searchQuery = "";
                      applyFilters();
                    });
                  },
                )
              : null,
          filled: true,
          fillColor:
              isDark ? const Color(0xFF1E1E1E) : Colors.white,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
                color: isDark
                    ? Colors.white.withValues(alpha :0.1)
                    : Colors.grey.shade200),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
                color: isDark
                    ? Colors.white.withValues(alpha :0.1)
                    : Colors.grey.shade200),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
              color: isDark ? colors.primary : Colors.black,
              width: 1.5,
            ),
          ),
        ),
      ),
    );
  }

  /* ---------------- CAR GRID ---------------- */
  Widget _buildCarGrid() {
    final crossAxisCount = MediaQuery.of(context).size.width >= 600 ? 3 : 2;
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filteredCars.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount,
        crossAxisSpacing: 12,
        mainAxisSpacing: 14,
        childAspectRatio: 0.72,
      ),
      itemBuilder: (_, index) {
        final car = filteredCars[index];

        return FadeInUp(
          duration: Duration(milliseconds: 200 + (index * 50)),
          child: CarCard(
            car: car,
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => CarDetailPage(
                    car: car,
                    ownerId: widget.ownerId, // Pass ownerId as fallback
                    onDelete: () => deleteVehicle(car),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
