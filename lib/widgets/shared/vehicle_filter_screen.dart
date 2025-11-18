import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class VehicleFilterScreen extends StatefulWidget {
  const VehicleFilterScreen({Key? key}) : super(key: key);

  @override
  State<VehicleFilterScreen> createState() => _VehicleFilterScreenState();
}

class _VehicleFilterScreenState extends State<VehicleFilterScreen> {
  // Vehicle Type
  String _selectedVehicleType = 'All';
  final List<String> _vehicleTypes = ['All', 'Cars', 'Motorcycles'];

  // Car Types
  String _selectedCarType = 'All Cars';
  final List<String> _carTypes = ['All Cars', 'Regular Cars', 'Luxury Cars'];

  // Motorcycle Types
  String _selectedMotorcycleType = 'All Motorcycles';
  final List<String> _motorcycleTypes = [
    'All Motorcycles',
    'Sport Bikes',
    'Cruisers',
    'Touring',
    'Scooters',
    'Off-Road'
  ];

  // Price Range
  RangeValues _priceRange = const RangeValues(10, 250);

  // Rental Time
  String _selectedRentalTime = 'Day';
  final List<String> _rentalTimes = ['Day', 'Weekly', 'Monthly'];

  // Pick up and Drop Date with Time
  DateTime? _pickupDate;
  DateTime? _dropDate;
  TimeOfDay _pickupTime = const TimeOfDay(hour: 10, minute: 30);
  TimeOfDay _dropTime = const TimeOfDay(hour: 17, minute: 30);

  // Location
  String _location = '2 km to Chicago 60601 Usa';

  // Colors
  final List<Map<String, dynamic>> _colors = [
    {'name': 'White', 'color': Colors.white, 'selected': false},
    {'name': 'Grey', 'color': Colors.grey, 'selected': false},
    {'name': 'Blue', 'color': Colors.blue, 'selected': true},
    {'name': 'Black', 'color': Colors.black, 'selected': false},
  ];

  // Seating Capacity (for cars)
  int _selectedSeats = 4;
  final List<int> _seatOptions = [2, 4, 6, 8];

  // Fuel Type
  String _selectedFuelType = 'Electric';
  final List<String> _fuelTypes = ['Electric', 'Petrol', 'Diesel', 'Hybrid'];

  int _resultCount = 100;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.9,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          // Header
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
                Text(
                  'Filters',
                  style: GoogleFonts.poppins(
                    fontSize: 18,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(width: 48),
              ],
            ),
          ),
          const Divider(height: 1),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Vehicle Type Selection
                  _buildSectionTitle('Vehicle Type'),
                  const SizedBox(height: 12),
                  _buildVehicleTypeChips(),
                  const SizedBox(height: 24),

                  // Type of Cars/Motorcycles
                  if (_selectedVehicleType == 'Cars' || _selectedVehicleType == 'All')
                    ..._buildCarTypeSection(),
                  
                  if (_selectedVehicleType == 'Motorcycles')
                    ..._buildMotorcycleTypeSection(),

                  // Price Range
                  _buildSectionTitle('Price range'),
                  const SizedBox(height: 12),
                  _buildPriceChart(),
                  const SizedBox(height: 12),
                  _buildPriceRangeSlider(),
                  const SizedBox(height: 24),

                  // Rental Time
                  _buildSectionTitle('Rental Time'),
                  const SizedBox(height: 12),
                  _buildRentalTimeChips(),
                  const SizedBox(height: 24),

                  // Pick up and Drop Date
                  _buildSectionTitle('Pick up and Drop Date'),
                  const SizedBox(height: 12),
                  _buildDatePicker(),
                  const SizedBox(height: 24),

                  // Location
                  _buildSectionTitle('Car Location'),
                  const SizedBox(height: 12),
                  _buildLocationField(),
                  const SizedBox(height: 24),

                  // Colors
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _buildSectionTitle('Colors'),
                      TextButton(
                        onPressed: () {},
                        child: Text(
                          'See All',
                          style: GoogleFonts.poppins(
                            color: Colors.grey[600],
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _buildColorOptions(),
                  const SizedBox(height: 24),

                  // Seating Capacity (only for cars)
                  if (_selectedVehicleType != 'Motorcycles') ...[
                    _buildSectionTitle('Sitting Capacity'),
                    const SizedBox(height: 12),
                    _buildSeatingCapacity(),
                    const SizedBox(height: 24),
                  ],

                  // Fuel Type
                  _buildSectionTitle('Fuel Type'),
                  const SizedBox(height: 12),
                  _buildFuelTypeChips(),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),

          // Bottom Actions
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -5),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _clearAll,
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      side: const BorderSide(color: Colors.black),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                    ),
                    child: Text(
                      'Clear All',
                      style: GoogleFonts.poppins(
                        color: Colors.black,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  flex: 2,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(context, _getFilters());
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                    ),
                    child: Text(
                      'Show $_resultCount+ Cars',
                      style: GoogleFonts.poppins(
                        color: const Color(0xFFCDFE3D),
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: GoogleFonts.poppins(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: Colors.black87,
      ),
    );
  }

  Widget _buildVehicleTypeChips() {
    return Row(
      children: _vehicleTypes.map((type) {
        final isSelected = _selectedVehicleType == type;
        return Padding(
          padding: const EdgeInsets.only(right: 12),
          child: ChoiceChip(
            label: Text(type),
            selected: isSelected,
            onSelected: (selected) {
              setState(() => _selectedVehicleType = type);
            },
            labelStyle: GoogleFonts.poppins(
              fontSize: 13,
              color: isSelected ? Colors.white : Colors.black,
              fontWeight: isSelected ? FontWeight.w500 : FontWeight.normal,
            ),
            backgroundColor: Colors.grey[100],
            selectedColor: Colors.black,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          ),
        );
      }).toList(),
    );
  }

  List<Widget> _buildCarTypeSection() {
    return [
      _buildSectionTitle('Type of Cars'),
      const SizedBox(height: 12),
      Row(
        children: _carTypes.map((type) {
          final isSelected = _selectedCarType == type;
          return Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ChoiceChip(
              label: Text(type),
              selected: isSelected,
              onSelected: (selected) {
                setState(() => _selectedCarType = type);
              },
              labelStyle: GoogleFonts.poppins(
                fontSize: 13,
                color: isSelected ? Colors.white : Colors.black,
              ),
              backgroundColor: Colors.grey[100],
              selectedColor: Colors.black,
            ),
          );
        }).toList(),
      ),
      const SizedBox(height: 24),
    ];
  }

  List<Widget> _buildMotorcycleTypeSection() {
    return [
      _buildSectionTitle('Type of Motorcycles'),
      const SizedBox(height: 12),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: _motorcycleTypes.map((type) {
          final isSelected = _selectedMotorcycleType == type;
          return ChoiceChip(
            label: Text(type),
            selected: isSelected,
            onSelected: (selected) {
              setState(() => _selectedMotorcycleType = type);
            },
            labelStyle: GoogleFonts.poppins(
              fontSize: 13,
              color: isSelected ? Colors.white : Colors.black,
            ),
            backgroundColor: Colors.grey[100],
            selectedColor: Colors.black,
          );
        }).toList(),
      ),
      const SizedBox(height: 24),
    ];
  }

  Widget _buildPriceChart() {
    return SizedBox(
      height: 60,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: List.generate(30, (index) {
          final heights = [20, 25, 30, 35, 40, 50, 45, 55, 50, 45, 40, 35, 30, 25, 20];
          final height = heights[index % heights.length].toDouble();
          return Container(
            width: 4,
            height: height,
            decoration: BoxDecoration(
              color: Colors.black,
              borderRadius: BorderRadius.circular(2),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildPriceRangeSlider() {
    return Column(
      children: [
        RangeSlider(
          values: _priceRange,
          min: 0,
          max: 300,
          divisions: 30,
          activeColor: Colors.black,
          inactiveColor: Colors.grey[300],
          onChanged: (values) {
            setState(() => _priceRange = values);
          },
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('₱${_priceRange.start.round()}', style: GoogleFonts.poppins(fontSize: 12)),
              Text('₱${_priceRange.end.round()}+', style: GoogleFonts.poppins(fontSize: 12)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildRentalTimeChips() {
    return Row(
      children: _rentalTimes.map((time) {
        final isSelected = _selectedRentalTime == time;
        return Padding(
          padding: const EdgeInsets.only(right: 12),
          child: ChoiceChip(
            label: Text(time),
            selected: isSelected,
            onSelected: (selected) {
              setState(() => _selectedRentalTime = time);
            },
            labelStyle: GoogleFonts.poppins(
              fontSize: 13,
              color: isSelected ? Colors.white : Colors.grey[600],
            ),
            backgroundColor: Colors.grey[100],
            selectedColor: Colors.black,
          ),
        );
      }).toList(),
    );
  }

  Widget _buildDatePicker() {
    return GestureDetector(
      onTap: () {
        showDialog(
          context: context,
          builder: (BuildContext context) {
            return Dialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: CustomDateTimePicker(
                initialPickupDate: _pickupDate,
                initialDropDate: _dropDate,
                initialPickupTime: _pickupTime,
                initialDropTime: _dropTime,
              ),
            );
          },
        ).then((result) {
          if (result != null && result is Map<String, dynamic>) {
            setState(() {
              _pickupDate = result['pickup'];
              _dropDate = result['drop'];
              _pickupTime = result['pickupTime'];
              _dropTime = result['dropTime'];
            });
          }
        });
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.grey[100],
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Text(
                _pickupDate != null && _dropDate != null
                    ? '${_pickupDate!.day}/${_pickupDate!.month}/${_pickupDate!.year} ${_pickupTime.format(context)} - ${_dropDate!.day}/${_dropDate!.month}/${_dropDate!.year} ${_dropTime.format(context)}'
                    : 'Select pickup and drop date',
                style: GoogleFonts.poppins(fontSize: 13),
              ),
            ),
            const Icon(Icons.calendar_today, size: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationField() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.grey[100],
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(Icons.location_on, size: 18, color: Colors.grey[600]),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              _location,
              style: GoogleFonts.poppins(fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildColorOptions() {
    return Row(
      children: _colors.map((colorData) {
        return Padding(
          padding: const EdgeInsets.only(right: 24),
          child: GestureDetector(
            onTap: () {
              setState(() {
                for (var c in _colors) {
                  c['selected'] = false;
                }
                colorData['selected'] = true;
              });
            },
            child: Column(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: colorData['color'],
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: colorData['selected'] ? Colors.black : Colors.grey[300]!,
                      width: colorData['selected'] ? 3 : 1,
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  colorData['name'],
                  style: GoogleFonts.poppins(fontSize: 11),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildSeatingCapacity() {
    return Row(
      children: _seatOptions.map((seats) {
        final isSelected = _selectedSeats == seats;
        return Padding(
          padding: const EdgeInsets.only(right: 12),
          child: ChoiceChip(
            label: Text('$seats'),
            selected: isSelected,
            onSelected: (selected) {
              setState(() => _selectedSeats = seats);
            },
            labelStyle: GoogleFonts.poppins(
              fontSize: 13,
              color: isSelected ? Colors.white : Colors.black,
            ),
            backgroundColor: Colors.grey[100],
            selectedColor: Colors.black,
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildFuelTypeChips() {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: _fuelTypes.map((fuel) {
        final isSelected = _selectedFuelType == fuel;
        return ChoiceChip(
          label: Text(fuel),
          selected: isSelected,
          onSelected: (selected) {
            setState(() => _selectedFuelType = fuel);
          },
          labelStyle: GoogleFonts.poppins(
            fontSize: 13,
            color: isSelected ? Colors.white : Colors.black,
          ),
          backgroundColor: Colors.grey[100],
          selectedColor: Colors.black,
        );
      }).toList(),
    );
  }

  void _clearAll() {
    setState(() {
      _selectedVehicleType = 'All';
      _selectedCarType = 'All Cars';
      _selectedMotorcycleType = 'All Motorcycles';
      _priceRange = const RangeValues(10, 250);
      _selectedRentalTime = 'Day';
      _pickupDate = null;
      _dropDate = null;
      _selectedSeats = 4;
      _selectedFuelType = 'Electric';
      for (var c in _colors) {
        c['selected'] = false;
      }
    });
  }

  Map<String, dynamic> _getFilters() {
    return {
      'vehicleType': _selectedVehicleType,
      'carType': _selectedCarType,
      'motorcycleType': _selectedMotorcycleType,
      'priceRange': _priceRange,
      'rentalTime': _selectedRentalTime,
      'pickupDate': _pickupDate,
      'dropDate': _dropDate,
      'pickupTime': _pickupTime,
      'dropTime': _dropTime,
      'location': _location,
      'selectedColor': _colors.firstWhere((c) => c['selected'], orElse: () => _colors[0])['name'],
      'seats': _selectedSeats,
      'fuelType': _selectedFuelType,
    };
  }
}

// Custom Date Time Picker Widget
class CustomDateTimePicker extends StatefulWidget {
  final DateTime? initialPickupDate;
  final DateTime? initialDropDate;
  final TimeOfDay initialPickupTime;
  final TimeOfDay initialDropTime;
  
  const CustomDateTimePicker({
    Key? key,
    this.initialPickupDate,
    this.initialDropDate,
    required this.initialPickupTime,
    required this.initialDropTime,
  }) : super(key: key);

  @override
  State<CustomDateTimePicker> createState() => _CustomDateTimePickerState();
}

class _CustomDateTimePickerState extends State<CustomDateTimePicker> {
  late int startHour;
  late int startMinute;
  late bool startIsAM;
  
  late int endHour;
  late int endMinute;
  late bool endIsAM;
  
  DateTime? pickupDate;
  DateTime? dropDate;
  DateTime displayMonth = DateTime.now();
  
  @override
  void initState() {
    super.initState();
    pickupDate = widget.initialPickupDate;
    dropDate = widget.initialDropDate;
    
    // Initialize pickup time
    startHour = widget.initialPickupTime.hourOfPeriod == 0 ? 12 : widget.initialPickupTime.hourOfPeriod;
    startMinute = widget.initialPickupTime.minute;
    startIsAM = widget.initialPickupTime.period == DayPeriod.am;
    
    // Initialize drop time
    endHour = widget.initialDropTime.hourOfPeriod == 0 ? 12 : widget.initialDropTime.hourOfPeriod;
    endMinute = widget.initialDropTime.minute;
    endIsAM = widget.initialDropTime.period == DayPeriod.am;
    
    if (pickupDate != null) {
      displayMonth = pickupDate!;
    }
  }
  
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      constraints: const BoxConstraints(maxWidth: 400),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Time Selection
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.grey[100],
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Time', style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w500)),
                const SizedBox(width: 20),
                GestureDetector(
                  onTap: () => _showTimePicker(true),
                  child: _buildTimeDisplay(true),
                ),
                const SizedBox(width: 12),
                GestureDetector(
                  onTap: () => _showTimePicker(false),
                  child: _buildTimeDisplay(false),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          
          // Month/Year Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                icon: const Icon(Icons.chevron_left),
                onPressed: () {
                  setState(() {
                    displayMonth = DateTime(displayMonth.year, displayMonth.month - 1);
                  });
                },
              ),
              Text(
                '${_getMonthName(displayMonth.month)} ${displayMonth.year}',
                style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              IconButton(
                icon: const Icon(Icons.chevron_right),
                onPressed: () {
                  setState(() {
                    displayMonth = DateTime(displayMonth.year, displayMonth.month + 1);
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          
          // Weekday Headers
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) {
              return SizedBox(
                width: 40,
                child: Center(
                  child: Text(
                    day,
                    style: GoogleFonts.poppins(fontSize: 12, fontWeight: FontWeight.w500),
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 12),
          
          // Calendar Grid
          _buildCalendarGrid(),
          const SizedBox(height: 20),
          
          // Action Buttons
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    side: const BorderSide(color: Colors.black),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
                  ),
                  child: Text(
                    'Cancel',
                    style: GoogleFonts.poppins(color: Colors.black, fontWeight: FontWeight.w500),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    if (pickupDate != null && dropDate != null) {
                      // Convert hours to 24-hour format for TimeOfDay
                      int pickupHour24 = startIsAM 
                          ? (startHour == 12 ? 0 : startHour)
                          : (startHour == 12 ? 12 : startHour + 12);
                      int dropHour24 = endIsAM 
                          ? (endHour == 12 ? 0 : endHour)
                          : (endHour == 12 ? 12 : endHour + 12);
                      
                      Navigator.pop(context, {
                        'pickup': pickupDate,
                        'drop': dropDate,
                        'pickupTime': TimeOfDay(hour: pickupHour24, minute: startMinute),
                        'dropTime': TimeOfDay(hour: dropHour24, minute: endMinute),
                      });
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
                  ),
                  child: Text(
                    'Done',
                    style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w500),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
  
  Widget _buildTimeDisplay(bool isStartTime) {
    final hour = isStartTime ? startHour : endHour;
    final minute = isStartTime ? startMinute : endMinute;
    final isAM = isStartTime ? startIsAM : endIsAM;
    final isSelected = isStartTime;
    
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: isSelected ? Colors.black : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.grey[300]!),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.access_time,
            size: 16,
            color: isSelected ? Colors.white : Colors.black,
          ),
          const SizedBox(width: 6),
          Text(
            '${hour.toString().padLeft(2, '0')} : ${minute.toString().padLeft(2, '0')} ${isAM ? 'am' : 'pm'}',
            style: GoogleFonts.poppins(
              fontSize: 12,
              color: isSelected ? Colors.white : Colors.black,
            ),
          ),
        ],
      ),
    );
  }
  
  void _showTimePicker(bool isStartTime) async {
    int tempHour = isStartTime ? startHour : endHour;
    int tempMinute = isStartTime ? startMinute : endMinute;
    bool tempIsAM = isStartTime ? startIsAM : endIsAM;
    
    await showDialog(
      context: context,
      builder: (BuildContext dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(
                isStartTime ? 'Select Pickup Time' : 'Select Drop-off Time',
                style: GoogleFonts.poppins(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      // Hour selector
                      Column(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_up),
                            onPressed: () {
                              setDialogState(() {
                                tempHour = tempHour == 12 ? 1 : tempHour + 1;
                              });
                            },
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: Colors.grey[300]!),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              tempHour.toString().padLeft(2, '0'),
                              style: GoogleFonts.poppins(fontSize: 24, fontWeight: FontWeight.w600),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_down),
                            onPressed: () {
                              setDialogState(() {
                                tempHour = tempHour == 1 ? 12 : tempHour - 1;
                              });
                            },
                          ),
                        ],
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Text(
                          ':',
                          style: GoogleFonts.poppins(fontSize: 24, fontWeight: FontWeight.w600),
                        ),
                      ),
                      // Minute selector
                      Column(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_up),
                            onPressed: () {
                              setDialogState(() {
                                tempMinute = (tempMinute + 5) % 60;
                              });
                            },
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: Colors.grey[300]!),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              tempMinute.toString().padLeft(2, '0'),
                              style: GoogleFonts.poppins(fontSize: 24, fontWeight: FontWeight.w600),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_down),
                            onPressed: () {
                              setDialogState(() {
                                tempMinute = (tempMinute - 5 + 60) % 60;
                              });
                            },
                          ),
                        ],
                      ),
                      const SizedBox(width: 16),
                      // AM/PM selector
                      Column(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_up),
                            onPressed: () {
                              setDialogState(() {
                                tempIsAM = !tempIsAM;
                              });
                            },
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: Colors.grey[300]!),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              tempIsAM ? 'AM' : 'PM',
                              style: GoogleFonts.poppins(fontSize: 20, fontWeight: FontWeight.w600),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.keyboard_arrow_down),
                            onPressed: () {
                              setDialogState(() {
                                tempIsAM = !tempIsAM;
                              });
                            },
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: Text('Cancel', style: GoogleFonts.poppins(color: Colors.grey[600])),
                ),
                ElevatedButton(
                  onPressed: () {
                    Navigator.pop(dialogContext);
                    setState(() {
                      if (isStartTime) {
                        startHour = tempHour;
                        startMinute = tempMinute;
                        startIsAM = tempIsAM;
                      } else {
                        endHour = tempHour;
                        endMinute = tempMinute;
                        endIsAM = tempIsAM;
                      }
                    });
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: Text('OK', style: GoogleFonts.poppins(color: Colors.white)),
                ),
              ],
            );
          },
        );
      },
    );
  }
  
  Widget _buildCalendarGrid() {
    final firstDayOfMonth = DateTime(displayMonth.year, displayMonth.month, 1);
    final lastDayOfMonth = DateTime(displayMonth.year, displayMonth.month + 1, 0);
    final startWeekday = firstDayOfMonth.weekday % 7;
    final daysInMonth = lastDayOfMonth.day;
    
    List<Widget> dayWidgets = [];
    
    // Empty cells before the first day
    for (int i = 0; i < startWeekday; i++) {
      dayWidgets.add(const SizedBox(width: 40, height: 40));
    }
    
    // Days of the month
    for (int day = 1; day <= daysInMonth; day++) {
      final date = DateTime(displayMonth.year, displayMonth.month, day);
      final isPickup = pickupDate != null && 
                       date.year == pickupDate!.year && 
                       date.month == pickupDate!.month && 
                       date.day == pickupDate!.day;
      final isDrop = dropDate != null && 
                     date.year == dropDate!.year && 
                     date.month == dropDate!.month && 
                     date.day == dropDate!.day;
      final isInRange = pickupDate != null && dropDate != null &&
                        date.isAfter(pickupDate!) && 
                        date.isBefore(dropDate!);
      final isToday = date.year == DateTime.now().year && 
                     date.month == DateTime.now().month && 
                     date.day == DateTime.now().day;
      
      dayWidgets.add(
        GestureDetector(
          onTap: () {
            setState(() {
              if (pickupDate == null || (pickupDate != null && dropDate != null)) {
                // Start new selection
                pickupDate = date;
                dropDate = null;
              } else if (date.isAfter(pickupDate!)) {
                // Set drop date
                dropDate = date;
              } else {
                // Reset if selecting before pickup
                pickupDate = date;
                dropDate = null;
              }
            });
          },
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: (isPickup || isDrop) 
                  ? Colors.black 
                  : isInRange 
                      ? Colors.grey[800] 
                      : isToday 
                          ? Colors.grey[300] 
                          : Colors.transparent,
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Text(
                '$day',
                style: GoogleFonts.poppins(
                  fontSize: 12,
                  color: (isPickup || isDrop || isInRange) ? Colors.white : Colors.black,
                  fontWeight: (isPickup || isDrop) ? FontWeight.w600 : FontWeight.normal,
                ),
              ),
            ),
          ),
        ),
      );
    }
    
    return Wrap(
      spacing: 4,
      runSpacing: 4,
      children: dayWidgets,
    );
  }
  
  String _getMonthName(int month) {
    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[month - 1];
  }
}