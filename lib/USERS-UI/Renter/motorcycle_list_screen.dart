import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/config/api_config.dart';
import '../Renter/motorcycle_filter_screen.dart';
import '../Renter/widgets/bottom_nav_bar.dart';
import 'motorcycle_detail_screen.dart';
import '../Renter/chats/chat_list_screen.dart';
import 'widgets/sort_bottom_sheet.dart';
import 'widgets/favorite_button.dart';
import 'widgets/notification_icon.dart';
import 'motorcycles_map_view_screen.dart';
import 'package:cargo/widgets/optimized_network_image.dart';
import 'package:cargo/widgets/loading_widgets.dart';
import '../../utils/image_helper.dart';

class MotorcycleListScreen extends StatefulWidget {
  final String title;

  const MotorcycleListScreen({
    super.key,
    this.title = 'Search Motorcycles',
  });

  @override
  State<MotorcycleListScreen> createState() => _MotorcycleListScreenState();
}

class _MotorcycleListScreenState extends State<MotorcycleListScreen> {
  String _selectedCategory = 'All';
  int _selectedNavIndex = 0;

  List<Map<String, dynamic>> _allMotorcycles = [];
  List<Map<String, dynamic>> _filteredMotorcycles = [];
  bool _loading = true;

  // Active filters
  Map<String, dynamic>? _activeFilters;
  int _activeFilterCount = 0;
  
  // Sorting
  String _sortBy = 'created_at';
  String _sortOrder = 'DESC';

  final List<String> _categories = ['All', 'Sport', 'Cruiser', 'Touring', 'Standard', 'Scooter'];

  @override
  void initState() {
    super.initState();
    fetchMotorcycles();
  }

  String getImageUrl(String path) {
    return ImageHelper.formatImageUrl(path);
  }

  Future<void> fetchMotorcycles() async {
    String url = GlobalApiConfig.getMotorcyclesFilteredEndpoint;
    List<String> queryParams = [];
    
    // Build query parameters from active filters
    if (_activeFilters != null) {
      if (_activeFilters!['location'] != null && _activeFilters!['location'].toString().isNotEmpty) {
        queryParams.add('location=${Uri.encodeComponent(_activeFilters!['location'])}');
      }
      if (_activeFilters!['transmission'] != null && _activeFilters!['transmission'].toString().isNotEmpty) {
        queryParams.add('transmission=${Uri.encodeComponent(_activeFilters!['transmission'])}');
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
      if (_activeFilters!['engineSize'] != null && _activeFilters!['engineSize'].toString().isNotEmpty) {
        queryParams.add('engineSize=${Uri.encodeComponent(_activeFilters!['engineSize'])}');
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
      final res = await http.get(Uri.parse(url));
      
      debugPrint("🔍 Motorcycle API Response Status: ${res.statusCode}");
      debugPrint("🔍 Motorcycle API Response Body (first 200 chars): ${res.body.substring(0, res.body.length > 200 ? 200 : res.body.length)}");

      if (res.statusCode == 200) {
        try {
          final data = jsonDecode(res.body);
          
          if (data['status'] == 'error') {
            debugPrint("❌ API Error: ${data['message']}");
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Error: ${data['message']}')),
              );
            }
            return;
          }
          
          if (data['status'] == 'success') {
            if (mounted) {
              setState(() {
                _allMotorcycles = List<Map<String, dynamic>>.from(data['motorcycles']);
                _applyFilters();
              });
            }
          }
        } catch (jsonError) {
          debugPrint("❌ JSON Decode Error: $jsonError");
          debugPrint("❌ Response body: ${res.body}");
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Invalid server response. Please check API.')),
            );
          }
        }
      } else {
        debugPrint("❌ HTTP Error: ${res.statusCode}");
      }
    } catch (e) {
      debugPrint("❌ ERROR LOADING MOTORCYCLES: $e");
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Network error: $e')),
        );
      }
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  void _applyFilters() {
    List<Map<String, dynamic>> filtered = List.from(_allMotorcycles);

    // Apply category filter (frontend only)
    if (_selectedCategory != 'All') {
      filtered = filtered.where((motorcycle) {
        String bodyStyle = (motorcycle['body_style'] ?? '').toString().toLowerCase();
        String selectedCat = _selectedCategory.toLowerCase();
        
        if (selectedCat == 'sport' && bodyStyle.contains('sport')) {
          return true;
        } else if (selectedCat == 'cruiser' && bodyStyle.contains('cruiser')) {
          return true;
        } else if (selectedCat == 'touring' && bodyStyle.contains('touring')) {
          return true;
        } else if (selectedCat == 'standard' && bodyStyle.contains('standard')) {
          return true;
        } else if (selectedCat == 'scooter' && bodyStyle.contains('scooter')) {
          return true;
        }
        return false;
      }).toList();
    }

    if (mounted) {
      setState(() {
        _filteredMotorcycles = filtered;
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
      if (_activeFilters!['deliveryMethod'] != null && 
          _activeFilters!['deliveryMethod'].toString().isNotEmpty) {
        count++;
      }
      if (_activeFilters!['transmission'] != null && 
          _activeFilters!['transmission'].toString().isNotEmpty) {
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
      if (_activeFilters!['engineSize'] != null && 
          _activeFilters!['engineSize'].toString().isNotEmpty) {
        count++;
      }
      
      double minPrice = (_activeFilters!['minPrice'] ?? 0).toDouble();
      double maxPrice = (_activeFilters!['maxPrice'] ?? 5000).toDouble();
      if (minPrice > 0 || maxPrice < 5000) count++;
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
    
    fetchMotorcycles();
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('All filters cleared'),
        backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
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
      backgroundColor: Colors.transparent,
      builder: (context) => SortBottomSheet(
        currentSortBy: _sortBy,
        currentSortOrder: _sortOrder,
        onSortChanged: (sortBy, sortOrder) {
          if (mounted) {
            setState(() {
              // Map generic 'year' to motorcycle-specific field
              if (sortBy == 'year') {
                _sortBy = 'motorcycle_year';
              } else {
                _sortBy = sortBy;
              }
              _sortOrder = sortOrder;
            });
            fetchMotorcycles();
          }
        },
      ),
    );
  }

  void _showMapView() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MotorcyclesMapViewScreen(
          motorcycles: _filteredMotorcycles,
          title: 'Motorcycles Map View (${_filteredMotorcycles.length})',
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
                    padding: const EdgeInsets.all(20),
                    sliver: SliverList(
                      delegate: SliverChildListDelegate([
                        _buildSearchBar(),
                        const SizedBox(height: 20),
                        _buildCategoryFilter(),
                        if (_activeFilterCount > 0) ...[
                          const SizedBox(height: 16),
                          _buildActiveFiltersChip(),
                        ],
                        const SizedBox(height: 24),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                _filteredMotorcycles.isEmpty ? "No motorcycles found" : "Available Motorcycles (${_filteredMotorcycles.length})",
                                style: GoogleFonts.poppins(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: Theme.of(context).textTheme.titleLarge?.color,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (_filteredMotorcycles.isNotEmpty)
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  // Sort button
                                  GestureDetector(
                                    onTap: _showSortOptions,
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                      decoration: BoxDecoration(
                                        color: Colors.grey.shade100,
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: Colors.grey.shade300),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(Icons.sort, size: 18, color: Colors.grey.shade700),
                                          const SizedBox(width: 6),
                                          Text(
                                            'Sort',
                                            style: GoogleFonts.poppins(
                                              fontSize: 13,
                                              fontWeight: FontWeight.w500,
                                              color: Colors.grey.shade700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  // Map button (now on the right)
                                  GestureDetector(
                                    onTap: _showMapView,
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                      decoration: BoxDecoration(
                                        color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: Theme.of(context).primaryColor.withValues(alpha: 0.3)),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(Icons.map, size: 18, color: Theme.of(context).primaryColor),
                                          const SizedBox(width: 6),
                                          Text(
                                            'Map',
                                            style: GoogleFonts.poppins(
                                              fontSize: 13,
                                              fontWeight: FontWeight.w500,
                                              color: Theme.of(context).primaryColor,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                          ],
                        ),
                        const SizedBox(height: 12),
                      ]),
                    ),
                  ),
                  _buildMotorcycleGrid(),
                ],
              ),
      ),
      bottomNavigationBar: BottomNavBar(
        currentIndex: _selectedNavIndex,
        onTap: _handleNavigation,
      ),
    );
  }

  Widget _buildActiveFiltersChip() {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: Theme.of(context).textTheme.titleLarge?.color,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.filter_list, color: Colors.white, size: 16),
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
        const SizedBox(width: 8),
        GestureDetector(
          onTap: _clearAllFilters,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.grey.shade300),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.clear, color: Colors.black, size: 16),
                const SizedBox(width: 6),
                Text(
                  'Clear All',
                  style: GoogleFonts.poppins(
                    color: Theme.of(context).textTheme.titleLarge?.color,
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

  Widget _buildSliverAppBar() {
    return SliverAppBar(
      backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
      elevation: 0,
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
      actions: const [
        Padding(
          padding: EdgeInsets.only(right: 12),
          child: NotificationIcon(),
        ),
      ],
    );
  }

  Widget _buildLoading() => const LoadingScreen(message: 'Loading motorcycles...');

  Widget _buildSearchBar() {
    return GestureDetector(
      onTap: () async {
        final result = await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => MotorcycleFilterScreen(
              currentFilters: _activeFilters,
            ),
          ),
        );

        if (result != null && result is Map<String, dynamic> && mounted) {
          setState(() {
            _activeFilters = result;
          });
          fetchMotorcycles();
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Filters applied - ${_filteredMotorcycles.length} motorcycles found'),
              backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Icon(
              Icons.filter_list, 
              color: _activeFilterCount > 0 ? Colors.black : Colors.grey, 
              size: 22
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                _activeFilterCount > 0 
                    ? "$_activeFilterCount filter${_activeFilterCount > 1 ? 's' : ''} applied"
                    : "Filter & search motorcycles...",
                style: GoogleFonts.poppins(
                  color: _activeFilterCount > 0 ? Colors.black : Colors.grey, 
                  fontSize: 14,
                  fontWeight: _activeFilterCount > 0 ? FontWeight.w600 : FontWeight.normal,
                ),
              ),
            ),
            if (_activeFilterCount > 0)
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Theme.of(context).textTheme.titleLarge?.color,
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
              margin: const EdgeInsets.only(right: 12),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              decoration: BoxDecoration(
                color: isSelected
                    ? Theme.of(context).primaryColor
                    : Theme.of(context).brightness == Brightness.dark
                        ? const Color(0xFF242529)
                        : Colors.grey.shade100,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: isSelected
                      ? Theme.of(context).primaryColor
                      : Theme.of(context).brightness == Brightness.dark
                          ? const Color(0xFF3A3B40)
                          : Colors.grey.shade300,
                  width: 1,
                ),
                boxShadow: isSelected
                    ? [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.1),
                          blurRadius: 8,
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

  Widget _buildMotorcycleGrid() {
    if (_filteredMotorcycles.isEmpty) {
      return SliverToBoxAdapter(
        child: Container(
          padding: const EdgeInsets.all(40),
          child: Column(
            children: [
              Icon(
                Icons.search_off,
                size: 80,
                color: Colors.grey.shade400,
              ),
              const SizedBox(height: 16),
              Text(
                'No motorcycles found',
                style: GoogleFonts.poppins(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade700,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Try adjusting your filters',
                style: GoogleFonts.poppins(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                ),
              ),
              if (_activeFilterCount > 0) ...[ 
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _clearAllFilters,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(
                    'Clear Filters',
                    style: GoogleFonts.poppins(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      );
    }

    final crossAxisCount = MediaQuery.of(context).size.width >= 600 ? 3 : 2;
    return SliverToBoxAdapter(
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 16),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: crossAxisCount,
          crossAxisSpacing: 12,
          mainAxisSpacing: 14,
          childAspectRatio: 0.72,
        ),
        itemCount: _filteredMotorcycles.length,
        itemBuilder: (context, index) {
          final motorcycle = _filteredMotorcycles[index];
          return _buildMotorcycleCard(
            
            motorcycleId: int.tryParse(motorcycle['id'].toString()) ?? 0,
            name: "${motorcycle['brand']} ${motorcycle['model']}",
            year: motorcycle['motorcycle_year'] ?? "",
            rating: double.tryParse(motorcycle['rating'].toString()) ?? 0.0,
            location: (motorcycle['location'] ?? '').toString().trim().isEmpty ? 'Location not set' : motorcycle['location'],
            price: motorcycle['price'].toString(),
            type: motorcycle['body_style'] ?? "Standard",
            transmission: motorcycle['transmission_type'] ?? "Manual",
            image: getImageUrl(motorcycle['image']),
            hasUnlimitedMileage: motorcycle['has_unlimited_mileage'] == 1,
          );
        },
      ),
    );
  }

  Widget _buildMotorcycleCard({
    required int motorcycleId,
    
    required String name,
    required String year,
    required double rating,
    required String location,
    required String price,
    required String type,
    required String transmission,
    required String image,
    bool hasUnlimitedMileage = false,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
final colors = Theme.of(context).colorScheme;


    return GestureDetector(
      
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => MotorcycleDetailScreen(
              motorcycleId: motorcycleId,
              motorcycleName: name,
              motorcycleImage: image,
              price: price,
              rating: rating,
              location: location,
            ),
          ),
        );
      },
      child: Container(
      decoration: BoxDecoration(
  // 🎨 Card color
  color: isDark
      ? colors.surfaceContainerHighest   // soft elevated dark gray
      : Colors.white,

  borderRadius: BorderRadius.circular(16),

  // 🌙 Soft elevation system
  boxShadow: isDark
      ? [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.35), // deeper but soft
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ]
      : [
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
                    errorIcon: Icons.two_wheeler,
                    errorIconSize: 60,
                  ),
                  // Favorite button
                  Positioned(
                    top: 8,
                    right: 8,
                    child: FavoriteButton(
                      vehicleType: 'motorcycle',
                      vehicleId: motorcycleId,
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
                          color: isDark ? colors.surface : Colors.white,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          "Unlimited Mileage",
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Theme.of(context).textTheme.titleLarge?.color,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      "₱$price",
                      style: GoogleFonts.poppins(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).textTheme.titleLarge?.color,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      "$name $year",
                      style: GoogleFonts.poppins(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: isDark ? colors.onSurface : Colors.black87,

                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.location_on, size: 11, color: Colors.grey.shade600),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            location,
                            style: GoogleFonts.poppins(
                              fontSize: 10,
                              color: Colors.grey.shade600,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.category, size: 11, color: Colors.grey.shade600),
                        const SizedBox(width: 3),
                        Text(
                          type,
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            color: Colors.grey.shade600,
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.star, color: Colors.amber, size: 11),
                        const SizedBox(width: 3),
                        Text(
                          rating.toStringAsFixed(1),
                          style: GoogleFonts.poppins(
                            fontSize: 10,
                            color: Colors.grey.shade600,
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
