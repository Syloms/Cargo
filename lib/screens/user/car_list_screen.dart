import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:cargo/screens/user/car_detail_screen.dart';

class CarListScreen extends StatefulWidget {
  const CarListScreen({super.key});

  @override
  State<CarListScreen> createState() => _CarListScreenState();
}

class _CarListScreenState extends State<CarListScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _selectedCategory = 'All';
  String _selectedSort = 'Popular';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  final List<Map<String, dynamic>> _cars = [
    {
      'name': 'Ferrari FF',
      'image': 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=500',
      'rating': 5.0,
      'location': 'Washington DC',
      'seats': 4,
      'price': '\$200',
      'category': 'Luxury',
    },
    {
      'name': 'Tesla Model S',
      'image': 'https://images.unsplash.com/photo-1560958089-b8a1929cea89?w=500',
      'rating': 5.0,
      'location': 'Chicago, USA',
      'seats': 5,
      'price': '\$1100',
      'category': 'Electric',
    },
    {
      'name': 'BMW M4',
      'image': 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=500',
      'rating': 4.8,
      'location': 'New York, USA',
      'seats': 4,
      'price': '\$850',
      'category': 'Sport',
    },
    {
      'name': 'BMW M8',
      'image': 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=500',
      'rating': 4.9,
      'location': 'Los Angeles, CA',
      'seats': 4,
      'price': '\$950',
      'category': 'Sport',
    },
    {
      'name': 'Audi R8',
      'image': 'https://images.unsplash.com/photo-1606664515524-ed2f786a0bd6?w=500',
      'rating': 4.7,
      'location': 'Miami, FL',
      'seats': 2,
      'price': '\$1200',
      'category': 'Sport',
    },
    {
      'name': 'Mercedes-Benz S-Class',
      'image': 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=500',
      'rating': 4.9,
      'location': 'San Francisco, CA',
      'seats': 5,
      'price': '\$800',
      'category': 'Luxury',
    },
  ];

  List<Map<String, dynamic>> get _filteredCars {
    return _cars.where((car) {
      if (_selectedCategory == 'All') return true;
      return car['category'] == _selectedCategory;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Available Cars',
          style: GoogleFonts.poppins(
            color: Colors.black,
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.favorite_border, color: Colors.black),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Bar
          Padding(
            padding: const EdgeInsets.all(20),
            child: Container(
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(12),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Row(
                children: [
                  const Icon(Icons.search, color: Colors.grey, size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      decoration: InputDecoration(
                        hintText: "Search cars...",
                        hintStyle: GoogleFonts.poppins(
                          color: Colors.grey,
                          fontSize: 14,
                        ),
                        border: InputBorder.none,
                      ),
                    ),
                  ),
                  GestureDetector(
                    onTap: () {
                      _showFilterSheet();
                    },
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.tune,
                        color: Colors.black,
                        size: 20,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Category Tabs
          SizedBox(
            height: 50,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              children: [
                _buildCategoryChip('All'),
                _buildCategoryChip('Sport'),
                _buildCategoryChip('Luxury'),
                _buildCategoryChip('Electric'),
                _buildCategoryChip('SUV'),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Results count
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${_filteredCars.length} cars available',
                  style: GoogleFonts.poppins(
                    fontSize: 14,
                    color: Colors.grey,
                  ),
                ),
                DropdownButton<String>(
                  value: _selectedSort,
                  underline: const SizedBox(),
                  icon: const Icon(Icons.keyboard_arrow_down, size: 20),
                  style: GoogleFonts.poppins(
                    fontSize: 14,
                    color: Colors.black,
                  ),
                  items: ['Popular', 'Price: Low to High', 'Price: High to Low', 'Rating']
                      .map((String value) {
                    return DropdownMenuItem<String>(
                      value: value,
                      child: Text(value),
                    );
                  }).toList(),
                  onChanged: (String? newValue) {
                    setState(() {
                      _selectedSort = newValue!;
                    });
                  },
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Car Grid
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.75,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
              ),
              itemCount: _filteredCars.length,
              itemBuilder: (context, index) {
                final car = _filteredCars[index];
                return _buildCarCard(
                  image: car['image'],
                  name: car['name'],
                  rating: car['rating'],
                  location: car['location'],
                  seats: car['seats'],
                  price: car['price'],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryChip(String label) {
    final isSelected = _selectedCategory == label;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedCategory = label;
        });
      },
      child: Container(
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? Colors.black : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Center(
          child: Text(
            label,
            style: GoogleFonts.poppins(
              color: isSelected ? Colors.white : Colors.black,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCarCard({
    required String image,
    required String name,
    required double rating,
    required String location,
    required int seats,
    required String price,
  }) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => CarDetailScreen(
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
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.grey.shade200),
          boxShadow: [
            BoxShadow(
              color: Colors.grey.withValues(alpha: 0.1),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                  child: Image.network(
                    image,
                    height: 120,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.favorite_border,
                      size: 18,
                      color: Colors.black,
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: GoogleFonts.poppins(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.star, color: Colors.amber, size: 14),
                      const SizedBox(width: 4),
                      Text(
                        rating.toString(),
                        style: GoogleFonts.poppins(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.location_on, color: Colors.grey, size: 14),
                      const SizedBox(width: 2),
                      Expanded(
                        child: Text(
                          location,
                          style: GoogleFonts.poppins(
                            fontSize: 11,
                            color: Colors.grey,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.event_seat, size: 14, color: Colors.grey),
                          const SizedBox(width: 4),
                          Text(
                            '$seats',
                            style: GoogleFonts.poppins(
                              fontSize: 11,
                              color: Colors.grey,
                            ),
                          ),
                        ],
                      ),
                      Text(
                        '$price/Day',
                        style: GoogleFonts.poppins(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        height: MediaQuery.of(context).size.height * 0.6,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Filters',
                  style: GoogleFonts.poppins(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Text(
              'Price Range',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            RangeSlider(
              values: const RangeValues(100, 1000),
              min: 0,
              max: 2000,
              divisions: 20,
              labels: const RangeLabels('\$100', '\$1000'),
              onChanged: (RangeValues values) {},
            ),
            const SizedBox(height: 20),
            Text(
              'Seats',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              children: [
                _buildFilterChip('2 Seats'),
                _buildFilterChip('4 Seats'),
                _buildFilterChip('5+ Seats'),
              ],
            ),
            const Spacer(),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      side: const BorderSide(color: Colors.black),
                    ),
                    child: Text(
                      'Reset',
                      style: GoogleFonts.poppins(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: Colors.black,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(
                      'Apply',
                      style: GoogleFonts.poppins(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterChip(String label) {
    return ChoiceChip(
      label: Text(label),
      selected: false,
      onSelected: (bool selected) {},
      labelStyle: GoogleFonts.poppins(
        fontSize: 14,
      ),
    );
  }
}