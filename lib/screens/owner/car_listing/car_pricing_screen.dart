import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../models/car_listing.dart';
import 'car_location_screen.dart';

class CarPricingScreen extends StatefulWidget {
  final CarListing listing;

  const CarPricingScreen({super.key, required this.listing});

  @override
  State<CarPricingScreen> createState() => _CarPricingScreenState();
}

class _CarPricingScreenState extends State<CarPricingScreen> {
  final _priceController = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (widget.listing.dailyRate != null) {
      _priceController.text = widget.listing.dailyRate!.toStringAsFixed(2);
    }
  }

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
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'How much would you want your car to be rented per day?',
                      style: GoogleFonts.poppins(
                        fontSize: 22,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const Spacer(),
                    
                    Center(
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Text(
                            '₱',
                            style: GoogleFonts.poppins(
                              fontSize: 48,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(width: 8),
                          IntrinsicWidth(
                            child: TextField(
                              controller: _priceController,
                              keyboardType: TextInputType.number,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly,
                              ],
                              style: GoogleFonts.poppins(
                                fontSize: 48,
                                fontWeight: FontWeight.w600,
                              ),
                              textAlign: TextAlign.center,
                              decoration: InputDecoration(
                                hintText: '00.00',
                                hintStyle: GoogleFonts.poppins(
                                  fontSize: 48,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.grey[300],
                                ),
                                border: InputBorder.none,
                                isDense: true,
                                contentPadding: EdgeInsets.zero,
                              ),
                              onChanged: (value) {
                                if (value.isNotEmpty) {
                                  widget.listing.dailyRate = double.tryParse(value);
                                }
                              },
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Spacer(),
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: widget.listing.dailyRate != null && widget.listing.dailyRate! > 0
                      ? () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => CarLocationScreen(listing: widget.listing),
                            ),
                          );
                        }
                      : null,
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
                      color: widget.listing.dailyRate != null && widget.listing.dailyRate! > 0
                          ? const Color(0xFFCDFE3D)
                          : Colors.grey[500],
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