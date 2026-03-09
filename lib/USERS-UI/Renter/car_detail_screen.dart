// Stub file for CarDetailScreen
import 'package:flutter/material.dart';

class CarDetailScreen extends StatelessWidget {
  final Map<String, dynamic>? car;
  final String? userId;
  final int? carId;
  final String? carName;
  final String? carImage;
  final dynamic price;
  final dynamic rating;
  final String? location;

  const CarDetailScreen({
    super.key,
    this.car,
    this.userId,
    this.carId,
    this.carName,
    this.carImage,
    this.price,
    this.rating,
    this.location,
  });

  @override
  Widget build(BuildContext context) {
    final title = carName ?? car?['brand']?.toString() ?? 'Car Details';
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: const Center(child: Text('Car Details')),
    );
  }
}
