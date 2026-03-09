import 'dart:async';
import 'dart:convert';
import 'package:audioplayers/audioplayers.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:vibration/vibration.dart';
import 'package:http/http.dart' as http;
import 'package:google_fonts/google_fonts.dart';

import 'package:cargo/config/api_config.dart';
import 'package:cargo/services/network_resilience_service.dart';

class NotificationScreen extends StatefulWidget {
  final int userId;
  const NotificationScreen({super.key, required this.userId});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> with SingleTickerProviderStateMixin {
  bool _isLoading = true;
  bool _isSelectionMode = false;
  Set<int> _selectedIds = {};
  
  List<Map<String, dynamic>> _notifications = [];
  List<Map<String, dynamic>> _filteredNotifications = [];
  final Map<String, List<Map<String, dynamic>>> _grouped = {};

  final AudioPlayer player = AudioPlayer();
  int? _loadedUserId;
  
  Timer? _refreshTimer;
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();
  final _resilienceService = NetworkResilienceService();
  
  String _searchQuery = '';
  int _consecutiveFailures = 0;
  String _currentFilter = 'all';
  
  final List<String> _filters = ['all', 'unread', 'booking', 'payment', 'alert'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _filters.length, vsync: this);
    _tabController.addListener(_onTabChanged);
    _loadUserId();
    _listenToFCM();
    _startAutoRefresh();
  }

  void _onTabChanged() {
    // indexIsChanging is true during animation; index is already updated
    if (_tabController.indexIsChanging) return;

    setState(() {
      _currentFilter = _filters[_tabController.index];
      _applyFilters();
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _tabController.dispose();
    _searchController.dispose();
    player.dispose();
    super.dispose();
  }

  void _startAutoRefresh() {
    // Increased refresh interval to reduce load
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 30), // Changed from 15 to 30 seconds
      (_) async {
        // Only refresh if network is available
        if (await NetworkResilienceService.isNetworkAvailable()) {
          _loadInitialNotifications(silent: true);
        } else {
          debugPrint('📵 Skipping auto-refresh: No network');
        }
      },
    );
  }

  // ============== LOAD USER_ID ==============

  Future<void> _loadUserId() async {
    final prefs = await SharedPreferences.getInstance();
    final savedId = prefs.getString("user_id");

    if (savedId != null) {
      _loadedUserId = int.tryParse(savedId);
    }

    _loadedUserId ??= widget.userId;
    debugPrint("🔥 Loaded user_id: $_loadedUserId");

    _loadInitialNotifications();
  }

  String _formatNetworkError(Object e) {
    final base = GlobalApiConfig.baseUrl;

    if (e is TimeoutException) {
      return 'Request timed out. Base URL: $base. Check your connection and that the server is reachable.';
    }

    // http.ClientException typically means "Failed host lookup" / "Connection refused" etc.
    if (e is http.ClientException) {
      return 'Network error contacting API. Base URL: $base. Make sure your phone and server are on the same network, and the URL is correct.';
    }

    return 'Unexpected error: $e (Base URL: $base)';
  }

  // ============== LOAD NOTIFICATIONS ==============

  Future<void> _loadInitialNotifications({bool silent = false}) async {
    if (_loadedUserId == null) {
      debugPrint("❌ user_id is NULL");
      if (mounted) {
        setState(() => _isLoading = false);
      }
      return;
    }

    if (!silent && mounted) setState(() => _isLoading = true);

    final url = Uri.parse(
      "${GlobalApiConfig.getNotificationsEndpoint}?user_id=$_loadedUserId",
    );

    try {
      // Use resilient GET with circuit breaker
      final res = await _resilienceService.resilientGet(
        url,
        timeout: const Duration(seconds: 10),
        maxRetries: 2,
      );

      if (res == null) {
        // Network error or circuit breaker open
        _consecutiveFailures++;
        
        // Stop auto-refresh if too many consecutive failures
        if (_consecutiveFailures >= 3) {
          debugPrint('⚠️ Stopping auto-refresh due to consecutive failures');
          _refreshTimer?.cancel();
          
          if (!silent && mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  'Unable to connect to server. Pull down to retry.',
                  style: GoogleFonts.poppins(),
                ),
                backgroundColor: Colors.orange,
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 5),
                action: SnackBarAction(
                  label: 'Retry',
                  textColor: Colors.white,
                  onPressed: () {
                    _consecutiveFailures = 0;
                    _resilienceService.resetAllCircuitBreakers();
                    _startAutoRefresh();
                    _loadInitialNotifications();
                  },
                ),
              ),
            );
          }
        }
        
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
        return;
      }

      // Success - reset failure counter
      _consecutiveFailures = 0;

      final decoded = jsonDecode(res.body);

      if (decoded is! Map || decoded["status"] != "success") {
        if (mounted) {
          setState(() {
            _notifications = [];
            _grouped.clear();
            _isLoading = false;
          });
        }
        return;
      }

      final rawList = decoded["notifications"];
      final List<Map<String, dynamic>> safeList =
          (rawList is List) ? List<Map<String, dynamic>>.from(rawList) : [];

      if (mounted) {
        setState(() {
          _notifications = safeList;
          _applyFilters();
          _isLoading = false;
        });
      }
    } catch (e) {
      final msg = _formatNetworkError(e);
      debugPrint("❌ Notifications fetch failed: $msg");
      _consecutiveFailures++;

      // Only show snackbar on non-silent refreshes to avoid spamming.
      if (!silent && mounted && _consecutiveFailures <= 2) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg, style: GoogleFonts.poppins()),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 3),
          ),
        );
      }

      if (mounted) {
        setState(() {
          _notifications = [];
          _grouped.clear();
          _isLoading = false;
        });
      }
    }
  }

  // ============== APPLY FILTERS ==============

  void _applyFilters() {
    var filtered = _notifications;

    // Apply tab filter
    if (_currentFilter == 'unread') {
      filtered = filtered.where((n) {
        final isRead = n["isRead"];
        // If locally toggled, use that value
        if (isRead != null) return !(isRead as bool);
        // Otherwise use server read_status
        return (n["read_status"] ?? "unread") != "read";
      }).toList();
    } else if (_currentFilter != 'all') {
      // Filter by type: booking, payment, alert, etc.
      filtered = filtered.where((n) =>
        (n["type"] ?? "").toLowerCase() == _currentFilter.toLowerCase()
      ).toList();
    }

    // Apply search
    if (_searchQuery.isNotEmpty) {
      final query = _searchQuery.toLowerCase();
      filtered = filtered.where((n) =>
          (n["title"] ?? "").toString().toLowerCase().contains(query) ||
          (n["message"] ?? "").toString().toLowerCase().contains(query))
          .toList();
    }

    _filteredNotifications = filtered;
    _groupByDate();
  }

  // ============== GROUP BY DATE ==============

  void _groupByDate() {
    _grouped.clear();

    for (var n in _filteredNotifications) {
      String date = n["date"] ?? "Unknown";

      if (!_grouped.containsKey(date)) {
        _grouped[date] = [];
      }

      _grouped[date]!.add(n);
    }
  }

  // ============== REALTIME FCM LISTENER ==============

  void _listenToFCM() {
    FirebaseMessaging.onMessage.listen((message) {
      _playSound();
      _vibrate();

      Map<String, dynamic> newNotif = {
        "id": int.tryParse(message.data["id"] ?? "0") ?? 0,
        "title": message.notification?.title ?? "Notification",
        "message": message.notification?.body ?? "",
        "date": message.data["date"] ?? "Today",
        "time": message.data["time"] ?? "",
        "type": message.data["type"] ?? "info",
        "isRead": false
      };

      setState(() {
        _notifications.insert(0, newNotif);
        _applyFilters();
      });
    });
  }

  // ============== SOUND + VIBRATE ==============

  Future<void> _playSound() async {
    try {
      await player.play(AssetSource("notification_sound.mp3"));
    } catch (e) {
      debugPrint("Sound play error: $e");
    }
  }

  void _vibrate() async {
    final hasVibrator = await Vibration.hasVibrator();
    if (hasVibrator == true) {
      Vibration.vibrate(duration: 80);
    }
  }

  // ============== SELECTION MODE ==============

  void _toggleSelection(int id) {
    setState(() {
      if (_selectedIds.contains(id)) {
        _selectedIds.remove(id);
      } else {
        _selectedIds.add(id);
      }

      if (_selectedIds.isEmpty) {
        _isSelectionMode = false;
      }
    });
  }

  // ============== MARK AS READ ==============

  Future<void> _markAsRead(Map<String, dynamic> notification) async {
    final notificationId = notification["id"];
    
    if (_loadedUserId == null || notificationId == null) return;

    // Update locally first for immediate UI feedback
    setState(() {
      notification["isRead"] = true;
      _applyFilters();
    });

    // Update on backend
    try {
      final response = await http.post(
        Uri.parse("${GlobalApiConfig.apiUrl}/notifications/mark_as_read.php"),
        body: {
          'notification_id': notificationId.toString(),
          'user_id': _loadedUserId.toString(),
        },
      ).timeout(const Duration(seconds: 10));

      final data = jsonDecode(response.body);
      
      if (data['success'] != true) {
        // Revert if failed
        setState(() {
          notification["isRead"] = false;
        });
        debugPrint("Failed to mark as read: ${data['message']}");
      }
    } catch (e) {
      // Revert on error
      setState(() {
        notification["isRead"] = false;
      });
      debugPrint("Error marking as read: $e");
    }
  }

  // ============== MARK AS UNREAD ==============

  Future<void> _markAsUnread(Map<String, dynamic> notification) async {
    final notificationId = notification["id"];
    
    if (_loadedUserId == null || notificationId == null) return;

    // Update locally first for immediate UI feedback
    setState(() {
      notification["isRead"] = false;
      _applyFilters();
    });

    // Update on backend
    try {
      final response = await http.post(
        Uri.parse("${GlobalApiConfig.apiUrl}/notifications/mark_as_unread.php"),
        body: {
          'notification_id': notificationId.toString(),
          'user_id': _loadedUserId.toString(),
        },
      ).timeout(const Duration(seconds: 10));

      final data = jsonDecode(response.body);
      
      if (data['success'] != true) {
        // Revert if failed
        setState(() {
          notification["isRead"] = true;
        });
        debugPrint("Failed to mark as unread: ${data['message']}");
      }
    } catch (e) {
      // Revert on error
      setState(() {
        notification["isRead"] = true;
      });
      debugPrint("Error marking as unread: $e");
    }
  }

  // ============== MARK SELECTED AS READ ==============

  Future<void> _markSelectedAsRead() async {
    if (_selectedIds.isEmpty) return;

    if (_loadedUserId == null) return;

    // Update locally first
    setState(() {
      for (var notification in _notifications) {
        if (_selectedIds.contains(notification["id"] as int)) {
          notification["isRead"] = true;
        }
      }
      _applyFilters();
    });

    // Update on backend
    int successCount = 0;
    int failCount = 0;

    for (var notificationId in _selectedIds) {
      try {
        final response = await http.post(
          Uri.parse("${GlobalApiConfig.apiUrl}/notifications/mark_as_read.php"),
          body: {
            'notification_id': notificationId.toString(),
            'user_id': _loadedUserId.toString(),
          },
        ).timeout(const Duration(seconds: 10));

        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          successCount++;
        } else {
          failCount++;
        }
      } catch (e) {
        failCount++;
        debugPrint("Error marking notification $notificationId as read: $e");
      }
    }

    setState(() {
      _selectedIds.clear();
      _isSelectionMode = false;
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            failCount > 0 
              ? '$successCount marked as read, $failCount failed' 
              : 'Marked as read',
            style: GoogleFonts.poppins(),
          ),
          backgroundColor: failCount > 0 ? Colors.orange : Colors.green,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // ============== MARK SELECTED AS UNREAD ==============

  Future<void> _markSelectedAsUnread() async {
    if (_selectedIds.isEmpty) return;

    if (_loadedUserId == null) return;

    // Update locally first
    setState(() {
      for (var notification in _notifications) {
        if (_selectedIds.contains(notification["id"] as int)) {
          notification["isRead"] = false;
        }
      }
      _applyFilters();
    });

    // Update on backend
    int successCount = 0;
    int failCount = 0;

    for (var notificationId in _selectedIds) {
      try {
        final response = await http.post(
          Uri.parse("${GlobalApiConfig.apiUrl}/notifications/mark_as_unread.php"),
          body: {
            'notification_id': notificationId.toString(),
            'user_id': _loadedUserId.toString(),
          },
        ).timeout(const Duration(seconds: 10));

        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          successCount++;
        } else {
          failCount++;
        }
      } catch (e) {
        failCount++;
        debugPrint("Error marking notification $notificationId as unread: $e");
      }
    }

    setState(() {
      _selectedIds.clear();
      _isSelectionMode = false;
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            failCount > 0 
              ? '$successCount marked as unread, $failCount failed' 
              : 'Marked as unread',
            style: GoogleFonts.poppins(),
          ),
          backgroundColor: failCount > 0 ? Colors.orange : Colors.blue,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // ============== DELETE MULTIPLE ==============

  Future<void> _deleteSelected() async {
    if (_selectedIds.isEmpty) return;

    final deleteCount = _selectedIds.length;
    final confirmed = await _showOwnerStyleDeleteDialog(
      title: 'Delete Notification${deleteCount > 1 ? 's' : ''}',
      message: 'Are you sure you want to permanently delete $deleteCount notification(s)? This action cannot be undone.',
    );

    if (!confirmed) return;

    if (_loadedUserId == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: User ID not found', style: GoogleFonts.poppins()),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
      return;
    }

    int successCount = 0;
    int failCount = 0;
    final Set<int> successIds = {};

    // Delete each notification (Owner-like: only remove those confirmed deleted)
    for (var notificationId in _selectedIds) {
      try {
        final response = await http.post(
          Uri.parse("${GlobalApiConfig.apiUrl}/notifications/delete_user_notification.php"),
          body: {
            'notification_id': notificationId.toString(),
            'user_id': _loadedUserId.toString(),
          },
        ).timeout(const Duration(seconds: 10));

        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          successCount++;
          successIds.add(notificationId);
        } else {
          failCount++;
        }
      } catch (e) {
        debugPrint("❌ Delete Error for ID $notificationId: $e");
        failCount++;
      }
    }

    if (!mounted) return;

    setState(() {
      _notifications.removeWhere((n) => successIds.contains(n["id"] as int));
      _selectedIds.clear();
      _isSelectionMode = false;
      _applyFilters();
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            failCount > 0
                ? '$successCount notification(s) deleted, $failCount failed'
                : '$successCount notification(s) deleted',
            style: GoogleFonts.poppins(),
          ),
          backgroundColor: failCount > 0
              ? (successCount > 0 ? Colors.orange : Colors.red)
              : Colors.green,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // ============== ARCHIVE MULTIPLE ==============

  Future<void> _archiveSelected() async {
    if (_selectedIds.isEmpty) return;

    final archiveCount = _selectedIds.length;

    setState(() {
      _notifications.removeWhere((n) => _selectedIds.contains(n["id"] as int));
      _selectedIds.clear();
      _isSelectionMode = false;
      _applyFilters();
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('$archiveCount notification(s) archived', style: GoogleFonts.poppins()),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // ============== OPEN DETAIL ==============

  void _openNotificationDetail(Map<String, dynamic> notification) async {
    if (_isSelectionMode) {
      _toggleSelection(notification["id"] ?? 0);
      return;
    }

    await _markAsRead(notification);

    if (mounted) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => NotificationDetailScreen(
            notification: notification,
            onDelete: () async {
              // Delete directly from detail screen
              // Don't pop here - let the detail screen handle navigation
              await _deleteFromDetailScreen(notification);
            },
          ),
        ),
      );
      
      // If deleted, just refresh the list (already updated by _deleteFromDetailScreen)
      // No need to do anything else - we stay on notification screen
    }
  }
  
  // Delete from detail screen without showing confirmation (already confirmed in menu)
  Future<void> _deleteFromDetailScreen(Map<String, dynamic> notification) async {
    final notificationId = notification["id"];
    
    if (_loadedUserId == null || notificationId == null) return;

    // Call backend API to delete notification
    try {
      final response = await http.post(
        Uri.parse("${GlobalApiConfig.apiUrl}/notifications/delete_user_notification.php"),
        body: {
          'notification_id': notificationId.toString(),
          'user_id': _loadedUserId.toString(),
        },
      ).timeout(const Duration(seconds: 10));

      debugPrint("📥 Delete Response: ${response.body}");

      final data = jsonDecode(response.body);

      if (data['success'] == true) {
        // Remove from local state after successful deletion
        if (mounted) {
          setState(() {
            _notifications.remove(notification);
            _applyFilters();
          });
        }
      }
    } catch (e) {
      debugPrint("❌ Delete Error: $e");
    }
  }

  // ============== SHOW CONFIRM DIALOG ==============

  // Owner-style delete confirmation dialog (keeps renter consistent with owner UX)
  Future<bool> _showOwnerStyleDeleteDialog({required String title, required String message}) async {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            backgroundColor: Theme.of(context).scaffoldBackgroundColor,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            title: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(Icons.delete_outline_rounded, color: Colors.red.shade600, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: GoogleFonts.poppins(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ),
              ],
            ),
            content: Text(
              message,
              style: GoogleFonts.poppins(fontSize: 14, color: Colors.grey.shade700, height: 1.5),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(
                  'Cancel',
                  style: GoogleFonts.poppins(
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                  ),
                ),
              ),
              ElevatedButton(
                onPressed: () => Navigator.of(context).pop(true),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red.shade600,
                  foregroundColor: isDark ? const Color(0xFF1E1E1E) : Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text('Delete', style: GoogleFonts.poppins(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
            ],
          ),
        ) ??
        false;
  }

  // ============== SHOW SEARCH DIALOG ==============

  void _showSearchBar() {
    showDialog(
      context: context,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        final colors = Theme.of(context).colorScheme;

        return AlertDialog(
          backgroundColor: isDark ? colors.surface : Colors.white,
          title: Text('Search Notifications', style: GoogleFonts.poppins(fontWeight: FontWeight.w700)),
          content: TextField(
            controller: _searchController,
            decoration: InputDecoration(
              hintText: 'Enter search term...',
              prefixIcon: const Icon(Icons.search),
              filled: true,
              fillColor: isDark ? colors.surfaceContainerHighest : Colors.grey[100],
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none,
              ),
            ),
            onChanged: (value) {
              setState(() {
                _searchQuery = value;
                _applyFilters();
              });
            },
          ),
          actions: [
            TextButton(
              onPressed: () {
                setState(() {
                  _searchQuery = '';
                  _searchController.clear();
                  _applyFilters();
                });
                Navigator.pop(context);
              },
              child: Text('Clear', style: GoogleFonts.poppins()),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context),
              style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
              child: Text('Done', style: GoogleFonts.poppins(color: Colors.white)),
            ),
          ],
        );
      },
    );
  }

  // ============== GET ICON BY TYPE ==============

  IconData _getIconByType(String type) {
    switch (type.toLowerCase()) {
      case 'booking':
        return Icons.calendar_month_outlined;
      case 'payment':
        return Icons.account_balance_wallet_outlined;
      case 'alert':
        return Icons.info_outline;
      case 'success':
        return Icons.verified_outlined;
      case 'message':
        return Icons.chat_bubble_outline_rounded;
      default:
        return Icons.notifications_outlined;
    }
  }

  Color _getColorByType(String type) {
    switch (type.toLowerCase()) {
      case 'booking':
        return Colors.blue;
      case 'payment':
        return Colors.green;
      case 'alert':
        return Colors.orange;
      case 'success':
        return Colors.green;
      case 'message':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  // ============== BUILD WIDGET ==============

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;
    final unreadCount = _notifications.where((n) {
      if (n["isRead"] != null) return !(n["isRead"] as bool);
      return (n["read_status"] ?? "unread") != "read";
    }).length;

    return Scaffold(
      backgroundColor: isDark ? colors.surface : Colors.grey[50],
      appBar: _buildAppBar(isDark, colors, unreadCount),
      body: _isLoading ? _buildLoading(colors) : (_grouped.isEmpty ? _buildEmptyState(isDark, colors) : _buildNotificationsList()),
      bottomNavigationBar: _isSelectionMode ? _buildSelectionBar() : null,
    );
  }

  PreferredSizeWidget _buildAppBar(bool isDark, ColorScheme colors, int unreadCount) {
    return AppBar(
      backgroundColor: isDark ? colors.surface : Colors.white,
      elevation: 0,
      leading: _isSelectionMode
          ? IconButton(
              icon: Icon(Icons.close, color: isDark ? colors.onSurface : Colors.black),
              onPressed: () => setState(() {
                _isSelectionMode = false;
                _selectedIds.clear();
              }),
            )
          : IconButton(
              icon: Icon(Icons.arrow_back, color: isDark ? colors.onSurface : Colors.black),
              onPressed: () => Navigator.pop(context),
            ),
      title: _isSelectionMode
          ? Text(
              '${_selectedIds.length} selected',
              style: GoogleFonts.poppins(color: isDark ? colors.onSurface : Colors.black, fontSize: 16, fontWeight: FontWeight.w600),
            )
          : Text(
              'Notifications',
              style: GoogleFonts.poppins(
                color: isDark ? colors.onSurface : Colors.black,
                fontWeight: FontWeight.bold,
                fontSize: 18,
              ),
            ),
      actions: [
        if (!_isSelectionMode) ...[
          IconButton(
            icon: Icon(Icons.search, color: isDark ? colors.onSurface : Colors.black),
            onPressed: _showSearchBar,
          ),
          if (unreadCount > 0)
            TextButton(
              onPressed: () async {
                if (_loadedUserId == null) return;

                // Update locally first
                setState(() {
                  for (var n in _notifications) {
                    n["isRead"] = true;
                  }
                });

                // Update on backend
                try {
                  final response = await http.post(
                    Uri.parse("${GlobalApiConfig.apiUrl}/notifications/update_all.php"),
                    body: {
                      'user_id': _loadedUserId.toString(),
                    },
                  ).timeout(const Duration(seconds: 10));

                  final data = jsonDecode(response.body);
                  
                  if (data['success'] == true) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text('All notifications marked as read', style: GoogleFonts.poppins()),
                          backgroundColor: Colors.green,
                          behavior: SnackBarBehavior.floating,
                        ),
                      );
                    }
                  } else {
                    debugPrint("Failed to mark all as read: ${data['message']}");
                  }
                } catch (e) {
                  debugPrint("Error marking all as read: $e");
                }
              },
              child: Text(
                'Mark all read',
                style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600, color: isDark ? colors.onSurface : Colors.black),
              ),
            ),
        ],
        if (_isSelectionMode) ...[
          IconButton(
            icon: Icon(Icons.select_all, color: isDark ? colors.onSurface : Colors.black),
            onPressed: () => setState(() {
              if (_selectedIds.length == _filteredNotifications.length) {
                _selectedIds.clear();
              } else {
                _selectedIds = _filteredNotifications.map((n) => (n["id"] as int? ?? 0)).toSet();
              }
            }),
          ),
        ],
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(48),
        child: _buildFilterTabs(isDark, colors),
      ),
    );
  }

  Widget _buildFilterTabs(bool isDark, ColorScheme colors) {
    return Container(
      color: isDark ? colors.surface : Colors.white,
      child: TabBar(
        controller: _tabController,
        isScrollable: true,
        labelColor: isDark ? colors.onSurface : Colors.black,
        unselectedLabelColor: isDark ? colors.onSurfaceVariant : Colors.grey,
        indicatorColor: isDark ? colors.onSurface : Colors.black,
        tabs: _filters.map((filter) {
          final count = filter == 'all'
              ? _notifications.length
              : filter == 'unread'
                  ? _notifications.where((n) {
                      if (n["isRead"] != null) return !(n["isRead"] as bool);
                      return (n["read_status"] ?? "unread") != "read";
                    }).length
                  : _notifications.where((n) => (n["type"] ?? "").toLowerCase() == filter).length;

          return Tab(
            child: Row(
              children: [
                Text(
                  filter.toUpperCase(),
                  style: GoogleFonts.poppins(fontSize: 12, fontWeight: FontWeight.w600),
                ),
                if (count > 0) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(10)),
                    child: Text('$count', style: GoogleFonts.poppins(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                ],
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildLoading(ColorScheme colors) {
    return Center(
      child: CircularProgressIndicator(color: colors.primary),
    );
  }

  Widget _buildEmptyState(bool isDark, ColorScheme colors) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.notifications_off, size: 64, color: colors.outline),
          const SizedBox(height: 16),
          Text(
            'No notifications',
            style: GoogleFonts.poppins(fontSize: 16, color: isDark ? colors.onSurfaceVariant : Colors.grey[600]),
          ),
        ],
      ),
    );
  }

  Widget _buildNotificationsList() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return RefreshIndicator(
      onRefresh: _loadInitialNotifications,
      color: colors.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _grouped.length,
        itemBuilder: (context, index) {
          final entry = _grouped.entries.elementAt(index);
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Text(
                  entry.key,
                  style: GoogleFonts.poppins(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: isDark ? colors.onSurfaceVariant : Colors.grey[700],
                  ),
                ),
              ),
              ...entry.value.map((notification) => _buildNotificationCard(notification)),
            ],
          );
        },
      ),
    );
  }

  Widget _buildNotificationCard(Map<String, dynamic> notification) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;
    final isSelected = _selectedIds.contains(notification["id"] as int);
    final isRead = notification["isRead"] ?? false;
    final type = notification["type"] ?? "info";
    final icon = _getIconByType(type);
    final color = _getColorByType(type);

    return Dismissible(
      key: Key('notification_${notification["id"]}'),
      background: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: Colors.green,
          borderRadius: BorderRadius.circular(12),
        ),
        alignment: Alignment.centerLeft,
        padding: const EdgeInsets.only(left: 20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(isRead ? Icons.markunread : Icons.done_all, color: Colors.white),
            const SizedBox(height: 4),
            Text(
              isRead ? 'Unread' : 'Read',
              style: GoogleFonts.poppins(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
      secondaryBackground: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: Colors.red,
          borderRadius: BorderRadius.circular(12),
        ),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.delete, color: Colors.white),
            const SizedBox(height: 4),
            Text(
              'Delete',
              style: GoogleFonts.poppins(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
      confirmDismiss: (direction) async {
        if (direction == DismissDirection.startToEnd) {
          // Swipe right - toggle read/unread
          if (isRead) {
            await _markAsUnread(notification);
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    'Marked as unread',
                    style: GoogleFonts.poppins(),
                  ),
                  backgroundColor: Colors.blue,
                  behavior: SnackBarBehavior.floating,
                  duration: const Duration(seconds: 2),
                ),
              );
            }
          } else {
            await _markAsRead(notification);
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    'Marked as read',
                    style: GoogleFonts.poppins(),
                  ),
                  backgroundColor: Colors.green,
                  behavior: SnackBarBehavior.floating,
                  duration: const Duration(seconds: 2),
                ),
              );
            }
          }
          return false; // Don't dismiss, just toggle
        } else if (direction == DismissDirection.endToStart) {
          // Swipe left - delete (ask for confirmation)
          final confirmed = await _showOwnerStyleDeleteDialog(
            title: 'Delete Notification',
            message: 'Are you sure you want to permanently delete this notification? This action cannot be undone.',
          );
          
          if (confirmed) {
            // Delete via API
            final notificationId = notification["id"];
            
            if (_loadedUserId == null || notificationId == null) return false;

            try {
              // Debug logging
              debugPrint("🔍 Attempting to delete notification...");
              debugPrint("🔍 Notification ID: $notificationId");
              debugPrint("🔍 User ID: $_loadedUserId");
              debugPrint("🔍 API URL: ${GlobalApiConfig.apiUrl}/notifications/delete_user_notification.php");
              
              final response = await http.post(
                Uri.parse("${GlobalApiConfig.apiUrl}/notifications/delete_user_notification.php"),
                body: {
                  'notification_id': notificationId.toString(),
                  'user_id': _loadedUserId.toString(),
                },
              ).timeout(const Duration(seconds: 10));

              debugPrint("📥 Response Status: ${response.statusCode}");
              debugPrint("📥 Response Body: ${response.body}");

              final data = jsonDecode(response.body);

              debugPrint("✅ Parsed response data: $data");
              
              if (data['success'] == true) {
                // Successfully deleted
                debugPrint("✅ Delete successful!");
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Notification deleted', style: GoogleFonts.poppins()),
                      backgroundColor: Colors.green,
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                }
                return true; // Allow dismissal
              } else {
                // Failed to delete
                debugPrint("❌ Delete failed: ${data['message']}");
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(data['message'] ?? 'Failed to delete notification', style: GoogleFonts.poppins()),
                      backgroundColor: Colors.red,
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                }
                return false; // Don't dismiss
              }
            } catch (e) {
              debugPrint("❌ Delete Error: $e");
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Error deleting notification', style: GoogleFonts.poppins()),
                    backgroundColor: Colors.red,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
              return false; // Don't dismiss on error
            }
          }
          return false; // User cancelled
        }
        return false;
      },
      onDismissed: (direction) {
        if (direction == DismissDirection.endToStart) {
          // Remove from local state (already deleted on backend in confirmDismiss)
          setState(() {
            _notifications.remove(notification);
            _applyFilters();
          });
        }
      },
      child: GestureDetector(
        onTap: () => _openNotificationDetail(notification),
        onLongPress: () {
          setState(() {
            _isSelectionMode = true;
            _toggleSelection(notification["id"] ?? 0);
          });
        },
        child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: isDark ? (isSelected ? colors.primaryContainer : colors.surface) : (isSelected ? Colors.blue.shade50 : Colors.white),
            borderRadius: BorderRadius.circular(12),
           
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.05),
                blurRadius: 4,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: ListTile(
          contentPadding: const EdgeInsets.all(12),
          leading: _isSelectionMode
              ? Checkbox(
                  value: isSelected,
                  activeColor: colors.primary,
                  checkColor: colors.onPrimary,
                  onChanged: (_) => _toggleSelection(notification["id"] ?? 0),
                )
              : Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, color: color),
                ),
          title: Text(
            notification["title"] ?? "Notification",
            style: GoogleFonts.poppins(
              fontWeight: isRead ? FontWeight.w600 : FontWeight.bold,
              fontSize: 14,
              color: isDark ? colors.onSurface : Colors.black,
            ),
          ),
          subtitle: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                notification["message"] ?? "",
                style: GoogleFonts.poppins(fontSize: 12, color: isDark ? colors.onSurfaceVariant : Colors.grey[600]),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              Text(
                notification["time"] ?? "",
                style: GoogleFonts.poppins(
                  fontSize: 10,
                  color: isDark ? colors.onSurfaceVariant : Colors.grey[500],
                ),
              ),
            ],
          ),
          trailing: !isRead
              ? Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: Colors.blue,
                    shape: BoxShape.circle,
                  ),
                )
              : null,
          ),
        ),
      ),
    );
  }

  Widget _buildSelectionBar() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: isDark ? colors.surface : Colors.white,
        boxShadow: [
          BoxShadow(
            color: isDark ? Colors.black.withValues(alpha: 0.6) : Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // First row: Mark as Read/Unread
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _markSelectedAsRead,
                  icon: const Icon(Icons.done_all, size: 18),
                  label: const Text('Mark Read', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _markSelectedAsUnread,
                  icon: const Icon(Icons.markunread, size: 18),
                  label: const Text('Mark Unread', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.blue,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          // Second row: Archive/Delete
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _archiveSelected,
                  icon: const Icon(Icons.archive, size: 18),
                  label: const Text('Archive', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.orange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _deleteSelected,
                  icon: const Icon(Icons.delete, size: 18),
                  label: const Text('Delete', style: TextStyle(fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ============== NOTIFICATION DETAIL SCREEN ==============

class NotificationDetailScreen extends StatefulWidget {
  final Map<String, dynamic> notification;
  final VoidCallback onDelete;

  const NotificationDetailScreen({
    super.key,
    required this.notification,
    required this.onDelete,
  });

  @override
  State<NotificationDetailScreen> createState() => _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  late Map<String, dynamic> notification;

  @override
  void initState() {
    super.initState();
    notification = widget.notification;
  }

  IconData _getIconByType(String type) {
    switch (type.toLowerCase()) {
      case 'booking':
        return Icons.calendar_month_outlined;
      case 'payment':
        return Icons.account_balance_wallet_outlined;
      case 'alert':
        return Icons.info_outline;
      case 'success':
        return Icons.verified_outlined;
      case 'message':
        return Icons.chat_bubble_outline_rounded;
      default:
        return Icons.notifications_outlined;
    }
  }

  Color _getColorByType(String type) {
    switch (type.toLowerCase()) {
      case 'booking':
        return Colors.blue;
      case 'payment':
        return Colors.green;
      case 'alert':
        return Colors.orange;
      case 'success':
        return Colors.green;
      case 'message':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;
    final type = notification["type"] ?? "info";
    final icon = _getIconByType(type);
    final color = _getColorByType(type);

    return Scaffold(
      backgroundColor: isDark ? colors.surface : Colors.grey[50],
      appBar: AppBar(
        backgroundColor: isDark ? colors.surface : Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: isDark ? colors.onSurface : Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Notification Details',
          style: GoogleFonts.poppins(
            color: isDark ? colors.onSurface : Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        actions: [
          PopupMenuButton<String>(
            icon: Icon(Icons.more_vert, color: isDark ? colors.onSurface : Colors.black),
            onSelected: (value) {
              if (value == 'delete') {
                widget.onDelete();
                Navigator.pop(context);
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'delete',
                child: Row(
                  children: [
                    const Icon(Icons.delete, size: 20, color: Colors.red),
                    const SizedBox(width: 12),
                    Text('Delete', style: GoogleFonts.poppins(color: Colors.red)),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header with icon
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: isDark ? colors.surface : Colors.white,
                borderRadius: BorderRadius.circular(16),
                
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: color.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(icon, color: color, size: 32),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              notification["title"] ?? "Notification",
                              style: GoogleFonts.poppins(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: isDark ? colors.onSurface : Colors.black,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              "${notification['date']} • ${notification['time']}",
                              style: GoogleFonts.poppins(
                                fontSize: 14,
                                color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Message section
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: isDark ? colors.surface : Colors.white,
                borderRadius: BorderRadius.circular(16),
               
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Message',
                    style: GoogleFonts.poppins(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    notification["message"] ?? "No message",
                    style: GoogleFonts.poppins(
                      fontSize: 16,
                      color: isDark ? colors.onSurface : Colors.black,
                      height: 1.6,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Details section
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: isDark ? colors.surface : Colors.white,
                borderRadius: BorderRadius.circular(16),
                
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Details',
                    style: GoogleFonts.poppins(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
                    ),
                  ),
                  const SizedBox(height: 16),
                  _buildDetailRow('Type', (notification["type"] as String?)?.toUpperCase() ?? "INFO", isDark, colors),
                  const SizedBox(height: 12),
                  _buildDetailRow('Status', (notification["isRead"] ?? false) ? 'Read' : 'Unread', isDark, colors),
                  const SizedBox(height: 12),
                  _buildDetailRow('Date & Time', "${notification['date']} • ${notification['time']}", isDark, colors),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Action buttons
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  widget.onDelete();
                  Navigator.pop(context);
                },
                icon: const Icon(Icons.delete),
                label: const Text('Delete Notification'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value, bool isDark, ColorScheme colors) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontSize: 13,
            color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
            fontWeight: FontWeight.w500,
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: isDark ? colors.surfaceContainerHighest : Colors.grey[100],
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            value,
            style: GoogleFonts.poppins(
              fontSize: 13,
              color: isDark ? colors.onSurface : Colors.black,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}