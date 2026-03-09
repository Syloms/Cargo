// Stub file for MotorcyclesMapViewScreen
import 'package:flutter/material.dart';

class MotorcyclesMapViewScreen extends StatelessWidget {
  final List<Map<String, dynamic>>? motorcycles;
  final String? title;

  const MotorcyclesMapViewScreen({super.key, this.motorcycles, this.title});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title ?? 'Motorcycles Map View')),
      body: const Center(child: Text('Map View')),
    );
  }
}
