// Stub file for SavedSearchesScreen
import 'package:flutter/material.dart';

class SavedSearchesScreen extends StatelessWidget {
  final ValueChanged<Map<String, dynamic>>? onSearchSelected;

  const SavedSearchesScreen({super.key, this.onSearchSelected});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Saved Searches')),
      body: const Center(child: Text('Saved Searches')),
    );
  }
}
