// Stub file for VerificationDialog
import 'package:flutter/material.dart';

class VerificationDialog {
  static void show(BuildContext context, {required VoidCallback onVerifyPressed}) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Verification Required'),
        content: const Text('You need to verify your identity before adding a vehicle.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              onVerifyPressed();
            },
            child: const Text('Verify Now'),
          ),
        ],
      ),
    );
  }
}
