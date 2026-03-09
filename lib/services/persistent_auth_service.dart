// Stub file for PersistentAuthService
import 'package:shared_preferences/shared_preferences.dart';

class PersistentAuthService {
  Future<void> saveUserSession(Map<String, dynamic> userData) async {
    final prefs = await SharedPreferences.getInstance();
    userData.forEach((key, value) {
      if (value != null) {
        prefs.setString(key, value.toString());
      }
    });
  }

  Future<void> clearUserSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }

  Future<Map<String, dynamic>?> getUserSession() async {
    return null;
  }
}
