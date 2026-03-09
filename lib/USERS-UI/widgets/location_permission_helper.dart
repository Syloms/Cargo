// Stub file for LocationPermissionHelper
import 'package:flutter/material.dart';

class LocationPermissionHelper {
  static Future<bool> requestPermission(BuildContext context) async {
    return false;
  }

  static Future<bool> checkPermission() async {
    return false;
  }

  static Future<bool> showPermissionDialog(BuildContext context) async {
    return false;
  }

  static Widget buildGpsStatusBadge({
    required bool isTracking,
    required int successCount,
    required int failCount,
    DateTime? lastUpdate,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: isTracking ? Colors.green : Colors.grey,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        isTracking ? 'GPS Active' : 'GPS Off',
        style: const TextStyle(color: Colors.white, fontSize: 11),
      ),
    );
  }
}
