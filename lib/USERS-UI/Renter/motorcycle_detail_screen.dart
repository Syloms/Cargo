// Stub file for MotorcycleDetailScreen
import 'package:flutter/material.dart';

class MotorcycleDetailScreen extends StatelessWidget {
  final Map<String, dynamic>? motorcycle;
  final String? userId;
  final int? motorcycleId;
  final String? motorcycleName;
  final String? motorcycleImage;
  final dynamic price;
  final dynamic rating;
  final String? location;

  const MotorcycleDetailScreen({
    super.key,
    this.motorcycle,
    this.userId,
    this.motorcycleId,
    this.motorcycleName,
    this.motorcycleImage,
    this.price,
    this.rating,
    this.location,
  });

  @override
  Widget build(BuildContext context) {
    final title = motorcycleName ?? motorcycle?['brand']?.toString() ?? 'Motorcycle Details';
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: const Center(child: Text('Motorcycle Details')),
    );
  }
}
