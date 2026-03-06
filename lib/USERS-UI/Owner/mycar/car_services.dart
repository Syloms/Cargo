import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import './api_constants.dart';
import 'package:cargo/config/api_config.dart';

class CarService {
  /* ---------------- FETCH CARS ---------------- */
Future<List<Map<String, dynamic>>> fetchCars(int ownerId) async {
  try {
    final url = "${ApiConstants.carsApi}?owner_id=$ownerId";
    debugPrint("🚗 Fetching cars from: $url");
    debugPrint("🚗 Owner ID: $ownerId");
    
    // ✅ CRASH FIX: Timeout already present, add better error handling
    final response = await http
        .get(Uri.parse(url))
        .timeout(
          ApiConstants.apiTimeout,
          onTimeout: () {
            throw Exception('Connection timeout after ${ApiConstants.apiTimeout.inSeconds}s');
          },
        );

    debugPrint("🚗 Response status: ${response.statusCode}");
    debugPrint("🚗 Response body: ${response.body}");

    if (response.statusCode == 200) {
      // ✅ CRASH FIX: Wrap jsonDecode in try-catch
      try {
        final data = jsonDecode(response.body);

        debugPrint("🚗 Decoded data type: ${data.runtimeType}");
        debugPrint("🚗 Data length: ${data is List ? data.length : 'not a list'}");

        if (data is List) {
          return data.map<Map<String, dynamic>>((car) {
            final String baseUrl = ApiConstants.baseUrl;

            String imagePath = car['image']?.toString() ?? "";

            // Convert Windows/server path to web path
            if (imagePath.contains("uploads")) {
              imagePath = imagePath.split("uploads").last;
              imagePath = "uploads$imagePath";
            }

            return {
              ...Map<String, dynamic>.from(car),
              "image": "$baseUrl/$imagePath",
            };
          }).toList();
        }
      } catch (jsonError) {
        debugPrint("❌ JSON decode error: $jsonError");
        throw Exception('Invalid data format from server');
      }
    } else {
      debugPrint("❌ HTTP error: ${response.statusCode}");
      throw Exception('Server error: ${response.statusCode}');
    }
  } catch (e) {
    debugPrint("❌ Error fetching cars: $e");
    // ✅ CRASH FIX: Rethrow with context for better error handling
    rethrow;
  }

  return [];
}


  /* ---------------- DELETE VEHICLE (CAR OR MOTORCYCLE) ---------------- */
  Future<Map<String, dynamic>> deleteVehicle(int vehicleId, String vehicleType, int ownerId) async {
    try {
      debugPrint("🗑️ Deleting vehicle - ID: $vehicleId, Type: $vehicleType, Owner: $ownerId");
      
      final response = await http.post(
        Uri.parse(ApiConstants.carsApi),
        body: {
          "action": "delete",
          "id": vehicleId.toString(),
          "vehicle_type": vehicleType,
          "owner_id": ownerId.toString(),
        },
      ).timeout(ApiConstants.apiTimeout);

      debugPrint("🗑️ Delete response status: ${response.statusCode}");
      debugPrint("🗑️ Delete response body: ${response.body}");

      if (response.statusCode == 200) {
        final result = jsonDecode(response.body);
        return {
          'success': result["success"] == true,
          'message': result["message"] ?? 'Unknown error',
        };
      } else {
        return {
          'success': false,
          'message': 'Server error: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint("❌ Delete error: $e");
      return {
        'success': false,
        'message': 'Failed to delete: $e',
      };
    }
  }
  
  /* ---------------- DELETE CAR (Legacy - for backward compatibility) ---------------- */
  Future<bool> deleteCar(int carId) async {
    final result = await deleteVehicle(carId, 'car', 0);
    return result['success'] == true;
  }

  /* ---------------- GET PRICE PREDICTION ---------------- */
  Future<Map<String, dynamic>> getPricePrediction(int carId, String vehicleType) async {
    try {
      final url = "${GlobalApiConfig.pricePredictionEndpoint}?car_id=$carId&vehicle_type=$vehicleType";
      debugPrint("💰 Fetching price prediction from: $url");

      final response = await http.get(Uri.parse(url)).timeout(ApiConstants.apiTimeout);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is Map<String, dynamic> ? data : {"success": false, "message": "Invalid response"};
      }
      return {"success": false, "message": "Server error: ${response.statusCode}"};
    } catch (e) {
      debugPrint("❌ Price prediction error: $e");
      return {"success": false, "message": e.toString()};
    }
  }

  /* ---------------- UPDATE CAR PRICE ---------------- */
  Future<Map<String, dynamic>> updateCarPrice(int carId, int ownerId, String vehicleType, double newPrice) async {
    try {
      debugPrint("💰 Updating price - carId: $carId, vehicleType: $vehicleType, newPrice: $newPrice");

      final response = await http.post(
        Uri.parse(GlobalApiConfig.updateCarPriceEndpoint),
        body: {
          "car_id": carId.toString(),
          "owner_id": ownerId.toString(),
          "vehicle_type": vehicleType,
          "new_price": newPrice.toString(),
        },
      ).timeout(ApiConstants.apiTimeout);

      debugPrint("💰 Update price response: ${response.body}");

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data is Map<String, dynamic> ? data : {"success": false, "message": "Invalid response"};
      }
      return {"success": false, "message": "Server error: ${response.statusCode}"};
    } catch (e) {
      debugPrint("❌ Update price error: $e");
      return {"success": false, "message": e.toString()};
    }
  }
}