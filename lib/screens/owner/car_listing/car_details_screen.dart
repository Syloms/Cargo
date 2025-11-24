import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

// Simplified CarListing model for testing
class CarListing {
  String? year;
  String? brand;
  String? model;
  String? bodyStyle;
  String? trim;
  String? plateNumber;
  String? color;
}

class CarDetailsScreen extends StatefulWidget {
  final CarListing? existingListing;

  const CarDetailsScreen({super.key, this.existingListing});

  @override
  State<CarDetailsScreen> createState() => _CarDetailsScreenState();
}

class _CarDetailsScreenState extends State<CarDetailsScreen> {
  late CarListing listing;
  final _plateController = TextEditingController();
  final _colorController = TextEditingController();
  bool _plateIsUnique = false;

  final List<String> years = List.generate(10, (i) => (2025 - i).toString());
  final List<String> brands = ['Audi', 'BAIC', 'BMW', 'BYD', 'Changan', 'Changhe', 'Chery'];
  final Map<String, List<String>> modelsByBrand = {
    'Audi': ['A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8'],
    'BMW': ['1 Series', '2 Series', '3 Series', '4 Series', '5 Series'],
    'BAIC': ['D20', 'X25', 'X35', 'X55'],
    'BYD': ['Atto 3', 'Dolphin', 'Seal', 'Han'],
  };
  final List<String> bodyStyles = ['3-Door Hatchback', '5-Door Hatchback', 'Sedan', 'SUV'];
  final List<String> trims = ['N/A', 'Base', 'Sport', 'Luxury'];

  @override
  void initState() {
    super.initState();
    if (widget.existingListing != null) {
      listing = widget.existingListing!;
    } else {
      listing = CarListing();
    }
    _plateController.text = listing.plateNumber ?? '';
    _colorController.text = listing.color ?? '';
    
    // Add listeners to text controllers
    _plateController.addListener(() {
      setState(() {
        listing.plateNumber = _plateController.text;
        _plateIsUnique = _plateController.text.isNotEmpty;
      });
    });
    
    _colorController.addListener(() {
      setState(() {
        listing.color = _colorController.text;
      });
    });
  }

  @override
  void dispose() {
    _plateController.dispose();
    _colorController.dispose();
    super.dispose();
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
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'What is your car?',
                      style: GoogleFonts.poppins(
                        fontSize: 24,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const SizedBox(height: 32),
                    
                    _buildDropdown('Year', years, listing.year, (value) {
                      setState(() => listing.year = value);
                    }),
                    
                    const SizedBox(height: 20),
                    
                    _buildDropdown('Car Brand', brands, listing.brand, (value) {
                      setState(() {
                        listing.brand = value;
                        listing.model = null; // Reset model when brand changes
                      });
                    }),
                    
                    const SizedBox(height: 20),
                    
                    _buildDropdown(
                      'Model',
                      listing.brand != null && modelsByBrand.containsKey(listing.brand)
                        ? modelsByBrand[listing.brand!]!
                        : ['Select brand first'],
                      listing.model,
                      (value) {
                        if (value != 'Select brand first') {
                          setState(() => listing.model = value);
                        }
                      },
                      enabled: listing.brand != null && modelsByBrand.containsKey(listing.brand),
                    ),
                    
                    const SizedBox(height: 20),
                    
                    _buildDropdown('Body Style', bodyStyles, listing.bodyStyle, (value) {
                      setState(() => listing.bodyStyle = value);
                    }),
                    
                    const SizedBox(height: 20),
                    
                    _buildDropdown('Trim', trims, listing.trim, (value) {
                      setState(() => listing.trim = value);
                    }),
                    
                    const SizedBox(height: 20),
                    
                    _buildTextField(
                      'Plate Number',
                      _plateController,
                    ),
                    
                    if (_plateIsUnique)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle, color: Colors.green, size: 16),
                            const SizedBox(width: 6),
                            Text(
                              'Plate number is unique.',
                              style: GoogleFonts.poppins(
                                fontSize: 13,
                                color: Colors.green[700],
                              ),
                            ),
                          ],
                        ),
                      ),
                    
                    const SizedBox(height: 20),
                    
                    _buildTextField(
                      'Car Color',
                      _colorController,
                    ),
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _canContinue() ? () {
                    // Navigate to next screen
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('All fields are valid! Proceeding...')),
                    );
                  } : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    disabledBackgroundColor: Colors.grey[300],
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  child: Text(
                    'Continue',
                    style: GoogleFonts.poppins(
                      color: _canContinue() ? const Color(0xFFCDFE3D) : Colors.grey[500],
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _canContinue() {
    bool yearValid = listing.year != null && listing.year!.isNotEmpty;
    bool brandValid = listing.brand != null && listing.brand!.isNotEmpty;
    bool modelValid = listing.model != null && listing.model!.isNotEmpty;
    bool bodyStyleValid = listing.bodyStyle != null && listing.bodyStyle!.isNotEmpty;
    bool trimValid = listing.trim != null && listing.trim!.isNotEmpty;
    bool plateValid = listing.plateNumber != null && listing.plateNumber!.isNotEmpty;
    bool colorValid = listing.color != null && listing.color!.isNotEmpty;
    
    return yearValid && brandValid && modelValid && bodyStyleValid && trimValid && plateValid && colorValid;
  }

 Widget _buildDropdown(
  String label, 
  List<String> items, 
  String? value, 
  Function(String?) onChanged,
  {bool enabled = true}
) {
  return Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: GoogleFonts.poppins(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: Colors.black87,
        ),
      ),
      const SizedBox(height: 8),
      Container(
        decoration: BoxDecoration(
          color: enabled ? Colors.grey[100] : Colors.grey[200],
          borderRadius: BorderRadius.circular(12),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<String>(
            value: value,
            isExpanded: true,
            hint: Text(
              'Select $label', 
              style: GoogleFonts.poppins(color: Colors.grey[600]),
            ),
            icon: Icon(
              Icons.keyboard_arrow_down, 
              color: enabled ? Colors.green : Colors.grey,
            ),
            items: [
              if (value == null)
                DropdownMenuItem(
                  value: null,
                  child: Text('Select $label'),
                ),
              ...items.map(
                (item) => DropdownMenuItem(
                  value: item,
                  child: Text(item, style: GoogleFonts.poppins(fontSize: 14)),
                ),
              ),
            ],
            onChanged: enabled ? onChanged : null,
          ),
        ),
      ),
    ],
  );
}


  Widget _buildTextField(String label, TextEditingController controller) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: Colors.black87,
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          style: GoogleFonts.poppins(fontSize: 14),
          decoration: InputDecoration(
            filled: true,
            fillColor: Colors.grey[100],
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide.none,
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          ),
        ),
      ],
    );
  }
}