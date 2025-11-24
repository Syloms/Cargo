import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:cargo/models/user_verification.dart';
import 'package:cargo/screens/verification/id_upload_screen.dart';

class PersonalInfoScreen extends StatefulWidget {
  final UserVerification? existingData;

  const PersonalInfoScreen({super.key, this.existingData});

  @override
  State<PersonalInfoScreen> createState() => _PersonalInfoScreenState();
}

class _PersonalInfoScreenState extends State<PersonalInfoScreen> {
  late UserVerification verification;
  
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _mobileController = TextEditingController();

  final List<String> regions = ['NCR', 'Region I', 'Region II', 'Region III', 'Region IV-A'];
  final List<String> genders = ['Male', 'Female', 'Prefer not to say'];

  @override
  void initState() {
    super.initState();
    verification = widget.existingData ?? UserVerification();
    _firstNameController.text = verification.firstName ?? '';
    _lastNameController.text = verification.lastName ?? '';
    _emailController.text = verification.email ?? '';
    _mobileController.text = verification.mobileNumber ?? '';
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _mobileController.dispose();
    super.dispose();
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
                  Expanded(child: _buildProgressLine(false)),
                  _buildProgressDot(false),
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
                      'Personal Information',
                      style: GoogleFonts.poppins(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Refrain from using names or any information than your real one to avoid any potential issues.',
                      style: GoogleFonts.poppins(
                        fontSize: 11,
                        color: Colors.orange[700],
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    _buildTextField('First Name', _firstNameController,
                      onChanged: (v) => verification.firstName = v),
                    const SizedBox(height: 16),
                    
                    _buildTextField('Last Name', _lastNameController,
                      onChanged: (v) => verification.lastName = v),
                    const SizedBox(height: 24),
                    
                    Text(
                      'PERMANENT ADDRESS',
                      style: GoogleFonts.poppins(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[600],
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    _buildDropdown('Region', regions, verification.permRegion,
                      (v) => setState(() => verification.permRegion = v)),
                    const SizedBox(height: 16),
                    
                    _buildTextField('Email Address', _emailController,
                      keyboardType: TextInputType.emailAddress,
                      onChanged: (v) => verification.email = v),
                    const SizedBox(height: 16),
                    
                    _buildTextField('Mobile Number', _mobileController,
                      keyboardType: TextInputType.phone,
                      hint: 'Enter mobile number',
                      onChanged: (v) => verification.mobileNumber = v),
                    const SizedBox(height: 16),
                    
                    _buildDropdown('Gender', genders, verification.gender,
                      (v) => setState(() => verification.gender = v)),
                    const SizedBox(height: 16),
                    
                    _buildDatePicker(),
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _canContinue() ? () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => IDUploadScreen(verification: verification),
                      ),
                    );
                  } : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _canContinue() ? const Color(0xFFCDFE3D) : Colors.grey[300],
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                    elevation: 0,
                  ),
                  child: Text(
                    'Continue',
                    style: GoogleFonts.poppins(
                      color: _canContinue() ? Colors.black : Colors.grey[500],
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _canContinue() {
    return (verification.firstName?.isNotEmpty ?? false) &&
           (verification.lastName?.isNotEmpty ?? false) &&
           verification.permRegion != null &&
           (verification.email?.isNotEmpty ?? false) &&
           (verification.mobileNumber?.isNotEmpty ?? false) &&
           verification.gender != null &&
           verification.dateOfBirth != null;
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

  Widget _buildTextField(String label, TextEditingController controller, {
    String? hint,
    TextInputType? keyboardType,
    Function(String)? onChanged,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontSize: 12,
            color: Colors.grey[700],
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          keyboardType: keyboardType,
          onChanged: onChanged,
          style: GoogleFonts.poppins(fontSize: 13),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: GoogleFonts.poppins(
              fontSize: 12,
              color: Colors.grey[400],
            ),
            filled: true,
            fillColor: Colors.grey[100],
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide.none,
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          ),
        ),
      ],
    );
  }

  Widget _buildDatePicker() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Date of Birth',
          style: GoogleFonts.poppins(
            fontSize: 12,
            color: Colors.grey[700],
          ),
        ),
        const SizedBox(height: 8),
        GestureDetector(
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: verification.dateOfBirth ?? DateTime(2000),
              firstDate: DateTime(1900),
              lastDate: DateTime.now(),
            );
            if (date != null) {
              setState(() => verification.dateOfBirth = date);
            }
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: Colors.grey[100],
              borderRadius: BorderRadius.circular(8),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  verification.dateOfBirth != null
                    ? DateFormat('yyyy-MM-dd').format(verification.dateOfBirth!)
                    : 'Select Date',
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    color: verification.dateOfBirth != null ? Colors.black : Colors.grey[400],
                  ),
                ),
                Icon(Icons.arrow_drop_down, size: 20, color: Colors.grey[600]),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDropdown(String label, List<String> items, String? value, Function(String?) onChanged) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontSize: 12,
            color: Colors.grey[700],
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: Colors.grey[100],
            borderRadius: BorderRadius.circular(8),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: value,
              isExpanded: true,
              hint: Text(
                'Select',
                style: GoogleFonts.poppins(
                  color: Colors.grey[400],
                  fontSize: 12,
                ),
              ),
              icon: Icon(Icons.arrow_drop_down, color: Colors.grey[600]),
              items: items.map((item) {
                return DropdownMenuItem(
                  value: item,
                  child: Text(
                    item,
                    style: GoogleFonts.poppins(fontSize: 13),
                  ),
                );
              }).toList(),
              onChanged: onChanged,
            ),
          ),
        ),
      ],
    );
  }
}