import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:cargo/config/api_config.dart';

class OwnerDamageReportScreen extends StatefulWidget {
  final int bookingId;
  final String renterName;
  final String carName;
  final int ownerId;

  const OwnerDamageReportScreen({
    super.key,
    required this.bookingId,
    required this.renterName,
    required this.carName,
    required this.ownerId,
  });

  @override
  State<OwnerDamageReportScreen> createState() =>
      _OwnerDamageReportScreenState();
}

class _OwnerDamageReportScreenState extends State<OwnerDamageReportScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();
  final _costController = TextEditingController();
  final _picker = ImagePicker();

  final List<String> _damageOptions = [
    'Scratch',
    'Dent',
    'Broken window/mirror',
    'Interior damage',
    'Missing parts',
    'Engine damage',
    'Tire damage',
    'Body damage',
    'Electrical damage',
    'Other',
  ];

  final Set<String> _selectedDamageTypes = {};
  final List<File?> _images = [null, null, null, null];
  bool _isSubmitting = false;

  @override
  void dispose() {
    _descriptionController.dispose();
    _costController.dispose();
    super.dispose();
  }

  Future<void> _pickImage(int slot) async {
    try {
      final XFile? picked = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 75,
        maxWidth: 1920,
      );
      if (picked == null || !mounted) return;
      setState(() => _images[slot] = File(picked.path));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to pick image: $e'), backgroundColor: Colors.red),
      );
    }
  }

  void _removeImage(int slot) => setState(() => _images[slot] = null);

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedDamageTypes.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select at least one damage type'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final hasImage = _images.any((img) => img != null);
    if (!hasImage) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please attach at least one photo as evidence'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final confirmed = await _showConfirmDialog();
    if (!confirmed || !mounted) return;

    setState(() => _isSubmitting = true);

    try {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse(GlobalApiConfig.submitDamageReportEndpoint),
      );

      request.fields['booking_id'] = widget.bookingId.toString();
      request.fields['owner_id'] = widget.ownerId.toString();
      request.fields['damage_types'] = jsonEncode(_selectedDamageTypes.toList());
      request.fields['description'] = _descriptionController.text.trim();
      request.fields['estimated_cost'] = _costController.text.trim();

      for (int i = 0; i < 4; i++) {
        if (_images[i] != null) {
          request.files.add(
            await http.MultipartFile.fromPath('image_${i + 1}', _images[i]!.path),
          );
        }
      }

      final streamed = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamed);

      if (!mounted) return;

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        _showSuccessDialog();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(data['message'] ?? 'Submission failed'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: ${e.toString().replaceAll('Exception: ', '')}'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<bool> _showConfirmDialog() async {
    return await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            title: Text('Submit Damage Report?',
                style: GoogleFonts.outfit(fontWeight: FontWeight.bold)),
            content: Text(
              'Your report will be reviewed by our admin team. If approved, the estimated amount may be deducted from the renter\'s security deposit.',
              style: GoogleFonts.inter(fontSize: 14),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: Text('Cancel', style: GoogleFonts.inter(color: Colors.grey)),
              ),
              ElevatedButton(
                onPressed: () => Navigator.pop(ctx, true),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red.shade700,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: Text('Submit', style: GoogleFonts.inter(color: Colors.white)),
              ),
            ],
          ),
        ) ??
        false;
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check_circle, color: Colors.green, size: 52),
            ),
            const SizedBox(height: 16),
            Text(
              'Report Submitted',
              style: GoogleFonts.outfit(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Our admin team will review your damage report and mediate within 24-48 hours. You\'ll be notified of the outcome.',
              style: GoogleFonts.inter(fontSize: 13, color: Colors.grey.shade600),
              textAlign: TextAlign.center,
            ),
          ],
        ),
        actions: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.pop(context, true);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: Text('Done', style: GoogleFonts.inter(color: Colors.white, fontWeight: FontWeight.w600)),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, size: 18),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Report Damage',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w700, fontSize: 18),
        ),
        centerTitle: true,
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header info card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.car_crash, color: Colors.red.shade700, size: 24),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.carName,
                            style: GoogleFonts.outfit(
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                          Text(
                            'Renter: ${widget.renterName}',
                            style: GoogleFonts.inter(
                              fontSize: 13,
                              color: Colors.grey.shade600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 8),

              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.amber.shade50,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.amber.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.amber.shade800, size: 18),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Admin will mediate and notify both parties. Approved amounts may be deducted from the security deposit.',
                        style: GoogleFonts.inter(fontSize: 12, color: Colors.amber.shade900),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Damage types
              Text('Damage Types *',
                  style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _damageOptions.map((type) {
                  final selected = _selectedDamageTypes.contains(type);
                  return FilterChip(
                    label: Text(type, style: GoogleFonts.inter(fontSize: 13)),
                    selected: selected,
                    onSelected: (val) {
                      setState(() {
                        if (val) {
                          _selectedDamageTypes.add(type);
                        } else {
                          _selectedDamageTypes.remove(type);
                        }
                      });
                    },
                    selectedColor: Colors.red.shade100,
                    checkmarkColor: Colors.red.shade700,
                    side: BorderSide(
                      color: selected ? Colors.red.shade400 : Colors.grey.shade300,
                    ),
                    backgroundColor: Colors.white,
                  );
                }).toList(),
              ),

              const SizedBox(height: 24),

              // Description
              Text('Description *',
                  style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              TextFormField(
                controller: _descriptionController,
                maxLines: 4,
                maxLength: 500,
                validator: (v) {
                  if (v == null || v.trim().length < 10) {
                    return 'Please provide a description (min 10 characters)';
                  }
                  return null;
                },
                decoration: InputDecoration(
                  hintText: 'Describe the damage in detail...',
                  hintStyle: GoogleFonts.inter(color: Colors.grey.shade400, fontSize: 14),
                  filled: true,
                  fillColor: Colors.grey.shade50,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.grey.shade300),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.grey.shade300),
                  ),
                  contentPadding: const EdgeInsets.all(14),
                ),
                style: GoogleFonts.inter(fontSize: 14),
              ),

              const SizedBox(height: 20),

              // Estimated cost
              Text('Estimated Repair Cost (₱) *',
                  style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              TextFormField(
                controller: _costController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                validator: (v) {
                  final val = double.tryParse(v ?? '');
                  if (val == null || val <= 0) return 'Enter a valid amount';
                  return null;
                },
                decoration: InputDecoration(
                  hintText: '0.00',
                  prefixText: '₱ ',
                  filled: true,
                  fillColor: Colors.grey.shade50,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.grey.shade300),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.grey.shade300),
                  ),
                  contentPadding: const EdgeInsets.all(14),
                ),
                style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w600),
              ),

              const SizedBox(height: 24),

              // Photos
              Text('Damage Photos * (min 1, max 4)',
                  style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text(
                'Clear photos help the admin assess the damage accurately',
                style: GoogleFonts.inter(fontSize: 12, color: Colors.grey.shade500),
              ),
              const SizedBox(height: 12),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 1.1,
                children: List.generate(4, (i) => _buildPhotoSlot(i)),
              ),

              const SizedBox(height: 32),

              // Submit button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submit,
                  icon: _isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.send_outlined, size: 20),
                  label: Text(
                    _isSubmitting ? 'Submitting...' : 'Submit Damage Report',
                    style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red.shade700,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.red.shade300,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    elevation: 2,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),

              const SizedBox(height: 12),
              Center(
                child: Text(
                  'False reports may result in account suspension',
                  style: GoogleFonts.inter(fontSize: 11, color: Colors.grey.shade500),
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPhotoSlot(int slot) {
    final image = _images[slot];
    return GestureDetector(
      onTap: () => image == null ? _pickImage(slot) : null,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: image != null ? Colors.red.shade300 : Colors.grey.shade300,
            width: image != null ? 2 : 1,
          ),
        ),
        child: image != null
            ? Stack(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(11),
                    child: Image.file(
                      image,
                      fit: BoxFit.cover,
                      width: double.infinity,
                      height: double.infinity,
                    ),
                  ),
                  Positioned(
                    top: 6,
                    right: 6,
                    child: GestureDetector(
                      onTap: () => _removeImage(slot),
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.red,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.close, color: Colors.white, size: 14),
                      ),
                    ),
                  ),
                ],
              )
            : Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.add_a_photo_outlined, color: Colors.grey.shade400, size: 28),
                  const SizedBox(height: 6),
                  Text(
                    'Photo ${slot + 1}',
                    style: GoogleFonts.inter(fontSize: 12, color: Colors.grey.shade500),
                  ),
                ],
              ),
      ),
    );
  }
}
