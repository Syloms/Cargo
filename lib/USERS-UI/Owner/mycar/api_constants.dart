// Stub: ApiConstants wrapping GlobalApiConfig
import 'package:cargo/config/api_config.dart';

class ApiConstants {
  static String get baseUrl => GlobalApiConfig.baseUrl;
  static String get carsApi => GlobalApiConfig.carsApiEndpoint;
  static Duration get apiTimeout => GlobalApiConfig.apiTimeout;

  static String getCarImageUrl(String? path) => GlobalApiConfig.getCarImageUrl(path);
  static String getImageUrl(String? path) => GlobalApiConfig.getImageUrl(path);
}
