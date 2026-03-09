// Stub file for NetworkResilienceService
import 'package:http/http.dart' as http;

class NetworkResilienceService {
  static final NetworkResilienceService _instance = NetworkResilienceService._internal();
  factory NetworkResilienceService() => _instance;
  NetworkResilienceService._internal();

  Future<bool> isConnected() async => true;

  static Future<bool> isNetworkAvailable() async => true;

  Future<http.Response?> resilientGet(
    Uri url, {
    Map<String, String>? headers,
    Duration? timeout,
    int? maxRetries,
  }) async {
    try {
      final future = http.get(url, headers: headers);
      if (timeout != null) {
        return await future.timeout(timeout);
      }
      return await future;
    } catch (_) {
      return null;
    }
  }

  void resetAllCircuitBreakers() {}
}
