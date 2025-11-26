import 'dart:convert';
import 'package:http/http.dart' as http;

class PayMongoService {
  // IMPORTANT: Replace these with your actual PayMongo API keys
  // Get them from: https://dashboard.paymongo.com/developers
  static const String _secretKey = 'sk_test_YOUR_SECRET_KEY_HERE';
  static const String _publicKey = 'pk_test_YOUR_PUBLIC_KEY_HERE';
  static const String _baseUrl = 'https://api.paymongo.com/v1';

  /// Create a PayMongo Source for GCash
  /// Amount should be in centavos (e.g., 10000 = PHP 100.00)
  Future<Map<String, dynamic>> createGCashSource({
    required int amount,
    required String description,
    String? redirectUrl,
  }) async {
    final url = Uri.parse('$_baseUrl/sources');
    
    // Basic auth with secret key
    final auth = base64Encode(utf8.encode('$_secretKey:'));
    
    final body = jsonEncode({
      'data': {
        'attributes': {
          'amount': amount,
          'redirect': {
            'success': redirectUrl ?? 'https://your-app.com/payment/success',
            'failed': redirectUrl ?? 'https://your-app.com/payment/failed',
          },
          'type': 'gcash',
          'currency': 'PHP',
          'description': description,
        }
      }
    });

    try {
      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic $auth',
        },
        body: body,
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Failed to create GCash source: ${response.body}');
      }
    } catch (e) {
      throw Exception('Error creating GCash source: $e');
    }
  }

  /// Create a Payment using the Source
  Future<Map<String, dynamic>> createPayment({
    required String sourceId,
    required int amount,
    required String description,
  }) async {
    final url = Uri.parse('$_baseUrl/payments');
    final auth = base64Encode(utf8.encode('$_secretKey:'));

    final body = jsonEncode({
      'data': {
        'attributes': {
          'amount': amount,
          'source': {
            'id': sourceId,
            'type': 'source',
          },
          'currency': 'PHP',
          'description': description,
        }
      }
    });

    try {
      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic $auth',
        },
        body: body,
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Failed to create payment: ${response.body}');
      }
    } catch (e) {
      throw Exception('Error creating payment: $e');
    }
  }

  /// Retrieve Payment Status
  Future<Map<String, dynamic>> getPaymentStatus(String paymentId) async {
    final url = Uri.parse('$_baseUrl/payments/$paymentId');
    final auth = base64Encode(utf8.encode('$_secretKey:'));

    try {
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic $auth',
        },
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Failed to get payment status: ${response.body}');
      }
    } catch (e) {
      throw Exception('Error getting payment status: $e');
    }
  }

  /// Retrieve Source Status
  Future<Map<String, dynamic>> getSourceStatus(String sourceId) async {
    final url = Uri.parse('$_baseUrl/sources/$sourceId');
    final auth = base64Encode(utf8.encode('$_secretKey:'));

    try {
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic $auth',
        },
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Failed to get source status: ${response.body}');
      }
    } catch (e) {
      throw Exception('Error getting source status: $e');
    }
  }

  /// Helper: Convert PHP amount to centavos
  static int toCentavos(double amount) {
    return (amount * 100).round();
  }

  /// Helper: Convert centavos to PHP
  static double toPhp(int centavos) {
    return centavos / 100;
  }
}