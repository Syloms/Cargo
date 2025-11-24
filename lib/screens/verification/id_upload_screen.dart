import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:cargo/models/user_verification.dart';
import 'package:cargo/screens/verification/selfie_screen.dart';

class IDUploadScreen extends StatefulWidget {
  final UserVerification verification;

  const IDUploadScreen({super.key, required this.verification});

  @override
  State<IDUploadScreen> createState() => _IDUploadScreenState();
}

class _IDUploadScreenState extends State<IDUploadScreen> {
  final ImagePicker _picker = ImagePicker();
  File? _frontImage;
  File? _backImage;
  Uint8List? _frontImageBytes;
  Uint8List? _backImageBytes;
  String? _selectedIdType;

  final List<Map<String, String>> idTypes = [
    {'value': 'drivers_license', 'label': 'Driver\'s License'},
    {'value': 'passport', 'label': 'Passport'},
    {'value': 'national_id', 'label': 'National ID'},
    {'value': 'umid', 'label': 'UMID'},
    {'value': 'sss', 'label': 'SSS ID'},
    {'value': 'philhealth', 'label': 'PhilHealth ID'},
    {'value': 'voters_id', 'label': 'Voter\'s ID'},
    {'value': 'postal_id', 'label': 'Postal ID'},
  ];

  Future<void> _pickImage(bool isFront) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );

      if (image != null) {
        if (kIsWeb) {
          final bytes = await image.readAsBytes();
          setState(() {
            if (isFront) {
              _frontImageBytes = Uint8List.fromList(bytes);
              widget.verification.idFrontPhoto = image.path;
            } else {
              _backImageBytes = Uint8List.fromList(bytes);
              widget.verification.idBackPhoto = image.path;
            }
          });
        } else {
          setState(() {
            if (isFront) {
              _frontImage = File(image.path);
              widget.verification.idFrontPhoto = image.path;
            } else {
              _backImage = File(image.path);
              widget.verification.idBackPhoto = image.path;
            }
          });
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _pickFromGallery(bool isFront) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );

      if (image != null) {
        if (kIsWeb) {
          final bytes = await image.readAsBytes();
          setState(() {
            if (isFront) {
              _frontImageBytes = Uint8List.fromList(bytes);
              widget.verification.idFrontPhoto = image.path;
            } else {
              _backImageBytes = Uint8List.fromList(bytes);
              widget.verification.idBackPhoto = image.path;
            }
          });
        } else {
          setState(() {
            if (isFront) {
              _frontImage = File(image.path);
              widget.verification.idFrontPhoto = image.path;
            } else {
              _backImage = File(image.path);
              widget.verification.idBackPhoto = image.path;
            }
          });
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _showImageSourceOptions(bool isFront) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Container(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Choose Image Source',
              style: GoogleFonts.poppins(
                fontSize: 18,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 24),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Colors.black),
              title: Text('Camera', style: GoogleFonts.poppins(fontSize: 15)),
              onTap: () {
                Navigator.pop(context);
                _pickImage(isFront);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Colors.black),
              title: Text('Gallery', style: GoogleFonts.poppins(fontSize: 15)),
              onTap: () {
                Navigator.pop(context);
                _pickFromGallery(isFront);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Verification',
          style: GoogleFonts.poppins(
            color: Colors.black,
            fontWeight: FontWeight.w600,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Row(
                children: [
                  _buildProgressDot(true),
                  Expanded(child: _buildProgressLine(true)),
                  _buildProgressDot(true),
                  Expanded(child: _buildProgressLine(false)),
                  _buildProgressDot(false),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Upload Valid ID',
                      style: GoogleFonts.poppins(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Please upload a clear photo of your government-issued ID.',
                      style: GoogleFonts.poppins(fontSize: 12, color: Colors.grey[600]),
                    ),
                    const SizedBox(height: 24),
                    _buildDropdown(),
                    const SizedBox(height: 32),
                    _buildUploadSection('Front of ID', true),
                    const SizedBox(height: 24),
                    _buildUploadSection('Back of ID', false),
                  ],
                ),
              ),
            ),
            _buildContinueButton(),
          ],
        ),
      ),
    );
  }

  Widget _buildProgressDot(bool isActive) {
    return Container(
      width: 10,
      height: 10,
      decoration: BoxDecoration(
        color: isActive ? const Color(0xFFCDFE3D) : Colors.grey[300],
        shape: BoxShape.circle,
      ),
    );
  }

  Widget _buildProgressLine(bool isActive) {
    return Container(
      height: 2,
      color: isActive ? const Color(0xFFCDFE3D) : Colors.grey[300],
    );
  }

  Widget _buildDropdown() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('ID Type', style: GoogleFonts.poppins(fontSize: 12, color: Colors.grey[700])),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.grey[100],
            borderRadius: BorderRadius.circular(8),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: _selectedIdType,
              isExpanded: true,
              hint: Text('Select ID Type', style: GoogleFonts.poppins(color: Colors.grey[400], fontSize: 12)),
              icon: Icon(Icons.arrow_drop_down, color: Colors.grey[600]),
              items: idTypes.map((id) {
                return DropdownMenuItem(
                  value: id['value'],
                  child: Text(id['label']!, style: GoogleFonts.poppins(fontSize: 13)),
                );
              }).toList(),
              onChanged: (value) => setState(() => _selectedIdType = value),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildUploadSection(String title, bool isFront) {
    final hasImage = kIsWeb 
        ? (isFront ? _frontImageBytes != null : _backImageBytes != null)
        : (isFront ? _frontImage != null : _backImage != null);
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w600)),
        const SizedBox(height: 12),
        GestureDetector(
          onTap: () => _showImageSourceOptions(isFront),
          child: Container(
            height: 200,
            decoration: BoxDecoration(
              color: Colors.grey[50],
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: hasImage ? const Color(0xFFCDFE3D) : Colors.grey[300]!,
                width: 2,
              ),
            ),
            child: hasImage
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: kIsWeb
                        ? Image.memory(
                            isFront ? _frontImageBytes! : _backImageBytes!,
                            width: double.infinity,
                            height: double.infinity,
                            fit: BoxFit.cover,
                          )
                        : Image.file(
                            isFront ? _frontImage! : _backImage!,
                            width: double.infinity,
                            height: double.infinity,
                            fit: BoxFit.cover,
                          ),
                  )
                : Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.camera_alt_outlined, size: 40, color: Colors.grey[600]),
                      const SizedBox(height: 16),
                      Text('Tap to upload', style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500, color: Colors.grey[700])),
                    ],
                  ),
          ),
        ),
      ],
    );
  }

  Widget _buildContinueButton() {
    final hasFront = kIsWeb ? _frontImageBytes != null : _frontImage != null;
    final hasBack = kIsWeb ? _backImageBytes != null : _backImage != null;
    final canContinue = _selectedIdType != null && hasFront && hasBack;
    
    return Padding(
      padding: const EdgeInsets.all(24),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: canContinue
              ? () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => SelfieScreen(verification: widget.verification),
                    ),
                  );
                }
              : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: canContinue ? const Color(0xFFCDFE3D) : Colors.grey[300],
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
            elevation: 0,
          ),
          child: Text(
            'Continue',
            style: GoogleFonts.poppins(
              color: canContinue ? Colors.black : Colors.grey[500],
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}