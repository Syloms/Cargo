import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../widgets/shared/verify_popup.dart';
import '../../models/vehicle_model.dart';
import '../owner/dashboard.dart';
import '../../widgets/shared/vehicle_filter_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool showCars = true;
  int _selectedNavIndex = 0;
  Map<String, dynamic>? _appliedFilters;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        VerifyPopup.showIfNotVerified(context);
      }
    });
  }

  final List<VehicleModel> bestCars = [
    VehicleModel(
      name: "Ferrari FF",
      category: "Sedan",
      location: "Washington, DC",
      price: "\$100/Day",
      image: "assets/car1.jpg",
      rating: 5.0,
      seats: "4 Seats",
    ),
    VehicleModel(
      name: "Tesla Model S",
      category: "Electric",
      location: "Chicago, USA",
      price: "\$130/Day",
      image: "assets/car2.jpg",
      rating: 5.0,
      seats: "5 Seats",
    ),
  ];

  final List<VehicleModel> bestMotorcycles = [
    VehicleModel(
      name: "Yamaha R1",
      category: "Sport",
      location: "Cebu City",
      price: "\$40/Day",
      image: "assets/moto1.jpg",
      rating: 5.0,
    ),
    VehicleModel(
      name: "Kawasaki Ninja",
      category: "Sport",
      location: "Davao City",
      price: "\$55/Day",
      image: "assets/moto2.jpg",
      rating: 4.8,
    ),
  ];

  void _showFilterPopup() {
    print('Filter button clicked!'); // Debug print
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        print('Building VehicleFilterScreen...'); // Debug print
        return const VehicleFilterScreen();
      },
    ).then((filters) {
      if (filters != null) {
        setState(() {
          _appliedFilters = filters;
        });
        print('Applied filters: $filters');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final bestVehicles = showCars ? bestCars : bestMotorcycles;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsets.only(bottom: 100),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          "CARGO",
                          style: GoogleFonts.poppins(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 1,
                          ),
                        ),
                        GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (context) => const OwnerDashboardScreen(),
                              ),
                            );
                          },
                          child: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.grey.shade200,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(Icons.directions_car, size: 28),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.grey.shade100,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      child: Row(
                        children: [
                          const Icon(Icons.search, color: Colors.grey, size: 22),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextField(
                              decoration: InputDecoration(
                                hintText: "Search your dream vehicle...",
                                hintStyle: GoogleFonts.poppins(
                                  color: Colors.grey,
                                  fontSize: 14,
                                ),
                                border: InputBorder.none,
                              ),
                            ),
                          ),
                          GestureDetector(
                            onTap: _showFilterPopup,
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: _appliedFilters != null ? Colors.black : Colors.white,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Icon(
                                Icons.tune,
                                color: _appliedFilters != null ? const Color(0xFFCDFE3D) : Colors.black,
                                size: 20,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _buildToggleButton("Car", showCars, () {
                          setState(() => showCars = true);
                        }),
                        const SizedBox(width: 12),
                        _buildToggleButton("Motorcycle", !showCars, () {
                          setState(() => showCars = false);
                        }),
                      ],
                    ),
                    const SizedBox(height: 20),
                    _buildSectionHeader("Best Vehicle", "View All"),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 240,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        itemCount: bestVehicles.length,
                        itemBuilder: (context, index) {
                          return _buildVehicleCard(bestVehicles[index]);
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Positioned(
              bottom: 20,
              left: 20,
              right: 20,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
                decoration: BoxDecoration(
                  color: Colors.black,
                  borderRadius: BorderRadius.circular(30),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.2),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _buildNavItem(Icons.home_filled, 0),
                    _buildNavItem(Icons.car_rental, 1),
                    _buildNavItem(Icons.mail_outline, 2),
                    _buildNavItem(Icons.person_outline, 3),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildNavItem(IconData icon, int index) {
    final bool isSelected = _selectedNavIndex == index;
    return GestureDetector(
      onTap: () => setState(() => _selectedNavIndex = index),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? Colors.white : Colors.transparent,
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: isSelected ? Colors.black : Colors.white, size: 24),
      ),
    );
  }

  Widget _buildToggleButton(String label, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? Colors.black : Colors.grey.shade200,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: GoogleFonts.poppins(
            color: isSelected ? Colors.white : Colors.black,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    );
  }

  Widget _buildSectionHeader(String title, String actionText) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: GoogleFonts.poppins(fontSize: 18, fontWeight: FontWeight.bold)),
        Text(actionText, style: GoogleFonts.poppins(color: Colors.grey, fontSize: 13)),
      ],
    );
  }

  Widget _buildVehicleCard(VehicleModel vehicle) {
    return Container(
      width: 160,
      margin: const EdgeInsets.only(right: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.grey.shade300, blurRadius: 10, offset: const Offset(0, 4))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: Image.asset(vehicle.image, height: 110, width: double.infinity, fit: BoxFit.cover),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  vehicle.name,
                  style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(vehicle.category, style: GoogleFonts.poppins(fontSize: 11, color: Colors.grey.shade600)),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.location_on, size: 12, color: Colors.grey),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        vehicle.location,
                        style: GoogleFonts.poppins(fontSize: 10, color: Colors.grey),
                        maxLines: 1,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(vehicle.price, style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.bold)),
                    Row(
                      children: [
                        const Icon(Icons.star, size: 12, color: Colors.amber),
                        const SizedBox(width: 2),
                        Text(vehicle.rating.toString(), style: GoogleFonts.poppins(fontSize: 11)),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}