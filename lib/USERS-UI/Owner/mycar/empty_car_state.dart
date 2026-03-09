// Stub file for EmptyCarState
import 'package:flutter/material.dart';

class EmptyCarState extends StatelessWidget {
  final String searchQuery;

  const EmptyCarState({super.key, required this.searchQuery});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.directions_car_outlined, size: 64, color: Colors.grey.shade400),
          const SizedBox(height: 16),
          Text(
            searchQuery.isEmpty ? 'No vehicles yet' : 'No results for "$searchQuery"',
            style: TextStyle(fontSize: 16, color: Colors.grey.shade600),
          ),
        ],
      ),
    );
  }
}
