import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool showCars = true;

  // Car categories
  final List<Map<String, dynamic>> carCategories = [
    {"name": "Lamborghini", "icon": Icons.directions_car},
    {"name": "Tesla", "icon": Icons.electric_car},
    {"name": "BMW", "icon": Icons.drive_eta},
    {"name": "Audi", "icon": Icons.car_rental},
  ];

  // Motorcycle categories
  final List<Map<String, dynamic>> motorcycleCategories = [
    {"name": "Sport", "icon": Icons.two_wheeler},
    {"name": "Cruiser", "icon": Icons.motorcycle},
    {"name": "Touring", "icon": Icons.sports_motorsports},
    {"name": "Scooter", "icon": Icons.moped},
  ];

  // Best Cars
  final List<Map<String, dynamic>> bestCars = [
    {
      "name": "Ferrari FF",
      "category": "Sedan",
      "location": "Washington, DC",
      "price": "\$100/Day",
      "image": "assets/car1.jpg",
      "rating": 5.0,
      "seats": "4 Seats",
    },
    {
      "name": "Tesla Model S",
      "category": "Electric",
      "location": "Chicago, USA",
      "price": "\$130/Day",
      "image": "assets/car2.jpg",
      "rating": 5.0,
      "seats": "5 Seats",
    },
  ];

  // Best Motorcycles
  final List<Map<String, dynamic>> bestMotorcycles = [
    {
      "name": "Yamaha R1",
      "category": "Sport",
      "location": "Cebu City",
      "price": "\$40/Day",
      "image": "assets/moto1.jpg",
      "rating": 5.0,
    },
    {
      "name": "Kawasaki Ninja",
      "category": "Sport",
      "location": "Davao City",
      "price": "\$55/Day",
      "image": "assets/moto2.jpg",
      "rating": 4.8,
    },
  ];

  // Newly Added Cars
  final List<Map<String, dynamic>> newCars = [
    {
      "name": "BMW M5",
      "category": "Luxury",
      "location": "New York, USA",
      "price": "\$150/Day",
      "image": "assets/car3.jpg",
      "rating": 4.9,
      "seats": "5 Seats",
    },
  ];

  // Newly Added Motorcycles
  final List<Map<String, dynamic>> newMotorcycles = [
    {
      "name": "Harley Davidson",
      "category": "Cruiser",
      "location": "Manila",
      "price": "\$60/Day",
      "image": "assets/moto1.jpg",
      "rating": 4.7,
    },
  ];

  @override
  Widget build(BuildContext context) {
    final categories = showCars ? carCategories : motorcycleCategories;
    final bestVehicles = showCars ? bestCars : bestMotorcycles;
    final newVehicles = showCars ? newCars : newMotorcycles;

    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: BottomNavigationBar(
        selectedItemColor: Colors.black,
        unselectedItemColor: Colors.grey,
        showSelectedLabels: false,
        showUnselectedLabels: false,
        type: BottomNavigationBarType.fixed,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home_filled), label: "Home"),
          BottomNavigationBarItem(icon: Icon(Icons.car_rental), label: "Bookings"),
          BottomNavigationBarItem(icon: Icon(Icons.mail), label: "Message"),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: "Profile"),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // 🔹 Top Bar
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
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade200,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.directions_car, size: 28),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // 🔹 Search Bar
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
                            hintText: "Search your dream car...",
                            hintStyle: GoogleFonts.poppins(
                              color: Colors.grey,
                              fontSize: 14,
                            ),
                            border: InputBorder.none,
                          ),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.tune, color: Colors.black, size: 20),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // 🔹 Category Icons (Car/Motorcycle brands or types)
                SizedBox(
                  height: 90,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    itemCount: categories.length,
                    itemBuilder: (context, index) {
                      final category = categories[index];
                      return Container(
                        width: 70,
                        margin: const EdgeInsets.only(right: 12),
                        child: Column(
                          children: [
                            Container(
                              height: 60,
                              width: 60,
                              decoration: BoxDecoration(
                                color: Colors.black,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                category["icon"],
                                color: Colors.white,
                                size: 28,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              category["name"],
                              style: GoogleFonts.poppins(
                                fontSize: 10,
                                fontWeight: FontWeight.w500,
                              ),
                              textAlign: TextAlign.center,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                const SizedBox(height: 10),

                // 🔹 Toggle Buttons (Car / Motorcycle)
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

                // 🔹 Best Vehicle Section
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
                const SizedBox(height: 24),

                // 🔹 Newly Added Section
                _buildSectionHeader("Newly", "View All"),
                const SizedBox(height: 12),
                SizedBox(
                  height: 280,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    itemCount: newVehicles.length,
                    itemBuilder: (context, index) {
                      return _buildLargeVehicleCard(newVehicles[index]);
                    },
                  ),
                ),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
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
        Text(
          title,
          style: GoogleFonts.poppins(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          actionText,
          style: GoogleFonts.poppins(
            color: Colors.grey,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }

  Widget _buildVehicleCard(Map<String, dynamic> vehicle) {
    return Container(
      width: 160,
      margin: const EdgeInsets.only(right: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.shade300,
            blurRadius: 10,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: Image.asset(
              vehicle["image"]!,
              height: 110,
              width: double.infinity,
              fit: BoxFit.cover,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  vehicle["name"]!,
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  vehicle["category"]!,
                  style: GoogleFonts.poppins(
                    fontSize: 11,
                    color: Colors.grey.shade600,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.location_on, size: 12, color: Colors.grey),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        vehicle["location"]!,
                        style: GoogleFonts.poppins(
                          fontSize: 10,
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
                    Text(
                      vehicle["price"]!,
                      style: GoogleFonts.poppins(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Row(
                      children: [
                        const Icon(Icons.star, size: 12, color: Colors.amber),
                        const SizedBox(width: 2),
                        Text(
                          vehicle["rating"].toString(),
                          style: GoogleFonts.poppins(
                            fontSize: 11,
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

  Widget _buildLargeVehicleCard(Map<String, dynamic> vehicle) {
    return Container(
      width: MediaQuery.of(context).size.width * 0.85,
      margin: const EdgeInsets.only(right: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.shade300,
            blurRadius: 12,
            offset: const Offset(0, 6),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
            child: Image.asset(
              vehicle["image"]!,
              height: 170,
              width: double.infinity,
              fit: BoxFit.cover,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        vehicle["name"]!,
                        style: GoogleFonts.poppins(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.black,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.star, size: 12, color: Colors.amber),
                          const SizedBox(width: 4),
                          Text(
                            vehicle["rating"].toString(),
                            style: GoogleFonts.poppins(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  vehicle["category"]!,
                  style: GoogleFonts.poppins(
                    fontSize: 12,
                    color: Colors.grey.shade600,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.location_on, size: 14, color: Colors.grey),
                    const SizedBox(width: 4),
                    Text(
                      vehicle["location"]!,
                      style: GoogleFonts.poppins(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  vehicle["price"]!,
                  style: GoogleFonts.poppins(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}