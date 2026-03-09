// Stub file for Owner BookingService
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:cargo/config/api_config.dart';

class BookingService {
  Future<List<Map<String, dynamic>>> fetchActiveBookings(String ownerId) async {
    try {
      final response = await http.get(
        Uri.parse('${GlobalApiConfig.activeBookingsEndpoint}?owner_id=$ownerId'),
      ).timeout(GlobalApiConfig.apiTimeout);
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data is List) return data.cast<Map<String, dynamic>>();
      }
    } catch (_) {}
    return [];
  }

  Future<Map<String, dynamic>> startTrip(String bookingId, String ownerId) async {
    try {
      final response = await http.post(
        Uri.parse(GlobalApiConfig.startTripEndpoint),
        body: {'booking_id': bookingId, 'owner_id': ownerId},
      ).timeout(GlobalApiConfig.apiTimeout);
      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
    } catch (_) {}
    return {'success': false, 'message': 'Request failed'};
  }

  Future<Map<String, dynamic>> endTrip(String bookingId, String ownerId) async {
    try {
      final response = await http.post(
        Uri.parse(GlobalApiConfig.endTripEndpoint),
        body: {'booking_id': bookingId, 'owner_id': ownerId},
      ).timeout(GlobalApiConfig.apiTimeout);
      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
    } catch (_) {}
    return {'success': false, 'message': 'Request failed'};
  }
}
