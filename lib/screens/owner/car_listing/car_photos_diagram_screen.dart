import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/models/car_listing.dart';

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
  'Right Side',          // 3
  'Rear Right 3/4ths',   // 4
  'Rear',                // 5
  'Rear Left 3/4ths',    // 6
  'Left Side',           // 7
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
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          final carWidth = MediaQuery.of(context).size.width * 0.7;
                          final carHeight = carWidth * 1.5; // Aspect ratio adjustment
                          
                          return SizedBox(
                            width: carWidth,
                            height: carHeight,
                            child: Stack(
                              children: [
                                // Car image placeholder
                                Container(
                                  width: carWidth,
                                  height: carHeight,
                                  decoration: BoxDecoration(
                                    color: Colors.grey[200],
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(20),
                                    child: Image.asset(
                                      'assets/cartop.png',
                                      fit: BoxFit.cover,
                                      errorBuilder: (context, error, stackTrace) {
                                        return Column(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Icon(Icons.directions_car, size: 100, color: Colors.grey[400]),
                                            const SizedBox(height: 8),
                                            Text(
                                              'Car Top View',
                                              style: GoogleFonts.poppins(color: Colors.grey[600]),
                                            ),
                                          ],
                                        );
                                      },
                                    ),
                                  ),
                                ),
                                
                                // Photo spot buttons
                                ..._buildPhotoSpots(carWidth, carHeight),
                              ],
                            ),
                          );
                        },
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

  List<Widget> _buildPhotoSpots(double carWidth, double carHeight) {
    // Adjusted positions based on the screenshot
   final positions = [
  const Offset(0.50, 0.09),  // 1 - Front

  const Offset(0.82, 0.17),  // 2 - Front Right 3/4
  const Offset(0.90, 0.40),  // 3 - Right Side
  const Offset(0.82, 0.63),  // 4 - Rear Right 3/4

  const Offset(0.50, 0.81),  // 5 - Rear

  const Offset(0.18, 0.63),  // 6 - Rear Left 3/4
  const Offset(0.10, 0.40),  // 7 - Left Side
  const Offset(0.18, 0.17),  // 8 - Front Left 3/4

  const Offset(0.50, 0.30),  // 9 - Front Seats
  const Offset(0.50, 0.46),  // 10 - Back Seats
  const Offset(0.50, 0.62),  // 11 - Trunk
];




    return List.generate(11, (index) {
      final isUploaded = uploadedPhotos.containsKey(index + 1);
      
      return Positioned(
        left: positions[index].dx * carWidth - 20,
        top: positions[index].dy * carHeight - 20,
        child: GestureDetector(
          onTap: () => _navigateToPhotoCapture(index + 1),
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: isUploaded ? Colors.red : Colors.grey,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.2),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
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
    // Simulate photo capture
    await Future.delayed(const Duration(milliseconds: 500));
    
    // Mock result - in real app this would come from camera
    setState(() {
      uploadedPhotos[spotNumber] = 'photo_$spotNumber.jpg';
    });
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Photo ${photoLabels[spotNumber - 1]} uploaded!',
          style: GoogleFonts.poppins(),
        ),
        duration: const Duration(seconds: 1),
      ),
    );
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