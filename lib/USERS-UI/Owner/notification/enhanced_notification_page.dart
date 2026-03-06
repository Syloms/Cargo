import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'enhanced_notification_service.dart';
import 'notification_model.dart';
import 'notification_helper.dart';

class EnhancedNotificationPage extends StatefulWidget {
  final int userId;
  
  const EnhancedNotificationPage({
    super.key,
    required this.userId,
  });

  @override
  State<EnhancedNotificationPage> createState() => _EnhancedNotificationPageState();
}

class _EnhancedNotificationPageState extends State<EnhancedNotificationPage> 
    with SingleTickerProviderStateMixin {
  final EnhancedNotificationService _service = EnhancedNotificationService();
  
  List<NotificationModel> _notifications = [];
  List<NotificationModel> _filteredNotifications = [];
  
  bool _isLoading = true;
  bool _isSelectionMode = false;
  Set<int> _selectedIds = {};
  String _selectedFilter = 'all';
  String _searchQuery = '';
  
  Timer? _refreshTimer;
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();

  final List<String> _filters = ['all', 'unread', 'booking', 'payment', 'alert'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _filters.length, vsync: this);
    _loadNotifications();
    _startAutoRefresh();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _startAutoRefresh() {
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 15),
      (_) => _loadNotifications(silent: true),
    );
  }

  Future<void> _loadNotifications({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);

    try {
      // Always fetch all notifications — filtering is done client-side
      final result = await _service.fetchNotifications(
        userId: widget.userId,
      );

      if (result['success']) {
        setState(() {
          _notifications = result['notifications'];
          _applyFilters();
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('Error loading notifications: $e');
      if (!silent) setState(() => _isLoading = false);
    }
  }

  void _applyFilters() {
    var filtered = _notifications;

    // Apply tab filter client-side
    if (_selectedFilter == 'unread') {
      filtered = filtered.where((n) => n.isUnread).toList();
    } else if (_selectedFilter != 'all') {
      filtered = filtered.where((n) => n.type == _selectedFilter).toList();
    }

    // Apply search
    if (_searchQuery.isNotEmpty) {
      final query = _searchQuery.toLowerCase();
      filtered = filtered.where((n) =>
        n.title.toLowerCase().contains(query) ||
        n.message.toLowerCase().contains(query)
      ).toList();
    }

    _filteredNotifications = filtered;
  }

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

  /// Mark notification as read when opened
  Future<void> _markAsRead(NotificationModel notification) async {
    if (!notification.isUnread) return;

    await _service.markAsRead(notification.id.toString(), widget.userId);

    setState(() {
      final index = _notifications.indexWhere((n) => n.id == notification.id);
      if (index != -1) {
        _notifications[index] = notification.copyWith(readStatus: 'read');
      }
      _applyFilters();
    });
  }




  /// Navigate to notification detail
  void _openNotificationDetail(NotificationModel notification) async {
    // Mark as read
    await _markAsRead(notification);

    // Navigate to detail page
    if (mounted) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => NotificationDetailScreen(
            notification: notification,
            onDelete: () => _deleteNotification(notification.id),
            onArchive: () => _archiveNotification(notification.id),
          ),
        ),
      );
    }
  }

  /// Delete single notification
  Future<void> _deleteNotification(int id) async {
    final result = await _service.deleteMultiple([id], widget.userId);

    if (result['success']) {
      _showSnackBar('Notification deleted', Colors.green);
      setState(() {
        _notifications.removeWhere((n) => n.id == id);
        _applyFilters();
      });
    }
  }

  /// Archive single notification
  Future<void> _archiveNotification(int id) async {
    final result = await _service.archiveMultiple([id]);

    if (result['success']) {
      _showSnackBar('Notification archived', Colors.orange);
      setState(() {
        _notifications.removeWhere((n) => n.id == id);
        _applyFilters();
      });
    }
  }

  Future<void> _deleteSelected() async {
    if (_selectedIds.isEmpty) return;

    final confirmed = await _showConfirmDialog(
      'Delete ${_selectedIds.length} notification(s)?',
      'This action cannot be undone.',
    );

    if (confirmed) {
      final result = await _service.deleteMultiple(
        _selectedIds.toList(),
        widget.userId,
      );

      if (result['success']) {
        _showSnackBar(
          '${result['deleted_count']} notification(s) deleted',
          Colors.green,
        );
        setState(() {
          _notifications.removeWhere((n) => _selectedIds.contains(n.id));
          _selectedIds.clear();
          _isSelectionMode = false;
          _applyFilters();
        });
      }
    }
  }

  Future<void> _archiveSelected() async {
    if (_selectedIds.isEmpty) return;

    final result = await _service.archiveMultiple(_selectedIds.toList());

    if (result['success']) {
      _showSnackBar(
        '${result['archived_count']} notification(s) archived',
        Colors.orange,
      );
      setState(() {
        _notifications.removeWhere((n) => _selectedIds.contains(n.id));
        _selectedIds.clear();
        _isSelectionMode = false;
        _applyFilters();
      });
    }
  }

  Future<bool> _showConfirmDialog(String title, String message) async {
    return await showDialog<bool>(
      context: context,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        final colors = Theme.of(context).colorScheme;

        return AlertDialog(
          backgroundColor: isDark ? colors.surface : Colors.white,
          title: Text(
            title,
            style: GoogleFonts.poppins(
              fontWeight: FontWeight.w700,
              color: isDark ? colors.onSurface : Colors.black,
            ),
          ),
          content: Text(
            message,
            style: GoogleFonts.poppins(
              color: isDark ? colors.onSurfaceVariant : Colors.grey[700],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(
                'Cancel',
                style: GoogleFonts.poppins(
                  color: isDark ? colors.onSurfaceVariant : Colors.grey[700],
                ),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
              child: Text(
                'Confirm',
                style: GoogleFonts.poppins(color: Colors.white),
              ),
            ),
          ],
        );
      },
    ) ?? false;
  }

  void _showSnackBar(String message, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message, style: GoogleFonts.poppins()),
        backgroundColor: color,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: isDark ? colors.surface : Colors.grey[50],
      appBar: _buildAppBar(),
      body: _isLoading ? _buildLoading() : _buildContent(),
      bottomNavigationBar: _isSelectionMode ? _buildSelectionBar() : null,
    );
  }

  PreferredSizeWidget _buildAppBar() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return AppBar(
      backgroundColor: isDark ? colors.surface : Colors.white,
      elevation: 0,
      automaticallyImplyLeading: false,
      leading: _isSelectionMode
          ? IconButton(
              icon: Icon(Icons.close, color: isDark ? colors.onSurface : Colors.black),
              onPressed: () => setState(() {
                _isSelectionMode = false;
                _selectedIds.clear();
              }),
            )
          : null,
      title: _isSelectionMode
          ? Text(
              '${_selectedIds.length} selected',
              style: GoogleFonts.poppins(
                color: isDark ? colors.onSurface : Colors.black,
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
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
          PopupMenuButton<String>(
            icon: Icon(Icons.more_vert, color: isDark ? colors.onSurface : Colors.black),
            onSelected: (value) {
              if (value == 'mark_all_read') {
                _service.markAllAsRead(widget.userId);
                _loadNotifications();
              } else if (value == 'select_mode') {
                setState(() => _isSelectionMode = true);
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'mark_all_read',
                child: Row(
                  children: [
                    const Icon(Icons.done_all, size: 20),
                    const SizedBox(width: 12),
                    Text('Mark all as read', style: GoogleFonts.poppins(fontSize: 14)),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'select_mode',
                child: Row(
                  children: [
                    const Icon(Icons.checklist, size: 20),
                    const SizedBox(width: 12),
                    Text('Select mode', style: GoogleFonts.poppins(fontSize: 14)),
                  ],
                ),
              ),
            ],
          ),
        ],
        if (_isSelectionMode) ...[
          IconButton(
            icon: Icon(Icons.select_all, color: isDark ? colors.onSurface : Colors.black),
            onPressed: () => setState(() {
              if (_selectedIds.length == _filteredNotifications.length) {
                _selectedIds.clear();
              } else {
                _selectedIds = _filteredNotifications.map((n) => n.id).toSet();
              }
            }),
          ),
        ],
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(48),
        child: _buildFilterTabs(),
      ),
    );
  }

  Widget _buildFilterTabs() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Container(
      color: isDark ? colors.surface : Colors.white,
      child: TabBar(
        controller: _tabController,
        isScrollable: true,
        labelColor: isDark ? colors.onSurface : Colors.black,
        unselectedLabelColor: isDark ? colors.onSurfaceVariant : Colors.grey,
        indicatorColor: isDark ? colors.onSurface : Colors.black,
        onTap: (index) {
          setState(() {
            _selectedFilter = _filters[index];
            _applyFilters();
          });
        },
        tabs: _filters.map((filter) {
          final count = filter == 'all'
              ? _notifications.length
              : filter == 'unread'
                  ? _notifications.where((n) => n.isUnread).length
                  : _notifications.where((n) => n.type == filter).length;

          return Tab(
            child: Row(
              children: [
                Text(
                  filter.toUpperCase(),
                  style: GoogleFonts.poppins(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (count > 0) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$count',
                      style: GoogleFonts.poppins(
                        fontSize: 10,
                        color: isDark ? colors.surface : Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildLoading() {
    return const Center(
      child: CircularProgressIndicator(),
    );
  }

  Widget _buildContent() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    if (_filteredNotifications.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.notifications_off, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'No notifications',
              style: GoogleFonts.poppins(
                fontSize: 16,
                color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
              ),
            ),
          ],
        ),
      );
    }

    final grouped = NotificationHelper.groupByDate(_filteredNotifications);

    return RefreshIndicator(
      onRefresh: _loadNotifications,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: grouped.length,
        itemBuilder: (context, index) {
          final entry = grouped.entries.elementAt(index);
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
              ...entry.value.map((notification) => 
                _buildNotificationCard(notification)
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildNotificationCard(NotificationModel notification) {
    final isSelected = _selectedIds.contains(notification.id);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return GestureDetector(
      onTap: () {
        if (_isSelectionMode) {
          _toggleSelection(notification.id);
        } else {
          _openNotificationDetail(notification);
        }
      },
      onLongPress: () {
        setState(() {
          _isSelectionMode = true;
          _toggleSelection(notification.id);
        });
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: isDark
              ? (isSelected ? colors.primaryContainer : colors.surface)
              : (isSelected ? Colors.blue.shade50 : Colors.white),
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
                  onChanged: (_) => _toggleSelection(notification.id),
                )
              : Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: notification.color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(notification.icon, color: notification.color),
                ),
          title: Text(
            notification.title,
            style: GoogleFonts.poppins(
              fontWeight: notification.isUnread ? FontWeight.bold : FontWeight.w600,
              fontSize: 14,
              color: isDark ? colors.onSurface : Colors.black,
            ),
          ),
          subtitle: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                notification.message,
                style: GoogleFonts.poppins(
                  fontSize: 12,
                  color: isDark ? colors.onSurfaceVariant : Colors.grey[600],
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              Text(
                notification.formattedTime,
                style: GoogleFonts.poppins(
                  fontSize: 10,
                  color: isDark ? colors.onSurfaceVariant : Colors.grey[500],
                ),
              ),
            ],
          ),
          trailing: notification.isUnread
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
    );
  }

  Widget _buildSelectionBar() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? colors.surface : Colors.white,
        boxShadow: [
          BoxShadow(
            color: isDark
                ? Colors.black.withValues(alpha: 0.6)
                : Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: ElevatedButton.icon(
              onPressed: _archiveSelected,
              icon: const Icon(Icons.archive),
              label: const Text('Archive'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.orange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: ElevatedButton.icon(
              onPressed: _deleteSelected,
              icon: const Icon(Icons.delete),
              label: const Text('Delete'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showSearchBar() {
    showDialog(
      context: context,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        final colors = Theme.of(context).colorScheme;

        return AlertDialog(
          backgroundColor: isDark ? colors.surface : Colors.white,
          title: Text(
            'Search Notifications',
            style: GoogleFonts.poppins(
              fontWeight: FontWeight.w700,
              color: isDark ? colors.onSurface : Colors.black,
            ),
          ),
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
            style: GoogleFonts.poppins(
              color: isDark ? colors.onSurface : Colors.black,
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
              child: Text(
                'Clear',
                style: GoogleFonts.poppins(
                  color: isDark ? colors.onSurfaceVariant : Colors.grey[700],
                ),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue,
              ),
              child: Text(
                'Done',
                style: GoogleFonts.poppins(color: Colors.white),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// ✨ NEW: Notification Detail Screen
class NotificationDetailScreen extends StatefulWidget {
  final NotificationModel notification;
  final VoidCallback onDelete;
  final VoidCallback onArchive;

  const NotificationDetailScreen({
    super.key,
    required this.notification,
    required this.onDelete,
    required this.onArchive,
  });

  @override
  State<NotificationDetailScreen> createState() => _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  late NotificationModel notification;

  @override
  void initState() {
    super.initState();
    notification = widget.notification;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final colors = Theme.of(context).colorScheme;

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
              if (value == 'archive') {
                widget.onArchive();
                Navigator.pop(context);
              } else if (value == 'delete') {
                widget.onDelete();
                Navigator.pop(context);
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'archive',
                child: Row(
                  children: [
                    const Icon(Icons.archive, size: 20),
                    const SizedBox(width: 12),
                    Text('Archive', style: GoogleFonts.poppins()),
                  ],
                ),
              ),
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
            // Header with icon and title
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
                          color: notification.color.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          notification.icon,
                          color: notification.color,
                          size: 32,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              notification.title,
                              style: GoogleFonts.poppins(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: isDark ? colors.onSurface : Colors.black,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              notification.formattedTime,
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

            // Message content
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
                    notification.message,
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

            // Notification details
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
                  _buildDetailRow(
                    'Type',
                    notification.type.toUpperCase(),
                    isDark,
                    colors,
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    'Status',
                    notification.isUnread ? 'Unread' : 'Read',
                    isDark,
                    colors,
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    'Date & Time',
                    notification.formattedTime,
                    isDark,
                    colors,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Action buttons
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  widget.onArchive();
                  Navigator.pop(context);
                },
                icon: const Icon(Icons.archive),
                label: const Text('Archive Notification'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.orange,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
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
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(
    String label,
    String value,
    bool isDark,
    ColorScheme colors,
  ) {
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