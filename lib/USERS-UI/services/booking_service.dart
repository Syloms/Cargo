// Stub file for BookingService (renter services)
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:cargo/config/api_config.dart';

class BookingService {
  Future<Map<String, dynamic>> fetchBookingDetails(int bookingId) async {
    try {
      final response = await http.get(
        Uri.parse('${GlobalApiConfig.baseUrl}/api/get_booking.php?booking_id=$bookingId'),
      );
      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
    } catch (_) {}
    return {'success': false};
  }

  static Future<Map<String, dynamic>> cancelBooking({
    required int bookingId,
    required String userId,
    String? reason,
  }) async {
    try {
      final response = await http.post(
        Uri.parse(GlobalApiConfig.cancelBookingEndpoint),
        body: {
          'booking_id': bookingId.toString(),
          'user_id': userId,
          if (reason != null) 'reason': reason,
        },
      ).timeout(GlobalApiConfig.apiTimeout);
      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
    } catch (_) {}
    return {'success': false, 'message': 'Request failed'};
  }
}
