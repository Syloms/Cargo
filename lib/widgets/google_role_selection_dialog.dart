// Stub file for GoogleRoleSelectionDialog
import 'package:flutter/material.dart';

class GoogleRoleSelectionDialog extends StatefulWidget {
  final Map<String, dynamic> googleData;
  final Function(String role, String municipality) onComplete;
  final VoidCallback onCancel;

  const GoogleRoleSelectionDialog({
    super.key,
    required this.googleData,
    required this.onComplete,
    required this.onCancel,
  });

  @override
  State<GoogleRoleSelectionDialog> createState() => _GoogleRoleSelectionDialogState();
}

class _GoogleRoleSelectionDialogState extends State<GoogleRoleSelectionDialog> {
  String _selectedRole = 'renter';
  String _selectedMunicipality = '';
  final _municipalityController = TextEditingController();

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Select Your Role'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          RadioGroup<String>(
            groupValue: _selectedRole,
            onChanged: (v) => setState(() => _selectedRole = v!),
            child: Column(
              children: [
                RadioListTile<String>(
                  title: const Text('Renter'),
                  value: 'renter',
                ),
                RadioListTile<String>(
                  title: const Text('Owner'),
                  value: 'owner',
                ),
              ],
            ),
          ),
          TextField(
            controller: _municipalityController,
            decoration: const InputDecoration(labelText: 'Municipality'),
            onChanged: (v) => _selectedMunicipality = v,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: widget.onCancel,
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () => widget.onComplete(_selectedRole, _selectedMunicipality),
          child: const Text('Continue'),
        ),
      ],
    );
  }
}
