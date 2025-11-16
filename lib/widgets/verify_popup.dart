import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:google_fonts/google_fonts.dart';

class VerifyPopup {
  static Future<void> showIfNotVerified(BuildContext context) async {
    final box = GetStorage();
    final bool isVerified = box.read('isVerified') ?? false;

    if (!isVerified) {
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) {
          return Dialog(
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            child: Container(
              constraints: const BoxConstraints(maxWidth: 400),
              child: SingleChildScrollView(
                child: Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Close button
                      Align(
                        alignment: Alignment.topLeft,
                        child: IconButton(
                          icon: const Icon(Icons.close, color: Colors.grey),
                          onPressed: () => Navigator.pop(context),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                        ),
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Mascot and "Just takes 2 mins" badge
                      Stack(
                        clipBehavior: Clip.none,
                        children: [
                          // Mascot character
                          Align(
                            alignment: Alignment.centerRight,
                            child: Image.asset(
                              'assets/logo.png', // Add your mascot image
                              height: 150,
                              width: 150,
                              errorBuilder: (context, error, stackTrace) {
                                return Container(
                                  height: 150,
                                  width: 150,
                                  decoration: BoxDecoration(
                                    color: Colors.green[300],
                                    borderRadius: BorderRadius.circular(75),
                                  ),
                                  child: const Icon(
                                    Icons.emoji_emotions,
                                    size: 80,
                                    color: Colors.white,
                                  ),
                                );
                              },
                            ),
                          ),
                          
                          // "Just takes 2 mins" badge
                          Positioned(
                            top: 0,
                            right: 20,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.black,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                'Just takes 2 mins!',
                                style: GoogleFonts.poppins(
                                  color: Colors.greenAccent,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 24),
                      
                      // Title
                      Text(
                        'Hi there!\nLet\'s get you\nverified first.',
                        style: GoogleFonts.poppins(
                          fontSize: 28,
                          fontWeight: FontWeight.bold,
                          color: Colors.black,
                          height: 1.2,
                        ),
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Subtitle
                      Text(
                        'Get verified for you to book freely anytime, anywhere with Cargo. Here\'s what you need:',
                        style: GoogleFonts.poppins(
                          fontSize: 14,
                          color: Colors.grey[700],
                          height: 1.4,
                        ),
                      ),
                      
                      const SizedBox(height: 24),
                      
                      // Step 1
                      _buildStep(
                        '1. Prepare your Driver\'s License or any Government ID.',
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // License image with decorative elements
                      Center(
                        child: Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.1),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: Image.asset(
                                  'assets/license_sample.jpg', // Add sample license image
                                  height: 120,
                                  fit: BoxFit.cover,
                                  errorBuilder: (context, error, stackTrace) {
                                    return Container(
                                      height: 120,
                                      width: 200,
                                      decoration: BoxDecoration(
                                        color: Colors.grey[200],
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: const Icon(
                                        Icons.credit_card,
                                        size: 50,
                                        color: Colors.grey,
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ),
                            
                            // Green accent bars
                            Positioned(
                              top: -5,
                              right: -5,
                              child: _buildAccentBar(),
                            ),
                            Positioned(
                              bottom: -5,
                              left: -5,
                              child: _buildAccentBar(),
                            ),
                            Positioned(
                              top: 40,
                              right: -10,
                              child: _buildAccentBar(),
                            ),
                          ],
                        ),
                      ),
                      
                      const SizedBox(height: 24),
                      
                      // Step 2
                      _buildStep(
                        '2. Take a selfie with your driver\'s license or any Government ID.',
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Step 3
                      _buildStep(
                        '3. Fill up our Personal Information Sheet.',
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Step 4
                      _buildStep(
                        '4. Wait for Cargo Team Verification.',
                      ),
                      
                      const SizedBox(height: 32),
                      
                      // Get Verified button
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () {
                            box.write('isVerified', true);
                            Navigator.pop(context);
                            // TODO: Navigate to verification screen
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.black,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            elevation: 0,
                          ),
                          child: Text(
                            'Get Verified',
                            style: GoogleFonts.poppins(
                              color: Colors.greenAccent,
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      );
    }
  }
  
  static Widget _buildStep(String text) {
    return Text(
      text,
      style: GoogleFonts.poppins(
        fontSize: 14,
        color: Colors.black87,
        fontWeight: FontWeight.w500,
        height: 1.4,
      ),
    );
  }
  
  static Widget _buildAccentBar() {
    return Container(
      width: 4,
      height: 20,
      decoration: BoxDecoration(
        color: Colors.greenAccent,
        borderRadius: BorderRadius.circular(2),
      ),
    );
  }
}