import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../models/car_listing.dart';
import 'car_features_screen.dart';

class CarPreferencesScreen extends StatefulWidget {
  final CarListing listing;

  const CarPreferencesScreen({super.key, required this.listing});

  @override
  State<CarPreferencesScreen> createState() => _CarPreferencesScreenState();
}

class _CarPreferencesScreenState extends State<CarPreferencesScreen> {
  final List<String> advanceNoticeOptions = ['30 minutes', '1 hour', '3 hours', 'Others'];
  final List<String> minDurationOptions = ['1 day', '2 days', '3 days', 'Others'];
  final List<String> maxDurationOptions = ['5 days', '1 week', '2 weeks', '1 month', '3 months', 'Others'];
  
  final List<Map<String, String>> deliveryOptions = [
    {
      'title': 'Guest Pickup & Guest Return',
      'subtitle': 'Simply select this option for hassle-free pickup and return. The host\'s address will be provided for this choice.',
    },
    {
      'title': 'Guest Pickup & Host Collection',
      'subtitle': 'The pickup will automatically default to the host\'s address. For the guest\'s convenience, input the location from which the host will collect.',
    },
    {
      'title': 'Host Delivery & Guest Return',
      'subtitle': 'Tailor your experience by choosing the pickup location while the return is automatically set to the host\'s address.',
    },
    {
      'title': 'Host Delivery & Host Collection',
      'subtitle': 'Effortlessly customize both pickup and return locations to suit your needs.',
    },
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
                      'Tell us more about your car',
                      style: GoogleFonts.poppins(
                        fontSize: 24,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const SizedBox(height: 32),
                    
                    Text(
                      'How much advance notice do you need before a trip starts?',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    Text(
                      '(1 hour recommended)',
                      style: GoogleFonts.poppins(fontSize: 12, color: Colors.grey[600]),
                    ),
                    const SizedBox(height: 12),
                    _buildDropdown(advanceNoticeOptions, widget.listing.advanceNotice, (value) {
                      setState(() => widget.listing.advanceNotice = value);
                    }),
                    
                    const SizedBox(height: 24),
                    
                    Text(
                      'What\'s the shortest and longest trip possible you\'ll accept?',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    Text('Minimum trip duration', style: GoogleFonts.poppins(fontSize: 13)),
                    const SizedBox(height: 8),
                    _buildDropdown(minDurationOptions, widget.listing.minTripDuration, (value) {
                      setState(() => widget.listing.minTripDuration = value);
                    }),
                    
                    const SizedBox(height: 16),
                    
                    Text('Maximum trip duration', style: GoogleFonts.poppins(fontSize: 13)),
                    const SizedBox(height: 8),
                    _buildDropdown(maxDurationOptions, widget.listing.maxTripDuration, (value) {
                      setState(() => widget.listing.maxTripDuration = value);
                    }),
                    
                    const SizedBox(height: 24),
                    
                    Text(
                      'Prefer delivery type (can select more than 1)',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    ...deliveryOptions.map((option) => _buildCheckboxTile(
                      option['title']!,
                      option['subtitle']!,
                      widget.listing.deliveryTypes.contains(option['title']),
                      (value) {
                        setState(() {
                          if (value == true) {
                            widget.listing.deliveryTypes.add(option['title']!);
                          } else {
                            widget.listing.deliveryTypes.remove(option['title']);
                          }
                        });
                      },
                    )),
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _canContinue() ? () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => CarFeaturesScreen(listing: widget.listing),
                      ),
                    );
                  } : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    disabledBackgroundColor: Colors.grey[300],
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  child: Text(
                    'Continue',
                    style: GoogleFonts.poppins(
                      color: _canContinue() ? const Color(0xFFCDFE3D) : Colors.grey[500],
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

  bool _canContinue() {
    return widget.listing.advanceNotice != null &&
           widget.listing.minTripDuration != null &&
           widget.listing.maxTripDuration != null &&
           widget.listing.deliveryTypes.isNotEmpty;
  }

  Widget _buildDropdown(List<String> items, String? value, Function(String?) onChanged) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.grey[100],
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          hint: Text('Select duration', style: GoogleFonts.poppins(color: Colors.grey)),
          icon: const Icon(Icons.keyboard_arrow_down, color: Colors.green),
          items: items.map((item) {
            return DropdownMenuItem(
              value: item,
              child: Text(item, style: GoogleFonts.poppins(fontSize: 14)),
            );
          }).toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }

  Widget _buildCheckboxTile(String title, String subtitle, bool value, Function(bool?) onChanged) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Checkbox(
            value: value,
            onChanged: onChanged,
            activeColor: Colors.green,
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: GoogleFonts.poppins(fontSize: 11, color: Colors.grey[600]),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}