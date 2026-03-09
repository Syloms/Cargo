import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/config/api_config.dart';
import '../Renter/search_filter_screen.dart';
import '../Renter/widgets/bottom_nav_bar.dart';
import 'car_detail_screen.dart';
import '../Renter/chats/chat_list_screen.dart';
import 'widgets/sort_bottom_sheet.dart';
import 'widgets/favorite_button.dart';
import 'services/saved_search_service.dart';
import 'saved_searches_screen.dart';
import 'cars_map_view_screen.dart';
import 'package:cargo/widgets/optimized_network_image.dart';
import '../../utils/image_helper.dart';

// ✨ THEME CONSTANTS - Facebook-style Dark Mode (Softer, Not Pure Black)
class AppColors {
  // Light Mode Colors
  static const Color lightBg = Color(0xFFFAFAFA);
  static const Color lightCard = Colors.white;
  static const Color lightText = Color(0xFF000000);
  static const Color lightSecondaryText = Color(0xFF65676B);
  static const Color lightBorder = Color(0xFFCED0D4);
  
  // Dark Mode (Facebook-inspired: soft, sophisticated)
  static const Color darkBg = Color(0xFF111214);
  static const Color darkCard = Color(0xFF1E1F23);
  static const Color darkCardSecondary = Color(0xFF242529);
  static const Color darkText = Color(0xFFE4E6EB);
  static const Color darkSecondaryText = Color(0xFFB0B3B9);
  static const Color darkBorder = Color(0xFF3A3B40);
  
  // Accent Colors
  static const Color primary = Color(0xFF0A66C2);
  static const Color primaryLight = Color(0xFF0D78F2);
  static const Color accent = Color(0xFFF0416C);
  static const Color success = Color(0xFF31A24C);
}



class CarListScreen extends StatefulWidget {
  final String title;

  const CarListScreen({
    super.key,
    this.title = 'Search',
  });

  @override
  State<CarListScreen> createState() => _CarListScreenState();
}

class _CarListScreenState extends State<CarListScreen> {
  String _selectedCategory = 'All';
  int _selectedNavIndex = 0;

  List<Map<String, dynamic>> _allCars = [];
  List<Map<String, dynamic>> _filteredCars = [];
  bool _loading = true;

  // Active filters
  Map<String, dynamic>? _activeFilters;
  int _activeFilterCount = 0;
  
  // Sorting
  String _sortBy = 'created_at';
  String _sortOrder = 'DESC';
  
  // Saved searches
  final SavedSearchService _savedSearchService = SavedSearchService();

  final List<String> _categories = ['All', 'SUV', 'Sedan', 'Sport', 'Coupe', 'Luxury'];

  @override
  void initState() {
    super.initState();
    fetchCars();
  }

  String getImageUrl(String path) {
    return ImageHelper.formatImageUrl(path);
  }

  Future<void> fetchCars() async {
    String url = GlobalApiConfig.getCarsFilteredEndpoint;
    List<String> queryParams = [];
    
    // Build query parameters from active filters
    if (_activeFilters != null) {
      if (_activeFilters!['location'] != null && _activeFilters!['location'].toString().isNotEmpty) {
        queryParams.add('location=${Uri.encodeComponent(_activeFilters!['location'])}');
      }
      if (_activeFilters!['transmission'] != null && _activeFilters!['transmission'].toString().isNotEmpty) {
        queryParams.add('transmission=${Uri.encodeComponent(_activeFilters!['transmission'])}');
      }
      if (_activeFilters!['fuelType'] != null && _activeFilters!['fuelType'].toString().isNotEmpty) {
        queryParams.add('fuelType=${Uri.encodeComponent(_activeFilters!['fuelType'])}');
      }
      if (_activeFilters!['bodyStyle'] != null && _activeFilters!['bodyStyle'].toString().isNotEmpty) {
        queryParams.add('bodyStyle=${Uri.encodeComponent(_activeFilters!['bodyStyle'])}');
      }
      if (_activeFilters!['brand'] != null && _activeFilters!['brand'].toString().isNotEmpty) {
        queryParams.add('brand=${Uri.encodeComponent(_activeFilters!['brand'])}');
      }
      if (_activeFilters!['year'] != null && _activeFilters!['year'].toString().isNotEmpty) {
        queryParams.add('year=${Uri.encodeComponent(_activeFilters!['year'])}');
      }
      if (_activeFilters!['seats'] != null && _activeFilters!['seats'] > 0) {
        queryParams.add('seats=${_activeFilters!['seats']}');
      }
      if (_activeFilters!['deliveryMethod'] != null && _activeFilters!['deliveryMethod'].toString().isNotEmpty) {
        queryParams.add('deliveryMethod=${Uri.encodeComponent(_activeFilters!['deliveryMethod'])}');
      }
      
      double minPrice = (_activeFilters!['minPrice'] ?? 0).toDouble();
      double maxPrice = (_activeFilters!['maxPrice'] ?? 999999).toDouble();
      queryParams.add('minPrice=$minPrice');
      queryParams.add('maxPrice=$maxPrice');
    }
    
    // Add sorting parameters
    queryParams.add('sortBy=$_sortBy');
    queryParams.add('sortOrder=$_sortOrder');
    
    if (queryParams.isNotEmpty) {
      url += '?${queryParams.join('&')}';
    }

    try {
      // ✅ CRASH FIX: Add timeout to prevent hanging
      final res = await http.get(Uri.parse(url)).timeout(
        const Duration(seconds: 15),
        onTimeout: () {
          throw Exception('Connection timeout');
        },
      );

      if (res.statusCode == 200) {
        // ✅ CRASH FIX: Wrap jsonDecode in try-catch
        try {
          final data = jsonDecode(res.body);
          if (data['status'] == 'success') {
            // ✅ FIX: Check if widget is still mounted before calling setState
            if (mounted) {
              setState(() {
                _allCars = List<Map<String, dynamic>>.from(data['cars']);
                _applyFilters();
              });
            }
          }
        } catch (jsonError) {
          debugPrint("❌ JSON decode error: $jsonError");
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Error loading car data')),
            );
          }
        }
      } else {
        debugPrint("❌ HTTP error: ${res.statusCode}");
      }
    } catch (e) {
      debugPrint("❌ ERROR LOADING CARS: $e");
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString().contains('timeout') 
              ? 'Connection timeout. Please check your internet.' 
              : 'Failed to load cars. Please try again.'),
          ),
        );
      }
    }

    // ✅ FIX: Check if widget is still mounted before calling setState
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  void _applyFilters() {
    List<Map<String, dynamic>> filtered = List.from(_allCars);

    // Apply category filter (frontend only)
    if (_selectedCategory != 'All') {
      filtered = filtered.where((car) {
        String bodyStyle = (car['body_style'] ?? '').toString().toLowerCase();
        String selectedCat = _selectedCategory.toLowerCase();
        
        if (selectedCat == 'suv' && (bodyStyle.contains('suv') || bodyStyle.contains('crossover'))) {
          return true;
        } else if (selectedCat == 'sedan' && bodyStyle.contains('sedan')) {
          return true;
        } else if (selectedCat == 'sport' && (bodyStyle.contains('sport') || bodyStyle.contains('coupe'))) {
          return true;
        } else if (selectedCat == 'coupe' && bodyStyle.contains('coupe')) {
          return true;
        } else if (selectedCat == 'luxury' && (bodyStyle.contains('luxury') || bodyStyle.contains('executive'))) {
          return true;
        }
        return false;
      }).toList();
    }

    if (mounted) {
      setState(() {
        _filteredCars = filtered;
        _calculateActiveFilters();
      });
    }
  }

  void _calculateActiveFilters() {
    int count = 0;
    if (_activeFilters != null) {
      if (_activeFilters!['location'] != null && 
          _activeFilters!['location'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['vehicleType'] != null && 
          _activeFilters!['vehicleType'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['deliveryMethod'] != null && 
          _activeFilters!['deliveryMethod'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['transmission'] != null && 
          _activeFilters!['transmission'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['fuelType'] != null && 
          _activeFilters!['fuelType'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['bodyStyle'] != null && 
          _activeFilters!['bodyStyle'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['brand'] != null && 
          _activeFilters!['brand'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['year'] != null && 
          _activeFilters!['year'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['seats'] != null && _activeFilters!['seats'] > 0) count++;
      
      double minPrice = (_activeFilters!['minPrice'] ?? 0).toDouble();
      double maxPrice = (_activeFilters!['maxPrice'] ?? 2000).toDouble();
      if (minPrice > 0 || maxPrice < 2000) count++;
    }
    _activeFilterCount = count;
  }

  void _clearAllFilters() {
    if (!mounted) return;
    
    setState(() {
      _activeFilters = null;
      _activeFilterCount = 0;
      _selectedCategory = 'All';
    });
    
    fetchCars();
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('All filters cleared'),
        backgroundColor: Theme.of(context).primaryColor,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  void _handleNavigation(int index) {
    if (!mounted) return;
    
    setState(() => _selectedNavIndex = index);

    if (index == 0) Navigator.pop(context);
    if (index == 3) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const ChatListScreen()));
    }
  }

  void _showSortOptions() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SortBottomSheet(
        currentSortBy: _sortBy,
        currentSortOrder: _sortOrder,
        onSortChanged: (sortBy, sortOrder) {
          if (mounted) {
            setState(() {
              if (sortBy == 'year') {
                _sortBy = 'car_year';
              } else {
                _sortBy = sortBy;
              }
              _sortOrder = sortOrder;
            });
            fetchCars();
          }
        },
      ),
    );
  }

  void _showSaveSearchDialog() {
    if (_activeFilters == null || _activeFilterCount == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please apply filters before saving')),
      );
      return;
    }

    final TextEditingController nameController = TextEditingController();
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Theme.of(context).cardColor,
        title: Text(
          'Save Search',
          style: GoogleFonts.poppins(fontWeight: FontWeight.w600, fontSize: 18),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Give your search a name:',
              style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: nameController,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'e.g., Budget SUVs in Bayugan',
                hintStyle: GoogleFonts.poppins(color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.6)),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Theme.of(context).dividerColor),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppColors.primary, width: 2),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                filled: true,
                fillColor: Theme.of(context).brightness == Brightness.dark 
                  ? AppColors.darkCardSecondary 
                  : Colors.grey[50],
              ),
              style: GoogleFonts.poppins(),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Cancel', style: GoogleFonts.poppins(color: Theme.of(context).textTheme.bodyLarge?.color)),
          ),
          ElevatedButton(
            onPressed: () async {
              final name = nameController.text.trim();
              if (name.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Please enter a name')),
                );
                return;
              }

              final success = await _savedSearchService.saveSearch(name, _activeFilters!);

              if (!mounted) return;
              Navigator.pop(this.context);
              if (success) {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(
                    content: Text('Search saved successfully!'),
                    backgroundColor: AppColors.success,
                  ),
                );
              } else {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('A search with this name already exists')),
                );
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).primaryColor,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: Text('Save', style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }

  void _showSavedSearches() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => SavedSearchesScreen(
          onSearchSelected: (filters) {
            setState(() {
              _activeFilters = filters;
            });
            fetchCars();
          },
        ),
      ),
    );
  }

  void _showMapView() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CarsMapViewScreen(
          cars: _filteredCars,
          title: 'Cars Map View (${_filteredCars.length})',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      resizeToAvoidBottomInset: false,
      body: SafeArea(
        child: _loading
            ? _buildLoading()
            : CustomScrollView(
                slivers: [
                  _buildSliverAppBar(),
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    sliver: SliverList(
                      delegate: SliverChildListDelegate([
                        _buildSearchBar(),
                        const SizedBox(height: 18),
                        _buildCategoryFilter(),
                        if (_activeFilterCount > 0) ...[
                          const SizedBox(height: 16),
                          _buildActiveFiltersChip(),
                          const SizedBox(height: 12),
                          _buildSavedSearchActions(),
                        ],
                        const SizedBox(height: 24),
                        _buildHeaderWithActions(),
                        const SizedBox(height: 12),
                      ]),
                    ),
                  ),
                  _buildCarGrid(),
                  const SliverPadding(padding: EdgeInsets.only(bottom: 20)),
                ],
              ),
      ),
      bottomNavigationBar: BottomNavBar(
        currentIndex: _selectedNavIndex,
        onTap: _handleNavigation,
      ),
    );
  }

  Widget _buildHeaderWithActions() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            _filteredCars.isEmpty ? "No cars found" : "Available Cars (${_filteredCars.length})",
            style: GoogleFonts.poppins(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Theme.of(context).textTheme.titleLarge?.color,
            ),
          ),
        ),
        if (_filteredCars.isNotEmpty)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildIconButton(Icons.tune, _showSortOptions),
              const SizedBox(width: 10),
              _buildIconButton(Icons.map, _showMapView),
            ],
          ),
      ],
    );
  }

  Widget _buildIconButton(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: Theme.of(context).brightness == Brightness.dark
              ? AppColors.darkCardSecondary
              : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: Theme.of(context).brightness == Brightness.dark
                ? AppColors.darkBorder
                : Colors.grey.shade300,
            width: 1,
          ),
        ),
        child: Icon(
          icon,
          size: 20,
          color: Theme.of(context).primaryColor,
        ),
      ),
    );
  }

  Widget _buildActiveFiltersChip() {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: Theme.of(context).primaryColor,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.filter_list, color: Colors.white, size: 16),
              const SizedBox(width: 6),
              Text(
                '$_activeFilterCount ${_activeFilterCount == 1 ? 'Filter' : 'Filters'} Active',
                style: GoogleFonts.poppins(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        GestureDetector(
          onTap: _clearAllFilters,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Theme.of(context).brightness == Brightness.dark
                  ? AppColors.darkCardSecondary
                  : Colors.grey.shade100,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: Theme.of(context).brightness == Brightness.dark
                    ? AppColors.darkBorder
                    : Colors.grey.shade300,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.clear,
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                  size: 16,
                ),
                const SizedBox(width: 6),
                Text(
                  'Clear All',
                  style: GoogleFonts.poppins(
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSavedSearchActions() {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _showSaveSearchDialog,
            icon: const Icon(Icons.bookmark_add_outlined, size: 18),
            label: Text(
              'Save Search',
              style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
              side: BorderSide(color: Theme.of(context).primaryColor, width: 1.5),
              foregroundColor: Theme.of(context).primaryColor,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _showSavedSearches,
            icon: const Icon(Icons.bookmarks_outlined, size: 18),
            label: Text(
              'View Saved',
              style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
              side: BorderSide(
                color: Theme.of(context).brightness == Brightness.dark
                    ? AppColors.darkBorder
                    : Colors.grey.shade400,
              ),
              foregroundColor: Theme.of(context).textTheme.bodyLarge?.color,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSliverAppBar() {
    return SliverAppBar(
      backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
      elevation: 0.5,
      pinned: false,
      floating: true,
      leading: IconButton(
        icon: Icon(Icons.arrow_back, color: Theme.of(context).iconTheme.color),
        onPressed: () => Navigator.pop(context),
      ),
      title: Text(
        widget.title,
        style: GoogleFonts.poppins(
          color: Theme.of(context).textTheme.titleLarge?.color,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
      ),
      centerTitle: true,
    );
  }

  Widget _buildLoading() => const Center(
    child: CircularProgressIndicator(color: AppColors.primary),
  );

  Widget _buildSearchBar() {
    return GestureDetector(
      onTap: () async {
        final result = await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => SearchFilterScreen(currentFilters: _activeFilters),
          ),
        );

        if (result != null && result is Map<String, dynamic> && mounted) {
          setState(() {
            _activeFilters = result;
          });
          fetchCars();
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Filters applied - ${_filteredCars.length} cars found'),
              backgroundColor: Theme.of(context).primaryColor,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      },
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: Theme.of(context).brightness == Brightness.dark
                ? AppColors.darkBorder
                : Colors.grey.shade300,
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Icon(
              Icons.filter_list,
              color: _activeFilterCount > 0
                  ? Theme.of(context).primaryColor
                  : Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.5),
              size: 22,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                _activeFilterCount > 0
                    ? "$_activeFilterCount filter${_activeFilterCount > 1 ? 's' : ''} applied"
                    : "Filter & search cars...",
                style: GoogleFonts.poppins(
                  color: _activeFilterCount > 0
                      ? Theme.of(context).textTheme.titleLarge?.color
                      : Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.6),
                  fontSize: 14,
                  fontWeight: _activeFilterCount > 0 ? FontWeight.w600 : FontWeight.w500,
                ),
              ),
            ),
            if (_activeFilterCount > 0)
              Container(
                padding: const EdgeInsets.all(6),
                decoration: const BoxDecoration(
                  color: AppColors.primary,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '$_activeFilterCount',
                  style: GoogleFonts.poppins(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryFilter() {
    return SizedBox(
      height: 42,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: _categories.length,
        itemBuilder: (context, index) {
          final category = _categories[index];
          final isSelected = _selectedCategory == category;

          return GestureDetector(
            onTap: () {
              if (!mounted) return;
              setState(() {
                _selectedCategory = category;
                _applyFilters();
              });
            },
            child: Container(
              margin: const EdgeInsets.only(right: 10),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              decoration: BoxDecoration(
                color: isSelected
                    ? Theme.of(context).primaryColor
                    : Theme.of(context).brightness == Brightness.dark
                        ? AppColors.darkCardSecondary
                        : Colors.grey.shade100,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: isSelected
                      ? Theme.of(context).primaryColor
                      : Theme.of(context).brightness == Brightness.dark
                          ? AppColors.darkBorder
                          : Colors.grey.shade300,
                  width: 1,
                ),
                boxShadow: isSelected
                    ? [
                        BoxShadow(
                          color: Theme.of(context).primaryColor.withValues(alpha :0.3),
                          blurRadius: 6,
                          offset: const Offset(0, 2),
                        ),
                      ]
                    : [],
              ),
              child: Text(
                category,
                style: GoogleFonts.poppins(
                  color: isSelected ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color,
                  fontSize: 13,
                  fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildCarGrid() {
    if (_filteredCars.isEmpty) {
      return SliverToBoxAdapter(
        child: Container(
          padding: const EdgeInsets.all(40),
          child: Column(
            children: [
              Icon(
                Icons.search_off,
                size: 80,
                color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.3),
              ),
              const SizedBox(height: 16),
              Text(
                'No cars found',
                style: GoogleFonts.poppins(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Theme.of(context).textTheme.titleLarge?.color,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Try adjusting your filters',
                style: GoogleFonts.poppins(
                  fontSize: 14,
                  color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.7),
                ),
              ),
              if (_activeFilterCount > 0) ...[
                const SizedBox(height: 24),
                ElevatedButton.icon(
                  onPressed: _clearAllFilters,
                  icon: const Icon(Icons.clear, size: 18),
                  label: Text(
                    'Clear Filters',
                    style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ],
          ),
        ),
      );
    }

    final crossAxisCount = MediaQuery.of(context).size.width >= 600 ? 3 : 2;
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: SliverToBoxAdapter(
        child: GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: crossAxisCount,
            crossAxisSpacing: 12,
            mainAxisSpacing: 14,
            childAspectRatio: 0.72,
          ),
          itemCount: _filteredCars.length,
          itemBuilder: (context, index) {
            final car = _filteredCars[index];
            return _buildCarCard(
              carId: int.tryParse(car['id'].toString()) ?? 0,
              name: "${car['brand']} ${car['model']}",
              year: car['car_year'] ?? "",
              rating: double.tryParse(car['rating'].toString()) ?? 0.0,
              location: (car['location'] ?? '').toString().trim().isEmpty ? 'Location not set' : car['location'],
              price: car['price'].toString(),
              seats: int.tryParse(car['seat'].toString()) ?? 4,
              transmission: car['transmission'] ?? "Automatic",
              image: getImageUrl(car['image']),
              hasUnlimitedMileage: car['has_unlimited_mileage'] == 1,
            );
          },
        ),
      ),
    );
  }

  Widget _buildCarCard({
    required int carId,
    required String name,
    required String year,
    required double rating,
    required String location,
    required String price,
    required int seats,
    required String transmission,
    required String image,
    bool hasUnlimitedMileage = false,
  }) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => CarDetailScreen(
              carId: carId,
              carName: name,
              carImage: image,
              price: price,
              rating: rating,
              location: location,
            ),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Theme.of(context).brightness == Brightness.dark
                ? AppColors.darkBorder.withValues(alpha :0.5)
                : Colors.grey.shade200,
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image Section
            AspectRatio(
              aspectRatio: 1.4,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  OptimizedNetworkImage(
                    imageUrl: image,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                    errorIcon: Icons.directions_car,
                  ),
                  // Favorite button
                  Positioned(
                    top: 8,
                    right: 8,
                    child: FavoriteButton(
                      vehicleType: 'car',
                      vehicleId: carId,
                      size: 20,
                    ),
                  ),
                  if (hasUnlimitedMileage)
                    Positioned(
                      top: 12,
                      left: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppColors.success,
                          borderRadius: BorderRadius.circular(8),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.2),
                              blurRadius: 4,
                            ),
                          ],
                        ),
                        child: Text(
                          "Unlimited Km",
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            // Details Section
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Price
                    Text(
                      "₱$price",
                      style: GoogleFonts.poppins(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).primaryColor,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    // Car Name
                    Text(
                      "$name $year",
                      style: GoogleFonts.poppins(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Theme.of(context).textTheme.titleLarge?.color,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 6),
                    // Location
                    Row(
                      children: [
                        Icon(
                          Icons.location_on,
                          size: 11,
                          color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.6),
                        ),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            location,
                            style: GoogleFonts.poppins(
                              fontSize: 10,
                              color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.7),
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    // Seats & Rating
                    Row(
                      children: [
                        Icon(
                          Icons.event_seat,
                          size: 11,
                          color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.6),
                        ),
                        const SizedBox(width: 3),
                        Text(
                          "$seats",
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.7),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(
                          Icons.star,
                          color: Colors.amber,
                          size: 11,
                        ),
                        const SizedBox(width: 3),
                        Text(
                          rating.toStringAsFixed(1),
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Theme.of(context).textTheme.bodyLarge?.color?.withValues(alpha :0.7),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
