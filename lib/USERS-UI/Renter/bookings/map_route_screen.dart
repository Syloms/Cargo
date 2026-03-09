// Stub file for MapRouteScreen
import 'package:flutter/material.dart';

class MapRouteScreen extends StatelessWidget {
  final double destinationLat;
  final double destinationLng;
  final String locationName;
  final String carName;

  const MapRouteScreen({
    super.key,
    required this.destinationLat,
    required this.destinationLng,
    required this.locationName,
    required this.carName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Directions to $locationName')),
      body: const Center(child: Text('Map Route')),
    );
  }
}
