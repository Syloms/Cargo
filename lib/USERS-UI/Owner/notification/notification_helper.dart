// Stub file for NotificationHelper
import 'notification_model.dart';

class NotificationHelper {
  static Map<String, List<NotificationModel>> groupByDate(
    List<NotificationModel> notifications,
  ) {
    final grouped = <String, List<NotificationModel>>{};
    for (final n in notifications) {
      final now = DateTime.now();
      final diff = now.difference(n.createdAt);
      String key;
      if (diff.inDays == 0) {
        key = 'Today';
      } else if (diff.inDays == 1) {
        key = 'Yesterday';
      } else {
        key = '${diff.inDays} days ago';
      }
      grouped.putIfAbsent(key, () => []).add(n);
    }
    return grouped;
  }
}
