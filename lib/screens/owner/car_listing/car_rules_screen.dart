import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../models/car_listing.dart';
import 'car_pricing_screen.dart';

class CarRulesScreen extends StatefulWidget {
  final CarListing listing;

  const CarRulesScreen({super.key, required this.listing});

  @override
  State<CarRulesScreen> createState() => _CarRulesScreenState();
}

class _CarRulesScreenState extends State<CarRulesScreen> {
  final List<String> availableRules = [
    'Clean As You Go (CLAYGO)',
    'No Littering',
    'No eating or drinking inside',
    'No inter-island travel',
    'No off-roading or driving through flooded areas',
    'No pets allowed',
    'No vaping/smoking',
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
                      'Your Cars, Your Rules',
                      style: GoogleFonts.poppins(
                        fontSize: 24,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Create your own rules',
                      style: GoogleFonts.poppins(
                        fontSize: 14,
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 32),
                    
                    Text(
                      'What are your car rules?',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    ...availableRules.map((rule) => CheckboxListTile(
                      value: widget.listing.rules.contains(rule),
                      onChanged: (value) {
                        setState(() {
                          if (value == true) {
                            widget.listing.rules.add(rule);
                          } else {
                            widget.listing.rules.remove(rule);
                          }
                        });
                      },
                      title: Text(
                        rule,
                        style: GoogleFonts.poppins(fontSize: 14),
                      ),
                      controlAffinity: ListTileControlAffinity.leading,
                      activeColor: Colors.black,
                      contentPadding: EdgeInsets.zero,
                    )),
                    
                    const SizedBox(height: 12),
                    
                    OutlinedButton.icon(
                      onPressed: () {
                        // Show dialog to add custom rule
                        _showAddRuleDialog();
                      },
                      icon: const Icon(Icons.add, color: Colors.black),
                      label: Text(
                        'Add car rule',
                        style: GoogleFonts.poppins(color: Colors.black),
                      ),
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(color: Colors.grey[300]!),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                    ),
                    
                    const SizedBox(height: 32),
                    
                    Text(
                      'How far can we drive your car?',
                      style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          child: _buildMileageOption(
                            'Set mileage limit',
                            !widget.listing.hasUnlimitedMileage,
                            () => setState(() => widget.listing.hasUnlimitedMileage = false),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _buildMileageOption(
                            'Unlimited mileage',
                            widget.listing.hasUnlimitedMileage,
                            () => setState(() => widget.listing.hasUnlimitedMileage = true),
                          ),
                        ),
                      ],
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
                        builder: (context) => CarPricingScreen(listing: widget.listing),
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

  Widget _buildMileageOption(String label, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? Colors.black : Colors.white,
          border: Border.all(
            color: isSelected ? Colors.black : Colors.grey[300]!,
            width: 1.5,
          ),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isSelected ? Icons.check_circle : Icons.circle_outlined,
              color: isSelected ? const Color(0xFFCDFE3D) : Colors.grey,
              size: 20,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: GoogleFonts.poppins(
                fontSize: 13,
                color: isSelected ? Colors.white : Colors.black,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showAddRuleDialog() {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Add Custom Rule', style: GoogleFonts.poppins()),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(hintText: 'Enter rule'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              if (controller.text.isNotEmpty) {
                setState(() {
                  availableRules.add(controller.text);
                  widget.listing.rules.add(controller.text);
                });
              }
              Navigator.pop(context);
            },
            child: const Text('Add'),
          ),
        ],
      ),
    );
  }
}