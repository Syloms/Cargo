import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:cargo/widgets/verify_popup.dart';
import 'package:cargo/screens/onboarding.dart'; // Import your existing onboarding

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await GetStorage.init();
  runApp(const CargoApp());
}

class CargoApp extends StatelessWidget {
  const CargoApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      home: const OnboardingWrapper(), // Use wrapper instead
    );
  }
}

// Wrapper to show verify popup before onboarding
class OnboardingWrapper extends StatefulWidget {
  const OnboardingWrapper({super.key});

  @override
  State<OnboardingWrapper> createState() => _OnboardingWrapperState();
}

class _OnboardingWrapperState extends State<OnboardingWrapper> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        VerifyPopup.showIfNotVerified(context);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return const OnboardingScreen(); // Your existing onboarding
  }
}