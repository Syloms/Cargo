import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/config/api_config.dart';

class EditProfile extends StatefulWidget {
  const EditProfile({super.key});

  @override
  State<EditProfile> createState() => _EditProfileState();
}

class _EditProfileState extends State<EditProfile> with SingleTickerProviderStateMixin {
  final String apiUrl = GlobalApiConfig.updateProfileEndpoint;

  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final addressController = TextEditingController();

  File? imageFile;
  Uint8List? webImage;
  String storedImage = "";

  bool saving = false;
  bool hasChanges = false;
  late AnimationController _fadeController;

  @override
  void initState() {
    super.initState();
    load();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    )..forward();

    nameController.addListener(_detectChanges);
    phoneController.addListener(_detectChanges);
    addressController.addListener(_detectChanges);
  }

  @override
  void dispose() {
    _fadeController.dispose();
    nameController.dispose();
    phoneController.dispose();
    addressController.dispose();
    super.dispose();
  }

  void _detectChanges() {
    setState(() => hasChanges = true);
  }

  Future<void> load() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    nameController.text = prefs.getString("fullname") ?? "";
    phoneController.text = prefs.getString("phone") ?? "";
    addressController.text = prefs.getString("address") ?? "";
    storedImage = prefs.getString("profile_image") ?? "";
    // ✅ CRASH FIX: Check mounted before setState
    if (!mounted) return;
    setState(() {});
  }

  Future pickImage() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Drag handle
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 24),
            
            Text(
              'Change Profile Photo',
              style: GoogleFonts.poppins(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 24),
            
            _buildImageSourceOption(
              icon: Icons.photo_library_rounded,
              title: 'Choose from Gallery',
              subtitle: 'Select from your photos',
              onTap: () {
                Navigator.pop(context);
                _pickImageFromSource(ImageSource.gallery);
              },
            ),
            const SizedBox(height: 12),
            
            _buildImageSourceOption(
              icon: Icons.camera_alt_rounded,
              title: 'Take a Photo',
              subtitle: 'Use your camera',
              onTap: () {
                Navigator.pop(context);
                _pickImageFromSource(ImageSource.camera);
              },
            ),
            
            if (storedImage.isNotEmpty || imageFile != null || webImage != null) ...[
              const SizedBox(height: 12),
              _buildImageSourceOption(
                icon: Icons.delete_outline_rounded,
                title: 'Remove Photo',
                subtitle: 'Use default avatar',
                onTap: () {
                  setState(() {
                    imageFile = null;
                    webImage = null;
                    storedImage = "";
                    hasChanges = true;
                  });
                  Navigator.pop(context);
                },
                isDestructive: true,
              ),
            ],
            
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Widget _buildImageSourceOption({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
    bool isDestructive = false,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isDestructive ? Colors.red.shade50 : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isDestructive ? Colors.red.shade100 : Colors.grey.shade200,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: isDestructive ? Colors.red.shade600 : Colors.black,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.poppins(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: isDestructive ? Colors.red.shade900 : Colors.black,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: GoogleFonts.poppins(
                      fontSize: 12,
                      color: isDestructive ? Colors.red.shade700 : Colors.grey.shade600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.arrow_forward_ios_rounded,
              size: 16,
              color: isDestructive ? Colors.red.shade400 : Colors.grey.shade400,
            ),
          ],
        ),
      ),
    );
  }

  Future _pickImageFromSource(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final file = await picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1920,
        maxHeight: 1920,
      );

      // ✅ CRITICAL: Check if user cancelled
      if (file == null) {
        print("📷 User cancelled image selection");
        return;
      }

      // ✅ CRITICAL: Check mounted before async operations
      if (!mounted) return;

      if (kIsWeb) {
        webImage = await file.readAsBytes();
      } else {
        imageFile = File(file.path);
      }

      // ✅ CRITICAL: Check mounted again after async read
      if (!mounted) return;
      
      setState(() => hasChanges = true);
    } catch (e, stackTrace) {
      print("❌ Error picking image: $e");
      print("Stack trace: $stackTrace");
      
      if (!mounted) return;
      
      _showSnackBar("Failed to select image. Please try again.", isError: true);
    }
  }

  ImageProvider? getImage() {
    if (imageFile != null) return FileImage(imageFile!);
    if (webImage != null) return MemoryImage(webImage!);
    // Safe check for network image - avoid empty strings
    if (storedImage.trim().isNotEmpty &&
        storedImage != "null" &&
        storedImage != "NULL" &&
        (storedImage.startsWith('http://') || storedImage.startsWith('https://'))) {
      return NetworkImage(storedImage);
    }
    return null;
  }

  Future<void> save() async {
    if (nameController.text.trim().isEmpty) {
      _showSnackBar("Name cannot be empty", isError: true);
      return;
    }

    if (phoneController.text.trim().isNotEmpty && phoneController.text.trim().length < 10) {
      _showSnackBar("Please enter a valid phone number", isError: true);
      return;
    }

    setState(() => saving = true);

    final prefs = await SharedPreferences.getInstance();
    final userId = prefs.getString("user_id") ?? "";

    var req = http.MultipartRequest("POST", Uri.parse(apiUrl));
    req.fields["user_id"] = userId;
    req.fields["fullname"] = nameController.text.trim();
    req.fields["phone"] = phoneController.text.trim();
    req.fields["address"] = addressController.text.trim();

    if (imageFile != null) {
      req.files.add(await http.MultipartFile.fromPath("profile_image", imageFile!.path));
    } else if (kIsWeb && webImage != null) {
      req.files.add(http.MultipartFile.fromBytes(
        "profile_image",
        webImage!,
        filename: "profile_${DateTime.now().millisecondsSinceEpoch}.png",
      ));
    }

    try {
      final response = await req.send();
      final json = jsonDecode(await response.stream.bytesToString());

      if (json["success"] == true) {
        final updated = json["user"];

        await prefs.setString("fullname", updated["fullname"]);
        await prefs.setString("phone", updated["phone"] ?? "");
        await prefs.setString("address", updated["address"] ?? "");
        String img = updated["profile_image"] ?? "";
        String finalURL = img.startsWith("http")
            ? img
            : GlobalApiConfig.uploadsUrl + "/profile_images/" + img;

await prefs.setString("profile_image", finalURL);



        if (!mounted) return;

        _showSnackBar("Profile updated successfully!", isError: false);
        
        await Future.delayed(const Duration(milliseconds: 800));
        if (!mounted) return;
        
        Navigator.pop(context, true);
      } else {
        _showSnackBar(json["message"] ?? "Update failed", isError: true);
      }
    } catch (e) {
      _showSnackBar("Network error. Please try again.", isError: true);
    }

    // ✅ CRASH FIX: Check mounted before setState
    if (!mounted) return;
    setState(() => saving = false);
  }

  void _showSnackBar(String message, {required bool isError}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              isError ? Icons.error_outline : Icons.check_circle_outline,
              color: Colors.white,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: GoogleFonts.poppins(fontSize: 14),
              ),
            ),
          ],
        ),
        backgroundColor: isError ? Colors.red.shade600 : Colors.green.shade600,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
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
          icon: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.grey.shade100,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.arrow_back_ios_new,
              color: Theme.of(context).iconTheme.color,



              size: 16,
            ),
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          "Edit Profile",
          style: GoogleFonts.poppins(
            color: Theme.of(context).iconTheme.color,



            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
        actions: [
          if (hasChanges)
            Center(
              child: Container(
                margin: const EdgeInsets.only(right: 16),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.orange.shade100,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.edit, size: 14, color: Colors.orange.shade900),
                    const SizedBox(width: 4),
                    Text(
                      'Unsaved',
                      style: GoogleFonts.poppins(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.orange.shade900,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),

      body: FadeTransition(
        opacity: _fadeController,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const SizedBox(height: 8),

              // Profile Avatar Section with gradient background
              Stack(
                alignment: Alignment.center,
                children: [
                  // Outer gradient circle
                  Container(
                    width: 150,
                    height: 150,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        colors: [
                          Colors.grey.shade300,
                          Colors.grey.shade100,
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                  ),
                  
                  // Avatar container
                  Container(
                    width: 140,
                    height: 140,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.grey.shade200,
                      border: Border.all(color: Colors.white, width: 4),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.1),
                          blurRadius: 20,
                          offset: const Offset(0, 4),
                        ),
                      ],
                      image: getImage() != null
                          ? DecorationImage(
                              image: getImage()!,
                              fit: BoxFit.cover,
                            )
                          : null,
                    ),
                    child: getImage() == null
                        ? Icon(Icons.person_rounded, size: 64, color: Colors.grey.shade400)
                        : null,
                  ),

                  // Edit button with animation
                  Positioned(
                    bottom: 0,
                    right: 4,
                    child: GestureDetector(
                      onTap: pickImage,
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Colors.black, Colors.black87],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 4),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.2),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.camera_alt_rounded,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 16),

              Text(
                'Tap camera icon to change photo',
                style: GoogleFonts.poppins(
                  fontSize: 13,
                  color: Colors.grey.shade600,
                ),
              ),

              const SizedBox(height: 40),

              // Info card with icon
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.blue.shade100),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.blue.shade700, size: 20),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Keep your profile up to date for a better experience',
                        style: GoogleFonts.poppins(
                          fontSize: 12,
                          color: Colors.blue.shade900,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              // Form Fields
              _buildInputField(
                label: 'Full Name',
                controller: nameController,
                icon: Icons.person_outline_rounded,
                hint: 'Enter your full name',
                isRequired: true,
              ),

              const SizedBox(height: 24),

              _buildInputField(
                label: 'Phone Number',
                controller: phoneController,
                icon: Icons.phone_outlined,
                hint: '09XX XXX XXXX',
                keyboardType: TextInputType.phone,
              ),

              const SizedBox(height: 24),

              _buildInputField(
                label: 'Address',
                controller: addressController,
                icon: Icons.location_on_outlined,
                hint: 'Enter your complete address',
                maxLines: 3,
              ),

              const SizedBox(height: 100),
            ],
          ),
        ),
      ),

      // Floating Save Button
      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, -5),
            ),
          ],
        ),
        child: SafeArea(
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            child: ElevatedButton(
              onPressed: saving || !hasChanges ? null : save,
              style: ElevatedButton.styleFrom(
                backgroundColor: Theme.of(context).iconTheme.color,







                disabledBackgroundColor: Colors.grey.shade300,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                elevation: 0,
              ),
              child: saving
                  ? Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          "Saving...",
                          style: GoogleFonts.poppins(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          hasChanges ? Icons.save_outlined : Icons.check_circle_outline,
                          size: 20,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          hasChanges ? "Save Changes" : "No Changes",
                          style: GoogleFonts.poppins(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildInputField({
    required String label,
    required TextEditingController controller,
    required IconData icon,
    required String hint,
    TextInputType keyboardType = TextInputType.text,
    int maxLines = 1,
    bool isRequired = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              label,
              style: GoogleFonts.poppins(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: Colors.black87,
              ),
            ),
            if (isRequired) ...[
              const SizedBox(width: 4),
              Text(
                '*',
                style: GoogleFonts.poppins(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Colors.red,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 10),
        Container(
          decoration: BoxDecoration(
            color: Colors.grey.shade50,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: TextField(
            controller: controller,
            keyboardType: keyboardType,
            maxLines: maxLines,
            style: GoogleFonts.poppins(
              fontSize: 15,
              fontWeight: FontWeight.w500,
            ),
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: GoogleFonts.poppins(
                color: Colors.grey.shade400,
                fontSize: 14,
              ),
              prefixIcon: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 12,
                  top: maxLines > 1 ? 16 : 0,
                  bottom: maxLines > 1 ? 16 : 0,
                ),
                child: Icon(icon, color: Colors.grey.shade600, size: 22),
              ),
              border: InputBorder.none,
              contentPadding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: maxLines > 1 ? 16 : 18,
              ),
            ),
          ),
        ),
      ],
    );
  }
}