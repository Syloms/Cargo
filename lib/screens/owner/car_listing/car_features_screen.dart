import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../models/car_listing.dart';
import 'car_rules_screen.dart';

class CarFeaturesScreen extends StatefulWidget {
  final CarListing listing;

  const CarFeaturesScreen({super.key, required this.listing});

  @override
  State<CarFeaturesScreen> createState() => _CarFeaturesScreenState();
}

class _CarFeaturesScreenState extends State<CarFeaturesScreen> {
  final _descriptionController = TextEditingController();

  final List<Map<String, dynamic>> availableFeatures = [
    {'name': 'AUX input', 'icon': Icons.audiotrack},
    {'name': 'All-wheel drive', 'icon': Icons.all_inclusive},
    {'name': 'Android auto', 'icon': Icons.android},
    {'name': 'Apple Carplay', 'icon': Icons.apple},
    {'name': 'Autosweep', 'icon': Icons.toll},
    {'name': 'Backup camera', 'icon': Icons.videocam},
    {'name': 'Bike rack', 'icon': Icons.directions_bike},
    {'name': 'Blind spot warning', 'icon': Icons.warning},
    {'name': 'Bluetooth', 'icon': Icons.bluetooth},
    {'name': 'Child seat', 'icon': Icons.child_care},
    {'name': 'Convertible', 'icon': Icons.directions_car},
    {'name': 'Easytrip', 'icon': Icons.credit_card},
    {'name': 'GPS', 'icon': Icons.gps_fixed},
    {'name': 'Keyless entry', 'icon': Icons.vpn_key},
    {'name': 'Pet-friendly', 'icon': Icons.pets},
    {'name': 'Sunroof', 'icon': Icons.wb_sunny},
    {'name': 'USB Charger', 'icon': Icons.usb},
    {'name': 'USB input', 'icon': Icons.cable},
    {'name': 'Wheelchair accessible', 'icon': Icons.accessible},
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Let guests know what your car has to offer',
                      style: GoogleFonts.poppins(
                        fontSize: 22,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    Text(
                      'What makes your car special?',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    TextField(
                      controller: _descriptionController,
                      maxLines: 4,
                      style: GoogleFonts.poppins(fontSize: 14),
                      decoration: InputDecoration(
                        hintText: 'Describe your amazing car...',
                        hintStyle: GoogleFonts.poppins(color: Colors.grey[400]),
                        filled: true,
                        fillColor: Colors.grey[100],
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.all(16),
                      ),
                    ),
                    
                    const SizedBox(height: 24),
                    
                    Text(
                      'Your car\'s features',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3,
                        childAspectRatio: 0.9,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                      ),
                      itemCount: availableFeatures.length,
                      itemBuilder: (context, index) {
                        final feature = availableFeatures[index];
                        final isSelected = widget.listing.features.contains(feature['name']);
                        
                        return GestureDetector(
                          onTap: () {
                            setState(() {
                              if (isSelected) {
                                widget.listing.features.remove(feature['name']);
                              } else {
                                widget.listing.features.add(feature['name']);
                              }
                            });
                          },
                          child: Container(
                            decoration: BoxDecoration(
                              color: isSelected ? Colors.black : Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: isSelected ? Colors.black : Colors.grey[300]!,
                                width: 1.5,
                              ),
                            ),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  feature['icon'],
                                  size: 32,
                                  color: isSelected ? Colors.white : Colors.grey[700],
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  feature['name'],
                                  textAlign: TextAlign.center,
                                  style: GoogleFonts.poppins(
                                    fontSize: 11,
                                    color: isSelected ? Colors.white : Colors.grey[800],
                                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => CarRulesScreen(listing: widget.listing),
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  child: Text(
                    'Continue',
                    style: GoogleFonts.poppins(
                      color: const Color(0xFFCDFE3D),
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}