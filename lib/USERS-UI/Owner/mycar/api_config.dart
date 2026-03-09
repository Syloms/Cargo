// Stub: re-export GlobalApiConfig as ApiConfig for backward compatibility
import 'package:cargo/config/api_config.dart';

class ApiConfig {
  static String get baseUrl => GlobalApiConfig.baseUrl;
  static String getCarImageUrl(String? path) => GlobalApiConfig.getCarImageUrl(path);
  static String getImageUrl(String? path) => GlobalApiConfig.getImageUrl(path);
}
