// Stub file for SortBottomSheet
import 'package:flutter/material.dart';

class SortBottomSheet extends StatelessWidget {
  final String? currentSort;
  final String? currentSortBy;
  final String? currentSortOrder;
  final ValueChanged<String>? onSortSelected;
  final void Function(String sortBy, String sortOrder)? onSortChanged;

  const SortBottomSheet({
    super.key,
    this.currentSort,
    this.currentSortBy,
    this.currentSortOrder,
    this.onSortSelected,
    this.onSortChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Sort By', style: TextStyle(fontWeight: FontWeight.bold)),
          ListTile(
            title: const Text('Price: Low to High'),
            onTap: () {
              onSortSelected?.call('price_asc');
              onSortChanged?.call('price', 'asc');
              Navigator.pop(context);
            },
          ),
          ListTile(
            title: const Text('Price: High to Low'),
            onTap: () {
              onSortSelected?.call('price_desc');
              onSortChanged?.call('price', 'desc');
              Navigator.pop(context);
            },
          ),
        ],
      ),
    );
  }

  static Future<String?> show(BuildContext context, {String? currentSort}) {
    return showModalBottomSheet<String>(
      context: context,
      builder: (_) => SortBottomSheet(currentSort: currentSort),
    );
  }
}
