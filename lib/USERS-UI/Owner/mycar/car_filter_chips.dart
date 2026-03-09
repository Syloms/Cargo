// Stub file for CarFilterChips
import 'package:flutter/material.dart';

class CarFilterChips extends StatelessWidget {
  final String selectedFilter;
  final ValueChanged<String> onFilterChanged;

  const CarFilterChips({
    super.key,
    required this.selectedFilter,
    required this.onFilterChanged,
  });

  @override
  Widget build(BuildContext context) {
    final filters = ['All', 'Approved', 'Pending', 'Rejected', 'Rented'];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: filters.map((filter) {
          final isSelected = selectedFilter == filter;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilterChip(
              label: Text(filter),
              selected: isSelected,
              onSelected: (_) => onFilterChanged(filter),
            ),
          );
        }).toList(),
      ),
    );
  }
}
