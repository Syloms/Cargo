// Stub file for RenterGpsService
class RenterGpsService {
  bool isTracking = false;
  String? activeBookingId;
  int successCount = 0;
  int failCount = 0;
  DateTime? lastUpdate;

  Future<bool> startTracking(String bookingId) async {
    isTracking = true;
    activeBookingId = bookingId;
    return true;
  }

  void stopTracking() {
    isTracking = false;
    activeBookingId = null;
  }

  Future<Map<String, dynamic>?> getCurrentLocation() async {
    return null;
  }
}

// Global singleton instance
final RenterGpsService renterGpsService = RenterGpsService();
