import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../models/car_listing.dart';
import 'car_photo_capture_screen.dart';

class CarPhotosDiagramScreen extends StatefulWidget {
  final CarListing listing;

  const CarPhotosDiagramScreen({super.key, required this.listing});

  @override
  State<CarPhotosDiagramScreen> createState() => _CarPhotosDiagramScreenState();
}

class _CarPhotosDiagramScreenState extends State<CarPhotosDiagramScreen> {
  final Map<int, String?> uploadedPhotos = {};
  
  final List<String> photoLabels = [
    'Front',               // 1
    'Front Right 3/4ths',  // 2
    'Rear Right',          // 3
    'Rear Right 3/4ths',   // 4
    'Rear',                // 5
    'Rear Left 3/4ths',    // 6
    'Rear Left',           // 7
    'Front Left 3/4ths',   // 8
    'Front Seats',         // 9
    'Back Seats',          // 10
    'Trunk',               // 11
  ];

  @override
  Widget build(BuildContext context) {
    final allPhotosUploaded = uploadedPhotos.length == 11;
    
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Car Photos',
          style: GoogleFonts.poppins(
            color: Colors.black,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
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
                      'Make sure to upload one clear photo on each car part for better listing.',
                      style: GoogleFonts.poppins(
                        fontSize: 14,
                        color: Colors.grey[600],
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 32),
                    
                    // Car diagram with numbered spots
                    Center(
                      child: Stack(
                        children: [
                          // Car image
                          Image.asset(
                            'assets/car_top_view.png', // Add this asset
                            width: MediaQuery.of(context).size.width * 0.7,
                            errorBuilder: (context, error, stackTrace) {
                              return Container(
                                width: MediaQuery.of(context).size.width * 0.7,
                                height: 400,
                                color: Colors.grey[200],
                                child: const Icon(Icons.directions_car, size: 100),
                              );
                            },
                          ),
                          
                          // Photo spot buttons
                          ..._buildPhotoSpots(),
                        ],
                      ),
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
                  onPressed: allPhotosUploaded ? _showSubmitDialog : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    disabledBackgroundColor: Colors.grey[300],
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  child: Text(
                    allPhotosUploaded ? 'Continue' : 'Next',
                    style: GoogleFonts.poppins(
                      color: allPhotosUploaded ? const Color(0xFFCDFE3D) : Colors.grey[500],
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

  List<Widget> _buildPhotoSpots() {
    // Positions for each photo spot (adjust based on your car image)
    final positions = [
      const Offset(0.5, 0.15),   // 1 - Front
      const Offset(0.75, 0.18),  // 2 - Front Right 3/4
      const Offset(0.85, 0.45),  // 3 - Rear Right
      const Offset(0.8, 0.72),   // 4 - Rear Right 3/4
      const Offset(0.5, 0.82),   // 5 - Rear
      const Offset(0.2, 0.72),   // 6 - Rear Left 3/4
      const Offset(0.15, 0.45),  // 7 - Rear Left
      const Offset(0.25, 0.18),  // 8 - Front Left 3/4
      const Offset(0.5, 0.4),    // 9 - Front Seats
      const Offset(0.5, 0.55),   // 10 - Back Seats
      const Offset(0.5, 0.75),   // 11 - Trunk
    ];

    return List.generate(11, (index) {
      final isUploaded = uploadedPhotos.containsKey(index + 1);
      
      return Positioned(
        left: positions[index].dx * MediaQuery.of(context).size.width * 0.7 - 20,
        top: positions[index].dy * 400 - 20,
        child: GestureDetector(
          onTap: () => _navigateToPhotoCapture(index + 1),
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: isUploaded ? Colors.red : Colors.grey,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2),
            ),
            child: Center(
              child: Text(
                '${index + 1}',
                style: GoogleFonts.poppins(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        ),
      );
    });
  }

  void _navigateToPhotoCapture(int spotNumber) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => CarPhotoCaptureScreen(
          photoLabel: photoLabels[spotNumber - 1],
          spotNumber: spotNumber,
        ),
      ),
    );
    
    if (result != null) {
      setState(() {
        uploadedPhotos[spotNumber] = result;
      });
    }
  }

  void _showSubmitDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(height: 8),
              Text(
                'Submit Car Listing',
                style: GoogleFonts.poppins(
                  fontSize: 20,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Make sure all information is correct. Once submitted, DOON team will review your listing for verification.',
                textAlign: TextAlign.center,
                style: GoogleFonts.poppins(
                  fontSize: 14,
                  color: Colors.grey[600],
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).popUntil((route) => route.isFirst);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          'Car listing submitted successfully!',
                          style: GoogleFonts.poppins(),
                        ),
                        backgroundColor: Colors.green,
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(
                    'Submit',
                    style: GoogleFonts.poppins(
                      color: const Color(0xFFCDFE3D),
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(
                  'Go back',
                  style: GoogleFonts.poppins(
                    color: Colors.grey[700],
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}