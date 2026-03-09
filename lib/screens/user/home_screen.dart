import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/widgets/shared/verify_popup.dart';
import 'package:cargo/screens/owner/dashboard.dart';
import 'package:cargo/screens/user/car_list_screen.dart';


class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedNavIndex = 0;
  final TextEditingController _searchController = TextEditingController();
  String _selectedVehicleType = 'car';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        VerifyPopup.showIfNotVerified(context);
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    // Search logic can be implemented here
    // TODO: Implement search functionality
  }

  @override
  Widget build(BuildContext context) {
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
                                builder: (context) =>
                                    const OwnerDashboardScreen(),
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
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 4),
                      child: Row(
                        children: [
                          const Icon(Icons.search,
                              color: Colors.grey, size: 22),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextField(
                              controller: _searchController,
                              onChanged: _onSearchChanged,
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
                            onTap: () {
                              // Filter functionality can be added here
                            },
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: const Icon(
                                Icons.tune,
                                color: Colors.black,
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
                        _buildToggleButton(
                          "Car",
                          _selectedVehicleType == 'car',
                          () {
                            setState(() {
                              _selectedVehicleType = 'car';
                            });
                          },
                        ),
                        const SizedBox(width: 12),
                        _buildToggleButton(
                          "Motorcycle",
                          _selectedVehicleType == 'motorcycle',
                          () {
                            setState(() {
                              _selectedVehicleType = 'motorcycle';
                            });
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    _buildSectionHeader("Best Cars", "View All"),
                    const SizedBox(height: 8),
                    Text(
                      "Available",
                      style: GoogleFonts.poppins(
                        fontSize: 12,
                        color: Colors.grey,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 240,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          _buildCarCard(
                            image: 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=500',
                            name: 'Ferrari FF',
                            rating: 5.0,
                            location: 'Washington DC',
                            seats: 4,
                            price: '\$200',
                          ),
                          _buildCarCard(
                            image: 'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=500',
                            name: 'Tesla Model S',
                            rating: 5.0,
                            location: 'Chicago, USA',
                            seats: 5,
                            price: '\$1100',
                          ),
                          _buildCarCard(
                            image: 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=500',
                            name: 'BMW M4',
                            rating: 4.8,
                            location: 'New York, USA',
                            seats: 4,
                            price: '\$850',
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    _buildSectionHeader("Nearby", "View All"),
                    const SizedBox(height: 12),
                    _buildNearbyCard(
                      image: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=500',
                      name: 'BMW M8',
                      rating: 4.9,
                      location: 'Los Angeles, CA',
                      seats: 4,
                      price: '\$950',
                    ),
                    const SizedBox(height: 24),
                    // You can add more sections here
                  ],
                ),
              ),
            ),
            Positioned(
              bottom: 20,
              left: 20,
              right: 20,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(vertical: 12, horizontal: 20),
                decoration: BoxDecoration(
                  color: Colors.black,
                  borderRadius: BorderRadius.circular(30),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.2),
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
        child: Icon(icon,
            color: isSelected ? Colors.black : Colors.white, size: 24),
      ),
    );
  }

  Widget _buildToggleButton(
      String label, bool isSelected, VoidCallback onTap) {
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
        Text(title,
            style: GoogleFonts.poppins(
                fontSize: 18, fontWeight: FontWeight.bold)),
        GestureDetector(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const CarListScreen(),
              ),
            );
          },
          child: Text(actionText,
              style: GoogleFonts.poppins(color: Colors.grey, fontSize: 13)),
        ),
      ],
    );
  }

  Widget _buildCarCard({
    required String image,
    required String name,
    required double rating,
    required String location,
    required int seats,
    required String price,
  }) {
    return Container(
      width: 180,
      margin: const EdgeInsets.only(right: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withValues(alpha: 0.1),
            blurRadius: 10,
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
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                child: Image.network(
                  image,
                  height: 120,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.favorite_border,
                    size: 18,
                    color: Colors.black,
                  ),
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: GoogleFonts.poppins(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.star, color: Colors.amber, size: 14),
                    const SizedBox(width: 4),
                    Text(
                      rating.toString(),
                      style: GoogleFonts.poppins(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Icon(Icons.location_on, color: Colors.grey, size: 14),
                    const SizedBox(width: 2),
                    Expanded(
                      child: Text(
                        location,
                        style: GoogleFonts.poppins(
                          fontSize: 11,
                          color: Colors.grey,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.event_seat, size: 14, color: Colors.grey),
                        const SizedBox(width: 4),
                        Text(
                          '$seats Seats',
                          style: GoogleFonts.poppins(
                            fontSize: 11,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        const Icon(Icons.access_time, size: 14, color: Colors.grey),
                        const SizedBox(width: 4),
                        Text(
                          '$price/Day',
                          style: GoogleFonts.poppins(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
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

  Widget _buildNearbyCard({
    required String image,
    required String name,
    required double rating,
    required String location,
    required int seats,
    required String price,
  }) {
    return Container(
      height: 200,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withValues(alpha: 0.2),
            blurRadius: 15,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Stack(
          children: [
            Image.network(
              image,
              width: double.infinity,
              height: double.infinity,
              fit: BoxFit.cover,
            ),
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.transparent,
                    Colors.black.withValues(alpha: 0.7),
                  ],
                ),
              ),
            ),
            Positioned(
              top: 12,
              right: 12,
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.favorite_border,
                  size: 20,
                  color: Colors.black,
                ),
              ),
            ),
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: GoogleFonts.poppins(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.star, color: Colors.amber, size: 16),
                      const SizedBox(width: 4),
                      Text(
                        rating.toString(),
                        style: GoogleFonts.poppins(
                          fontSize: 14,
                          fontWeight: FontWeight.w500,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Icon(Icons.location_on, color: Colors.white, size: 16),
                      const SizedBox(width: 4),
                      Text(
                        location,
                        style: GoogleFonts.poppins(
                          fontSize: 13,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.event_seat, size: 16, color: Colors.white),
                          const SizedBox(width: 4),
                          Text(
                            '$seats Seats',
                            style: GoogleFonts.poppins(
                              fontSize: 13,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.access_time, size: 14, color: Colors.black),
                            const SizedBox(width: 4),
                            Text(
                              '$price/Day',
                              style: GoogleFonts.poppins(
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
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}