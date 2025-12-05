import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'screens/onboarding_screen/onboarding.dart';
import 'widgets/shared/verify_popup.dart';

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
      title: 'CarGo Rentals',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        primarySwatch: Colors.blue,
        fontFamily: 'Plus Jakarta Sans',
        scaffoldBackgroundColor: Colors.white,
      ),
      home: const OnboardingWrapper(),
    );
  }
}

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
    return const OnboardingScreen();
  }
}