// Stub file for OverdueService
import 'package:cargo/USERS-UI/models/overdue_booking.dart';

class OverdueService {
  Future<List<OverdueBooking>> fetchOverdueBookings(int userId) async {
    return [];
  }

  Future<OverdueBooking?> checkBookingOverdue(int bookingId) async {
    return null;
  }

  bool isBookingOverdueLocal(String? returnDate, String? returnTime) {
    if (returnDate == null || returnDate.isEmpty) return false;
    try {
      final timeStr = returnTime?.isEmpty ?? true ? '23:59:00' : returnTime!;
      final dt = DateTime.parse('${returnDate}T$timeStr');
      return DateTime.now().isAfter(dt);
    } catch (_) {
      return false;
    }
  }

  double calculateHoursOverdue(String? returnDate, String? returnTime) {
    if (returnDate == null || returnDate.isEmpty) return 0;
    try {
      final timeStr = returnTime?.isEmpty ?? true ? '23:59:00' : returnTime!;
      final dt = DateTime.parse('${returnDate}T$timeStr');
      final diff = DateTime.now().difference(dt);
      return diff.inMinutes > 0 ? diff.inMinutes / 60.0 : 0;
    } catch (_) {
      return 0;
    }
  }

  double calculateLateFee(double hoursOverdue) {
    const double feePerHour = 100.0;
    return hoursOverdue * feePerHour;
  }
}
