// Stub file for ImageHelper
import 'package:cargo/config/api_config.dart';

class ImageHelper {
  static String getImageUrl(String? path) => GlobalApiConfig.getImageUrl(path);
  static String getCarImageUrl(String? path) => GlobalApiConfig.getCarImageUrl(path);
  static String formatImageUrl(String? path) => GlobalApiConfig.getImageUrl(path);
}
