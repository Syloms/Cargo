// Stub file for NotificationIcon
import 'package:flutter/material.dart';

class NotificationIcon extends StatelessWidget {
  final int? userId;
  final VoidCallback? onTap;

  const NotificationIcon({
    super.key,
    this.userId,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: const Icon(Icons.notifications_outlined),
      onPressed: onTap,
    );
  }
}
