import 'package:flutter/material.dart';
import 'package:cargo/config/api_config.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'register_page.dart';
import 'package:cargo/widgets/loading_widgets.dart';
import 'package:firebase_database/firebase_database.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:cargo/USERS-UI/Renter/renters.dart';
import 'package:cargo/USERS-UI/Owner/owner_home_screen.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:cargo/Google/google_sign_in_service.dart';
import 'package:cargo/services/user_presence_service.dart';
import 'package:cargo/services/persistent_auth_service.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cargo/widgets/google_role_selection_dialog.dart';
// ❌ REMOVED: Facebook import

class CarGoApp extends StatelessWidget {
  const CarGoApp({super.key});

  void setUserStatus(String userId, bool online) {
    final ref = FirebaseDatabase.instance.ref("status/$userId");

    ref.set({
      "isOnline": online,
      "lastSeen": ServerValue.timestamp,
    });

    ref.onDisconnect().set({
      "isOnline": false,
      "lastSeen": ServerValue.timestamp,
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
  debugShowCheckedModeBanner: false,
  title: 'CarGo Login',
  theme: ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
  ),
  darkTheme: ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: Colors.blue,
      brightness: Brightness.dark,
    ),
  ),
  themeMode: ThemeMode.system,
  home: const LoginPage(),
);

  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final GoogleSignInService _googleSignInService = GoogleSignInService();
  // ❌ REMOVED: FacebookSignInService
  
  bool _obscurePassword = true;
  bool _rememberMe = false;
  bool _isGoogleSigningIn = false;
  // ❌ REMOVED: _isFacebookSigningIn

  @override
  void initState() {
    super.initState();
    _loadSavedCredentials();
    _setupNotifications();
  }

  Future<void> _setupNotifications() async {
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  void _loadSavedCredentials() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? email = prefs.getString('email');
    String? password = prefs.getString('password');
    bool remember = prefs.getBool('remember') ?? false;

    if (remember && email != null && password != null) {
      setState(() {
        _emailController.text = email;
        _passwordController.text = password;
        _rememberMe = true;
      });
    }
  }

  void _saveCredentials() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    if (_rememberMe) {
      await prefs.setString('email', _emailController.text);
      await prefs.setString('password', _passwordController.text);
      await prefs.setBool('remember', true);
    } else {
      await prefs.remove('email');
      await prefs.remove('password');
      await prefs.setBool('remember', false);
    }
  }

  void _login() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text.trim();

    if (email.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all fields.')),
      );
      return;
    }

    // Show loading indicator
    LoadingDialog.showSimple(context);

    _saveCredentials();

    final url = Uri.parse(GlobalApiConfig.loginEndpoint);

    debugPrint("Sending JSON -> email: $email, password: $password");

    try {
      debugPrint("LOGIN URL -> ${GlobalApiConfig.loginEndpoint}");

      final response = await http.post(
        url,
        headers: {"Content-Type": "application/json; charset=UTF-8"},
        body: jsonEncode({
          "email": email,
          "password": password,
        }),
      );

      debugPrint("Response status: ${response.statusCode}");
      debugPrint("Response body: ${response.body}");

      // ✅ Parse response for both success (200) and error responses (400, 404, etc.)
      final responseData = jsonDecode(response.body);

      if (response.statusCode == 200) {
        // ✅ FIX: Check for "success" field instead of "status"
        if (responseData["success"] == true) {
          // Extract the actual user data from the "data" field
          final data = responseData["data"];
          
          // ✅ NEW: Sign in to Firebase Auth with custom token system
          // This authenticates the user with Firebase so Realtime Database rules work
          await _signInToFirebaseAuth(data);
          
          // ✅ Save user session using PersistentAuthService
          final authService = PersistentAuthService();
          await authService.saveUserSession(data);

          String role = data["role"].toString().toLowerCase(); // Convert to lowercase for comparison

          // ✅ OPTIMIZATION: Navigate immediately, do Firebase operations in background
          // Close loading dialog
          if (mounted) LoadingDialog.hide(context);

          // Navigate to home screen immediately
          if (mounted) {
            if (role == "renter") {
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (context) => const HomeScreen()),
              );
            } else if (role == "owner") {
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (context) => OwnerHomeScreen()),
              );
            }
          }

          // ✅ Do Firebase operations in background (non-blocking)
          _updateFirebaseInBackground(data);
          
          // ✅ Initialize presence service for online status tracking
          _initializePresenceService();

          // Show success message
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(responseData["message"] ?? "Login successful")),
            );
          }
        } else {
          // ✅ Handle login failure with user-friendly messages
          if (mounted) LoadingDialog.hide(context);
          if (mounted) {
            String errorMessage = responseData["message"] ?? "Login failed.";
            
            // ✅ User-friendly error messages
            if (errorMessage.contains("Invalid email or password")) {
              errorMessage = "Wrong email or password";
            } else if (errorMessage.contains("Account suspended")) {
              errorMessage = "Account suspended";
            }
            
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(errorMessage),
                backgroundColor: Colors.red.shade600,
                duration: const Duration(seconds: 4),
              ),
            );
          }
        }
      } else if (response.statusCode == 400 || response.statusCode == 401 || response.statusCode == 403) {
        // ✅ Handle 400 (Bad Request), 401 (Unauthorized), 403 (Forbidden)
        // These are returned for suspended accounts, wrong credentials, etc.
        if (mounted) LoadingDialog.hide(context);
        if (mounted) {
          String errorMessage = responseData["message"] ?? "Login failed.";
          
          // ✅ User-friendly error messages
          if (errorMessage.contains("Invalid email or password")) {
            errorMessage = "Wrong email or password";
          } else if (errorMessage.contains("Account suspended")) {
            errorMessage = "Account suspended";
          } else if (errorMessage.contains("suspended")) {
            errorMessage = "Account suspended";
          }
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(errorMessage),
              backgroundColor: Colors.red.shade600,
              duration: const Duration(seconds: 4),
            ),
          );
        }
      } else if (response.statusCode == 404) {
        // ✅ Handle 404 specifically
        if (mounted) LoadingDialog.hide(context);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text("Account not found"),
              backgroundColor: Colors.red,
              duration: Duration(seconds: 4),
            ),
          );
        }
      } else {
        // ✅ Handle other server errors (500, 502, etc.)
        if (mounted) LoadingDialog.hide(context);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text("Server error: ${response.statusCode}"),
              backgroundColor: Colors.red.shade600,
            ),
          );
        }
      }
    } catch (e) {
      debugPrint("Error: $e");
      if (mounted) LoadingDialog.hide(context);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Error connecting to server.')),
        );
      }
    }
  }

  // ✅ NEW: Sign in to Firebase Auth anonymously but link to user ID
  Future<void> _signInToFirebaseAuth(Map<String, dynamic> userData) async {
    try {
      final FirebaseAuth auth = FirebaseAuth.instance;
      
      // Check if already signed in
      if (auth.currentUser != null) {
        debugPrint('🔐 Already signed in to Firebase Auth: ${auth.currentUser!.uid}');
        return;
      }
      
      // Sign in anonymously - this gives us Firebase Auth credentials
      // We'll use the user's email as a unique identifier
      debugPrint('🔐 Signing in to Firebase Auth anonymously...');
      
      try {
        // Try to sign in with email (if Firebase Auth email is enabled)
        // For now, use anonymous auth which always works
        final userCredential = await auth.signInAnonymously();
        debugPrint('✅ Firebase Auth sign-in successful: ${userCredential.user?.uid}');
        
        // Update display name to match user ID
        await userCredential.user?.updateDisplayName(userData['id'].toString());
        
      } catch (e) {
        debugPrint('⚠️ Firebase Auth error (non-critical): $e');
        // Continue even if this fails - app will still work
      }
    } catch (e) {
      debugPrint('❌ Firebase Auth sign-in error: $e');
      // Don't throw - let the app continue
    }
  }

  // ✅ NEW: Initialize presence service
  Future<void> _initializePresenceService() async {
    try {
      await UserPresenceService().initialize();
      debugPrint('✅ Presence service initialized after login');
    } catch (e) {
      debugPrint('❌ Error initializing presence service: $e');
    }
  }

  // ✅ NEW: Background Firebase operations (non-blocking)
  Future<void> _updateFirebaseInBackground(Map<String, dynamic> data) async {
    try {
      final userRef = FirebaseFirestore.instance.collection("users").doc(data["id"].toString());

      // Check if user exists
      final docSnapshot = await userRef.get();

      if (!docSnapshot.exists) {
        // Create new user
        await userRef.set({
          "uid": data["id"].toString(),
          "name": data["fullname"],               
          "avatar": data["profile_image"] ?? "",  
          "email": data["email"],
          "role": data["role"],
          "online": true,
          "createdAt": FieldValue.serverTimestamp(),
        });
        debugPrint("🔥 Firestore user CREATED in background");
      } else {
        // Update existing user - do both operations in parallel
        debugPrint("✔ Firestore user already exists → updating status");
        
        final futures = <Future>[];
        
        // Update user data
        futures.add(userRef.update({
          "online": true,
          "avatar": data["profile_image"] ?? "",
          "name": data["fullname"],
        }));

        // Get and update FCM token in parallel
        futures.add(
          FirebaseMessaging.instance.getToken().then((token) {
            if (token != null) {
              userRef.update({"fcm": token});
              debugPrint("📩 FCM Token Updated: $token");
            }
          }).catchError((e) {
            debugPrint("❌ Failed to save FCM Token: $e");
          })
        );

        // Wait for both operations to complete
        await Future.wait(futures);
      }
    } catch (e) {
      debugPrint("❌ Firebase background update error: $e");
      // Don't show error to user - this is background operation
    }
  }

  Future<void> _handleGoogleSignIn() async {
    setState(() => _isGoogleSigningIn = true);

    try {
      final result = await _googleSignInService.signInWithGoogle();

      if (result == null) {
        setState(() => _isGoogleSigningIn = false);
        return;
      }

      if (result.containsKey('error')) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Google Sign-In failed: ${result['error']}')),
        );
        setState(() => _isGoogleSigningIn = false);
        return;
      }

      if (result['isNewUser'] == true) {
        setState(() => _isGoogleSigningIn = false);
        _showRoleSelectionDialog(result);
      } else if (result['success'] == true && result['user'] != null) {
        final userData = result['user'];
        _navigateToHome(userData['role']);
      } else {
        // success: false with no error key (unexpected state)
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['error']?.toString() ?? 'Sign-in failed. Please try again.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Sign-in error: $e')),
      );
    } finally {
      setState(() => _isGoogleSigningIn = false);
    }
  }

  void _showRoleSelectionDialog(Map<String, dynamic> googleData) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return GoogleRoleSelectionDialog(
          googleData: googleData,
          onComplete: (role, municipality) async {
            Navigator.pop(context);
            await _completeGoogleRegistration(
              googleData,
              role,
              municipality,
            );
          },
          onCancel: () {
            Navigator.pop(context);
            _googleSignInService.signOut();
          },
        );
      },
    );
  }

  Future<void> _completeGoogleRegistration(
    Map<String, dynamic> googleData,
    String role,
    String municipality,
  ) async {
    setState(() => _isGoogleSigningIn = true);

    final result = await _googleSignInService.registerGoogleUser(
      email: googleData['email'],
      fullName: googleData['fullName'],
      role: role,
      municipality: municipality,
      photoUrl: googleData['photoUrl'],
      firebaseUid: googleData['firebaseUid'],
    );

    setState(() => _isGoogleSigningIn = false);

    if (!mounted) return;
    if (result != null && result['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Account created successfully!')),
      );
      _navigateToHome(role);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Registration failed. Please try again.')),
      );
    }
  }

  // ❌ REMOVED: All Facebook-related methods
  // - _handleFacebookSignIn()
  // - _showFacebookRoleSelectionDialog()
  // - _completeFacebookRegistration()

  void _navigateToHome(String role) {
    // Convert role to lowercase for comparison
    final normalizedRole = role.toLowerCase();
    
    if (normalizedRole == "renter") {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const HomeScreen()),
      );
    } else if (normalizedRole == "owner") {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => OwnerHomeScreen()),
      );
    }
  }

  Future<Map<String, dynamic>> _requestPasswordReset(String email) async {
    final url = Uri.parse(GlobalApiConfig.requestPasswordResetEndpoint);

    final response = await http
        .post(
          url,
          headers: {"Content-Type": "application/json; charset=UTF-8"},
          body: jsonEncode({"email": email}),
        )
        .timeout(GlobalApiConfig.apiTimeout);

    final decoded = jsonDecode(response.body);
    if (response.statusCode != 200 || decoded is! Map<String, dynamic>) {
      throw Exception('Server error: ${response.statusCode}');
    }

    if (decoded["success"] != true) {
      throw Exception(decoded["message"] ?? 'Request failed');
    }

    return decoded;
  }

  Future<Map<String, dynamic>> _resetPassword({
    required String email,
    required String code,
    required String newPassword,
  }) async {
    final url = Uri.parse(GlobalApiConfig.resetPasswordEndpoint);

    final response = await http
        .post(
          url,
          headers: {"Content-Type": "application/json; charset=UTF-8"},
          body: jsonEncode({
            "email": email,
            "code": code,
            "new_password": newPassword,
          }),
        )
        .timeout(GlobalApiConfig.apiTimeout);

    final decoded = jsonDecode(response.body);
    if (response.statusCode != 200 || decoded is! Map<String, dynamic>) {
      throw Exception('Server error: ${response.statusCode}');
    }

    if (decoded["success"] != true) {
      throw Exception(decoded["message"] ?? 'Reset failed');
    }

    return decoded;
  }

  void _showForgotPasswordDialog() {
    final emailController = TextEditingController(text: _emailController.text.trim());
    final codeController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();

    int step = 0; // 0=email, 1=code+new password
    bool isLoading = false;

    bool isValidEmail(String v) {
      final email = v.trim();
      return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email);
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setState) {
            Future<void> runRequest() async {
              final email = emailController.text.trim().toLowerCase();
              if (email.isEmpty || !isValidEmail(email)) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Please enter a valid email address.')),
                );
                return;
              }

              setState(() => isLoading = true);
              try {
                final res = await _requestPasswordReset(email);
                setState(() => step = 1);

                if (!mounted) return;
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(content: Text(res['message'] ?? 'Reset code sent to your email.')),
                );
              } catch (e) {
                if (!mounted) return;
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
                );
              } finally {
                if (mounted) setState(() => isLoading = false);
              }
            }

            Future<void> runResend() async {
              // Resend uses same request endpoint
              await runRequest();
            }

            Future<void> runReset() async {
              final email = emailController.text.trim().toLowerCase();
              final code = codeController.text.trim();
              final newPass = newPasswordController.text;
              final confirmPass = confirmPasswordController.text;

              if (email.isEmpty || !isValidEmail(email)) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Please go back and enter a valid email.')),
                );
                return;
              }

              if (code.length < 6) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Please enter the 6-digit code from your email.')),
                );
                return;
              }

              if (newPass.isEmpty || confirmPass.isEmpty) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Please enter and confirm your new password.')),
                );
                return;
              }

              if (newPass.length < 6) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Password must be at least 6 characters.')),
                );
                return;
              }

              if (newPass != confirmPass) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Passwords do not match.')),
                );
                return;
              }

              setState(() => isLoading = true);
              try {
                final res = await _resetPassword(email: email, code: code, newPassword: newPass);
                if (!mounted) return;
                if (dialogContext.mounted && Navigator.canPop(dialogContext)) Navigator.pop(dialogContext);

                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(content: Text(res['message'] ?? 'Password reset successful.')),
                );
              } catch (e) {
                if (!mounted) return;
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
                );
              } finally {
                if (mounted) setState(() => isLoading = false);
              }
            }

            final theme = Theme.of(context);
            final title = step == 0 ? 'Forgot Password' : 'Reset Password';

            final content = step == 0
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'We will send a 6-digit verification code to your email.',
                        style: theme.textTheme.bodyMedium?.copyWith(color: theme.hintColor),
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: emailController,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.send,
                        decoration: const InputDecoration(
                          labelText: 'Email address',
                          prefixIcon: Icon(Icons.email_outlined),
                        ),
                        onSubmitted: (_) {
                          if (!isLoading) runRequest();
                        },
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Tip: Check Spam/Junk if you don\'t see the email within a minute.',
                        style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
                      ),
                    ],
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Enter the 6-digit code we sent to:',
                        style: theme.textTheme.bodyMedium?.copyWith(color: theme.hintColor),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        emailController.text.trim(),
                        style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: codeController,
                        keyboardType: TextInputType.number,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Verification code',
                          prefixIcon: Icon(Icons.verified_outlined),
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: newPasswordController,
                        obscureText: true,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'New password',
                          prefixIcon: Icon(Icons.lock_outline),
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: confirmPasswordController,
                        obscureText: true,
                        textInputAction: TextInputAction.done,
                        decoration: const InputDecoration(
                          labelText: 'Confirm new password',
                          prefixIcon: Icon(Icons.lock_reset_outlined),
                        ),
                        onSubmitted: (_) {
                          if (!isLoading) runReset();
                        },
                      ),
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton(
                          onPressed: isLoading ? null : runResend,
                          child: const Text('Resend code'),
                        ),
                      ),
                    ],
                  );

            return AlertDialog(
              title: Row(
                children: [
                  Icon(step == 0 ? Icons.lock_outline : Icons.password, color: theme.colorScheme.primary),
                  const SizedBox(width: 10),
                  Expanded(child: Text(title)),
                ],
              ),
              content: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420, maxHeight: 500),
                child: SingleChildScrollView(
                  child: content,
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoading ? null : () => Navigator.pop(dialogContext),
                  child: const Text('Cancel'),
                ),
                if (step == 1)
                  TextButton(
                    onPressed: isLoading
                        ? null
                        : () {
                            setState(() {
                              step = 0;
                              codeController.clear();
                              newPasswordController.clear();
                              confirmPasswordController.clear();
                            });
                          },
                    child: const Text('Back'),
                  ),
                ElevatedButton(
                  onPressed: isLoading ? null : () => (step == 0 ? runRequest() : runReset()),
                  child: isLoading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(step == 0 ? 'Send code' : 'Reset password'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Image.asset(
                    'assets/cargo.png',
                    width: 40,
                    height: 40,
                  ),
                  const SizedBox(width: 10),
                  Text(
                    'CarGo',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).iconTheme.color,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 40),

              Text(
                'Welcome Back',
                style: TextStyle(
                  fontSize: 28,
                  color: Theme.of(context).iconTheme.color,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Ready to hit the road.',
                style: TextStyle(
                  fontSize: 16,
                  color: Theme.of(context).hintColor,
                ),
              ),
              const SizedBox(height: 30),

              TextField(
                controller: _emailController,
                style: TextStyle(color: Theme.of(context).iconTheme.color),
                decoration: InputDecoration(
                  hintText: 'Email/Phone Number',
                  hintStyle:  TextStyle(color: Theme.of(context).disabledColor, fontSize: 14),
                  filled: true,
                  fillColor: Theme.of(context).colorScheme.surface,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
              const SizedBox(height: 16),

              TextField(
                controller: _passwordController,
                obscureText: _obscurePassword,
                style:  TextStyle(color: Theme.of(context).iconTheme.color),
                decoration: InputDecoration(
                  hintText: 'Password',
                  hintStyle:  TextStyle(color: Theme.of(context).disabledColor, fontSize: 14),
                  filled: true,
                  fillColor: Theme.of(context).colorScheme.surface,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none,
                  ),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscurePassword ? Icons.visibility_off : Icons.visibility,
                      color: Theme.of(context).disabledColor,
                      size: 20,
                    ),
                    onPressed: () {
                      setState(() {
                        _obscurePassword = !_obscurePassword;
                      });
                    },
                  ),
                ),
              ),
              const SizedBox(height: 12),

              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: Checkbox(
                          value: _rememberMe,
                          onChanged: (value) {
                            setState(() => _rememberMe = value!);
                          },
                          activeColor: Theme.of(context).iconTheme.color,
                          checkColor: Theme.of(context).colorScheme.onPrimary,
                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                      ),
                      const SizedBox(width: 8),
                       Text(
                        'Remember Me',
                        style: TextStyle(color: Theme.of(context).iconTheme.color, fontSize: 13),
                      ),
                    ],
                  ),
                  TextButton(
                    onPressed: _showForgotPasswordDialog,
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(0, 0),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child:  Text(
                      'Forgot Password',
                      style: TextStyle(color: Theme.of(context).iconTheme.color, fontSize: 13),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),

              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _login,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Theme.of(context).colorScheme.onPrimary,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Login',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
              const SizedBox(height: 24),

              Row(
                children:  [
                  Expanded(child: Divider(color: Theme.of(context).dividerColor, thickness: 1)),
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Text(
                      'Or',
                      style: TextStyle(color: Theme.of(context).disabledColor, fontSize: 13),
                    ),
                  ),
                  Expanded(child: Divider(color: Theme.of(context).dividerColor, thickness: 1)),
                ],
              ),
              const SizedBox(height: 24),

              // ❌ REMOVED: Facebook Login Button

              // Google Login Button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: OutlinedButton(
                  onPressed: _isGoogleSigningIn ? null : _handleGoogleSignIn,
                  style: OutlinedButton.styleFrom(
                    side:  BorderSide(color: Theme.of(context).dividerColor, width: 1),
                    foregroundColor: Theme.of(context).iconTheme.color,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isGoogleSigningIn
                      ?  SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Theme.of(context).iconTheme.color,
                                ),

                        )
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Image.asset(
                              'assets/search.png',
                              width: 20,
                              height: 20,
                            ),
                            const SizedBox(width: 12),
                            const Flexible(
                              child: Text(
                                'Continue with Google',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                ),
              ),
              const SizedBox(height: 24),

              Center(
                child: Wrap(
                  alignment: WrapAlignment.center,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(
                      "Don't have an account? ",
                      style: TextStyle(color: Theme.of(context).hintColor, fontSize: 13),
                    ),
                    TextButton(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (context) => const RegisterPage()),
                        );
                      },
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        minimumSize: const Size(0, 0),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: Text(
                        'Sign Up',
                        style: TextStyle(
                          color: Theme.of(context).iconTheme.color,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}