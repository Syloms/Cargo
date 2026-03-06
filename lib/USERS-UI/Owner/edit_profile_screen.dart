import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class EditProfileScreen extends StatefulWidget {
  final String api;
  const EditProfileScreen({super.key, required this.api});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen>
    with SingleTickerProviderStateMixin {

  File? imageFile;
  Uint8List? webImage;

  late AnimationController controller;
  late Animation<double> fadeAnimation;
  late Animation<double> scaleAnimation;

  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final addressController = TextEditingController();
  final gcashNumberController = TextEditingController();
  final gcashNameController = TextEditingController();

  String storedProfile = "";
  bool saving = false;
  bool hasChanges = false;

  @override
  void initState() {
    super.initState();
    animate();
    loadData();

    nameController.addListener(_detectChanges);
    phoneController.addListener(_detectChanges);
    addressController.addListener(_detectChanges);
    gcashNumberController.addListener(_detectChanges);
    gcashNameController.addListener(_detectChanges);
  }

  void animate() {
    controller = AnimationController(
        duration: const Duration(milliseconds: 500), vsync: this);

    fadeAnimation = CurvedAnimation(parent: controller, curve: Curves.easeOut);
    scaleAnimation = Tween<double>(begin: 0.8, end: 1.0)
        .animate(CurvedAnimation(parent: controller, curve: Curves.easeOutBack));

    controller.forward();
  }

  Future<void> loadData() async {
    final prefs = await SharedPreferences.getInstance();
    nameController.text = prefs.getString("fullname") ?? "";
    phoneController.text = prefs.getString("phone") ?? "";
    addressController.text = prefs.getString("address") ?? "";
    gcashNumberController.text = prefs.getString("gcash_number") ?? "";
    gcashNameController.text = prefs.getString("gcash_name") ?? "";
    
    // Clean up malformed profile image URLs (doubled http:// or https://)
    String profileImg = prefs.getString("profile_image") ?? "";
    final hasDoubleHttp = profileImg.contains('http://') &&
        profileImg.indexOf('http://') != profileImg.lastIndexOf('http://');
    final hasDoubleHttps = profileImg.contains('https://') &&
        profileImg.indexOf('https://') != profileImg.lastIndexOf('https://');
    if (hasDoubleHttp || hasDoubleHttps) {
      int lastHttpsIndex = profileImg.lastIndexOf('https://');
      int lastHttpIndex = profileImg.lastIndexOf('http://');
      int lastIndex = lastHttpsIndex > lastHttpIndex ? lastHttpsIndex : lastHttpIndex;
      if (lastIndex > 0) {
        profileImg = profileImg.substring(lastIndex);
        await prefs.setString("profile_image", profileImg);
      }
    }
    storedProfile = profileImg;
    
    // ✅ CRASH FIX: Check mounted before setState
    if (!mounted) return;
    setState(() {});
  }

  void _detectChanges() {
    setState(() => hasChanges = true);
  }

  Future pickImage() async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 70, // Reduced from 85 to 70 for faster upload
        maxWidth: 800,    // Reduced from 1920 to 800 for profile pictures
        maxHeight: 800,   // Reduced from 1920 to 800 for profile pictures
      );

      // ✅ CRITICAL: Check if user cancelled
      if (picked == null) {
        print("📷 User cancelled image selection");
        return;
      }

      // ✅ CRITICAL: Check mounted before async operations
      if (!mounted) return;

      if (kIsWeb) {
        webImage = await picked.readAsBytes();
      } else {
        imageFile = File(picked.path);
      }
      
      // ✅ CRITICAL: Check mounted again after async read
      if (!mounted) return;
      
      setState(() => hasChanges = true);
    } catch (e, stackTrace) {
      print("❌ Error picking image: $e");
      print("Stack trace: $stackTrace");
      
      if (!mounted) return;
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to select image: ${e.toString()}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  ImageProvider? avatarImage() {
    if (imageFile != null) return FileImage(imageFile!);
    if (webImage != null) return MemoryImage(webImage!);
    // Safe check for network image - avoid empty strings
    if (storedProfile.isNotEmpty && 
        storedProfile != "null" && 
        storedProfile != "NULL" &&
        storedProfile.trim().isNotEmpty &&
        (storedProfile.startsWith('http://') || storedProfile.startsWith('https://'))) {
      return NetworkImage(storedProfile);
    }
    return null;
  }

  Future<void> save() async {
    if (!hasChanges) return;

    setState(() => saving = true);

    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getString("user_id") ?? "";

      debugPrint("🚀 Starting profile update for user: $userId");
      debugPrint("📝 Fields: fullname=${nameController.text.trim()}, phone=${phoneController.text.trim()}");
      debugPrint("💰 GCash: number=${gcashNumberController.text.trim()}, name=${gcashNameController.text.trim()}");
      debugPrint("🖼️ Image file: ${imageFile != null}, Web image: ${webImage != null}");

      var req = http.MultipartRequest("POST", Uri.parse(widget.api));
      req.fields["user_id"] = userId;
      req.fields["fullname"] = nameController.text.trim();
      req.fields["phone"] = phoneController.text.trim();
      req.fields["address"] = addressController.text.trim();
      req.fields["gcash_number"] = gcashNumberController.text.trim();
      req.fields["gcash_name"] = gcashNameController.text.trim();

      if (imageFile != null) {
        debugPrint("📤 Adding image file: ${imageFile!.path}");
        req.files.add(await http.MultipartFile.fromPath("profile_image", imageFile!.path));
      }

      if (kIsWeb && webImage != null) {
        debugPrint("📤 Adding web image (${webImage!.length} bytes)");
        req.files.add(http.MultipartFile.fromBytes(
          "profile_image",
          webImage!,
          filename: "profile$userId.png",
        ));
      }

      debugPrint("⏱️ Sending request with 30 second timeout...");
      final res = await req.send().timeout(
        const Duration(seconds: 30),
        onTimeout: () {
          throw Exception('Request timed out after 30 seconds. Please check your internet connection.');
        },
      );
      
      debugPrint("✅ Response received in: ${DateTime.now()}");
      final responseBody = await res.stream.bytesToString();
    
    // Debug logging
    debugPrint("📤 Update Profile Response Status: ${res.statusCode}");
    debugPrint("📤 Update Profile Response Body: $responseBody");
    
    final json = jsonDecode(responseBody);

    if (json["success"] == true) {
      await prefs.setString("fullname", json["user"]["fullname"]);
      await prefs.setString("phone", json["user"]["phone"]);
      await prefs.setString("address", json["user"]["address"]);
      await prefs.setString("gcash_number", json["user"]["gcash_number"] ?? "");
      await prefs.setString("gcash_name", json["user"]["gcash_name"] ?? "");
      
      // Handle profile image URL correctly
      String profileImagePath = json["user"]["profile_image"] ?? "";
      String profileImageUrl = "";
      
      debugPrint("🖼️ Profile image path from server: $profileImagePath");
      
      if (profileImagePath.isNotEmpty && profileImagePath != "null") {
        // If it's already a full URL (Google, Facebook, etc.), use it as-is
        if (profileImagePath.startsWith('http://') || profileImagePath.startsWith('https://')) {
          profileImageUrl = profileImagePath;
          debugPrint("✅ Using full URL: $profileImageUrl");
        } else {
          // Otherwise, prepend the uploads URL (it already contains /profile_images/)
          profileImageUrl = profileImagePath;
          debugPrint("✅ Using server-provided path: $profileImageUrl");
        }
      }
      
      await prefs.setString("profile_image", profileImageUrl);
      
      // Update the UI immediately
      storedProfile = profileImageUrl;
      imageFile = null;
      webImage = null;

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Profile Updated 🎉")),
      );

      Navigator.pop(context, true);
    } else {
      // Show error message from server
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(json["message"] ?? "Failed to update profile"),
          backgroundColor: Colors.red,
        ),
      );
    }

    } catch (e) {
      debugPrint("❌ Error saving profile: $e");
      
      if (!mounted) return;
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to save: ${e.toString()}'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 5),
        ),
      );
    } finally {
      // ✅ CRASH FIX: Check mounted before setState
      if (!mounted) return;
      setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade100,

      appBar: AppBar(
        elevation: 0,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        foregroundColor: Theme.of(context).iconTheme.color,


        title: const Text("Edit Profile",
            style: TextStyle(fontWeight: FontWeight.bold)),
        centerTitle: true,
      ),

      body: FadeTransition(
        opacity: fadeAnimation,
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 25, vertical: 10),
          child: Column(
            children: [
              const SizedBox(height: 20),

              ScaleTransition(
                scale: scaleAnimation,
                child: Stack(
                  alignment: Alignment.bottomRight,
                  children: [
                    CircleAvatar(
                      radius: 75,
                      backgroundColor: Colors.grey.shade300,
                      backgroundImage: avatarImage(),
                      child: avatarImage() == null
                          ? const Icon(Icons.person, size: 70, color: Colors.black45)
                          : null,
                    ),
                    GestureDetector(
                      onTap: pickImage,
                      child: CircleAvatar(
                        radius: 22,
                         backgroundColor: Theme.of(context).iconTheme.color,




                        child: const Icon(Icons.camera_alt,
                            color: Colors.white, size: 19),
                      ),
                    )
                  ],
                ),
              ),

              const SizedBox(height: 40),

              _buildField("Full Name", nameController),
              _buildField("Phone Number", phoneController,
                  inputType: TextInputType.phone),
              _buildField("Address", addressController),

              const SizedBox(height: 30),

              // GCash Section Header
              Row(
                children: [
                  Icon(Icons.account_balance_wallet, color: Colors.blue, size: 24),
                  const SizedBox(width: 10),
                  Text(
                    "GCash Payout Settings",
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Colors.blue.shade800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.blue.shade700, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        "Required for receiving payouts from your vehicle rentals",
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.blue.shade900,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 15),

              _buildField("GCash Number", gcashNumberController,
                  inputType: TextInputType.phone,
                  hintText: "09XX XXX XXXX",
                  maxLength: 11),
              _buildField("GCash Account Name", gcashNameController,
                  hintText: "Name as shown in GCash"),

              const SizedBox(height: 100),
            ],
          ),
        ),
      ),

      bottomNavigationBar: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        padding: const EdgeInsets.all(20),
        child: ElevatedButton(
          onPressed: (!saving && hasChanges) ? save : null,
          style: ElevatedButton.styleFrom(
            backgroundColor:
                (!saving && hasChanges) ? Colors.black : Colors.grey,
            minimumSize: const Size(double.infinity, 55),
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12)),
          ),
          child: saving
              ? const CircularProgressIndicator(color: Colors.white)
              : const Text("Save Changes",
                  style: TextStyle(fontWeight: FontWeight.w600)),
        ),
      ),
    );
  }

  Widget _buildField(String label, TextEditingController controller,
      {TextInputType inputType = TextInputType.text, 
       String? hintText,
       int? maxLength}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: TextField(
        controller: controller,
        keyboardType: inputType,
        maxLength: maxLength,
        decoration: InputDecoration(
          labelText: label,
          hintText: hintText,
          floatingLabelBehavior: FloatingLabelBehavior.auto,
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14)),
          counterText: maxLength != null ? "" : null, // Hide counter
        ),
      ),
    );
  }
}
