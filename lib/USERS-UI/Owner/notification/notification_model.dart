// Stub file for NotificationModel
import 'package:flutter/material.dart';

class NotificationModel {
  final int id;
  final String title;
  final String message;
  final String type;
  final String readStatus;
  final DateTime createdAt;

  const NotificationModel({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.readStatus,
    required this.createdAt,
  });

  bool get isUnread => readStatus != 'read';

  String get formattedTime {
    final now = DateTime.now();
    final diff = now.difference(createdAt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inHours < 1) return '${diff.inMinutes}m ago';
    if (diff.inDays < 1) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }

  Color get color {
    switch (type) {
      case 'booking': return Colors.blue;
      case 'payment': return Colors.green;
      case 'alert': return Colors.red;
      default: return Colors.grey;
    }
  }

  IconData get icon {
    switch (type) {
      case 'booking': return Icons.book_online;
      case 'payment': return Icons.payment;
      case 'alert': return Icons.warning_amber;
      default: return Icons.notifications;
    }
  }

  NotificationModel copyWith({String? readStatus}) {
    return NotificationModel(
      id: id,
      title: title,
      message: message,
      type: type,
      readStatus: readStatus ?? this.readStatus,
      createdAt: createdAt,
    );
  }

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      type: json['type']?.toString() ?? 'general',
      readStatus: json['read_status']?.toString() ?? 'unread',
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ?? DateTime.now(),
    );
  }
}
