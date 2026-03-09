// Stub file for StatusHelper
import 'package:flutter/material.dart';

class StatusHelper {
  static Color getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'approved':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'rejected':
        return Colors.red;
      case 'rented':
        return Colors.blue;
      default:
        return Colors.grey;
    }
  }

  static IconData getStatusIcon(String status) {
    switch (status.toLowerCase()) {
      case 'approved':
        return Icons.check_circle_outline;
      case 'pending':
        return Icons.hourglass_empty;
      case 'rejected':
        return Icons.cancel_outlined;
      case 'rented':
        return Icons.directions_car;
      default:
        return Icons.help_outline;
    }
  }
}
