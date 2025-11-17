import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../screens/verification/personal_info_screen.dart';

class VerifyPopup {
  static Future<void> showIfNotVerified(BuildContext context) async {
    final box = GetStorage();
    final bool isVerified = box.read('isVerified') ?? false;

    if (!isVerified) {
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => Dialog(
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          child: Container(
            constraints: const BoxConstraints(maxWidth: 400),
            child: SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Align(
                      alignment: Alignment.topLeft,
                      child: IconButton(
                        icon: const Icon(Icons.close, color: Colors.grey),
                        onPressed: () => Navigator.pop(dialogContext),
                        padding: EdgeInsets.zero,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Stack(
                      clipBehavior: Clip.none,
                      children: [
                        Align(
                          alignment: Alignment.centerRight,
                          child: Container(
                            height: 150,
                            width: 150,
                            decoration: BoxDecoration(
                              color: Colors.green[300],
                              borderRadius: BorderRadius.circular(75),
                            ),
                            child: const Icon(Icons.emoji_emotions, size: 80, color: Colors.white),
                          ),
                        ),
                        Positioned(
                          top: 0,
                          right: 20,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
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
                    Text(
                      'Hi there!\nLet\'s get you\nverified first.',
                      style: GoogleFonts.poppins(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Get verified for you to book freely anytime, anywhere with Cargo.',
                      style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey[700], height: 1.4),
                    ),
                    const SizedBox(height: 24),
                    _buildStep('1. Prepare your Driver\'s License or any Government ID.'),
                    const SizedBox(height: 16),
                    _buildStep('2. Take a selfie with your driver\'s license.'),
                    const SizedBox(height: 16),
                    _buildStep('3. Fill up our Personal Information Sheet.'),
                    const SizedBox(height: 16),
                    _buildStep('4. Wait for Cargo Team Verification.'),
                    const SizedBox(height: 32),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () {
                          // Close the dialog using dialog context
                          Navigator.of(dialogContext).pop();
                          
                          // Navigate using parent context
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const PersonalInfoScreen(),
                            ),
                          );
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.black,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
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
        ),
      );
    }
  }

  static Widget _buildStep(String text) {
    return Text(
      text,
      style: GoogleFonts.poppins(fontSize: 14, color: Colors.black87, fontWeight: FontWeight.w500),
    );
  }
}