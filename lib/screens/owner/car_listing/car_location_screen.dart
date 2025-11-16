import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import '../../../models/car_listing.dart';
import 'upload_documents_screen.dart';

class CarLocationScreen extends StatefulWidget {
  final CarListing listing;

  const CarLocationScreen({super.key, required this.listing});

  @override
  State<CarLocationScreen> createState() => _CarLocationScreenState();
}

class _CarLocationScreenState extends State<CarLocationScreen> {
  final _addressController = TextEditingController();
  bool _showMap = false;
  final MapController _mapController = MapController();
  LatLng _currentPosition = LatLng(14.5995, 120.9842); // Manila default
  List<Marker> _markers = [];
  bool _isLoadingLocation = false;
  
  // Replace with your MapTiler API key
  final String _mapTilerApiKey = 'YGJxmPnRtlTHI1endzDH';

  @override
  void initState() {
    super.initState();
    _addressController.text = widget.listing.address ?? '';
    if (widget.listing.latitude != null && widget.listing.longitude != null) {
      _currentPosition = LatLng(widget.listing.latitude!, widget.listing.longitude!);
      _addMarker(_currentPosition);
    } else {
      _addMarker(_currentPosition);
    }
    _requestLocationPermission();
  }

  Future<void> _requestLocationPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
  }

  Future<void> _getCurrentLocation() async {
    setState(() => _isLoadingLocation = true);
    
    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      
      LatLng newPosition = LatLng(position.latitude, position.longitude);
      
      // Get address from coordinates
      List<Placemark> placemarks = await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      );
      
      if (placemarks.isNotEmpty) {
        Placemark place = placemarks[0];
        String address = '${place.street}, ${place.locality}, ${place.administrativeArea}';
        _addressController.text = address;
        widget.listing.address = address;
      }
      
      setState(() {
        _currentPosition = newPosition;
        widget.listing.latitude = position.latitude;
        widget.listing.longitude = position.longitude;
        _addMarker(newPosition);
      });
      
      _mapController.move(newPosition, 15);
    } catch (e) {
      debugPrint('Error getting location: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not get current location: $e')),
        );
      }
    } finally {
      setState(() => _isLoadingLocation = false);
    }
  }

  void _addMarker(LatLng position) {
    setState(() {
      _markers = [
        Marker(
          point: position,
          width: 40,
          height: 40,
          child: const Icon(
            Icons.location_pin,
            color: Colors.red,
            size: 40,
          ),
        ),
      ];
    });
  }

  Future<void> _updateAddressFromCoordinates(LatLng position) async {
    try {
      List<Placemark> placemarks = await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      );
      
      if (placemarks.isNotEmpty) {
        Placemark place = placemarks[0];
        String address = '${place.street}, ${place.locality}, ${place.administrativeArea}';
        setState(() {
          _addressController.text = address;
          widget.listing.address = address;
        });
      }
    } catch (e) {
      debugPrint('Error getting address: $e');
    }
  }

  Future<void> _searchAddress(String address) async {
    if (address.isEmpty) return;
    
    try {
      List<Location> locations = await locationFromAddress(address);
      
      if (locations.isNotEmpty) {
        Location location = locations[0];
        LatLng newPosition = LatLng(location.latitude, location.longitude);
        
        setState(() {
          _currentPosition = newPosition;
          widget.listing.latitude = location.latitude;
          widget.listing.longitude = location.longitude;
          _addMarker(newPosition);
          _showMap = true;
        });
        
        _mapController.move(newPosition, 15);
      }
    } catch (e) {
      debugPrint('Error searching address: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not find address')),
        );
      }
    }
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
          'Location',
          style: GoogleFonts.poppins(
            color: Colors.black,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
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
                      'Your Location',
                      style: GoogleFonts.poppins(
                        fontSize: 20,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[700],
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Set the pick-up and return address for your car.',
                      style: GoogleFonts.poppins(
                        fontSize: 14,
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 24),
                    
                    TextField(
                      controller: _addressController,
                      style: GoogleFonts.poppins(fontSize: 14),
                      decoration: InputDecoration(
                        prefixIcon: const Icon(Icons.location_on, color: Colors.green),
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.search, color: Colors.green),
                          onPressed: () => _searchAddress(_addressController.text),
                        ),
                        hintText: 'Please Input Complete Address',
                        hintStyle: GoogleFonts.poppins(color: Colors.grey[400]),
                        filled: true,
                        fillColor: Colors.grey[100],
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                      ),
                      onChanged: (value) {
                        widget.listing.address = value;
                      },
                    ),
                    
                    const SizedBox(height: 16),
                    
                    GestureDetector(
                      onTap: () {
                        setState(() => _showMap = !_showMap);
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        decoration: BoxDecoration(
                          border: Border(
                            bottom: BorderSide(color: Colors.grey[300]!),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Pin your Location (for better accuracy)',
                              style: GoogleFonts.poppins(fontSize: 14),
                            ),
                            Icon(
                              _showMap ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                              color: Colors.green,
                            ),
                          ],
                        ),
                      ),
                    ),
                    
                    if (_showMap) ...[
                      const SizedBox(height: 16),
                      Container(
                        height: 300,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.grey[300]!),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: FlutterMap(
                            mapController: _mapController,
                            options: MapOptions(
                              initialCenter: _currentPosition,
                              initialZoom: 15,
                              onTap: (tapPosition, point) {
                                setState(() {
                                  widget.listing.latitude = point.latitude;
                                  widget.listing.longitude = point.longitude;
                                  _currentPosition = point;
                                  _addMarker(point);
                                });
                                _updateAddressFromCoordinates(point);
                              },
                            ),
                            children: [
                              TileLayer(
                                urlTemplate: 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=$_mapTilerApiKey',
                                userAgentPackageName: 'com.example.cargo',
                              ),
                              MarkerLayer(
                                markers: _markers,
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      
                      // Current location button
                      ElevatedButton.icon(
                        onPressed: _isLoadingLocation ? null : _getCurrentLocation,
                        icon: _isLoadingLocation
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                ),
                              )
                            : const Icon(Icons.my_location, size: 20),
                        label: Text(
                          _isLoadingLocation ? 'Getting location...' : 'Use Current Location',
                          style: GoogleFonts.poppins(fontSize: 14),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                      ),
                      
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.grey[100],
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline, size: 16, color: Colors.grey),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Lat/Lng: ${widget.listing.latitude?.toStringAsFixed(6) ?? '0.0'}, ${widget.listing.longitude?.toStringAsFixed(6) ?? '0.0'}',
                                style: GoogleFonts.poppins(
                                  fontSize: 12,
                                  color: Colors.grey[700],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: widget.listing.address != null && widget.listing.address!.isNotEmpty
                      ? () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => UploadDocumentsScreen(listing: widget.listing),
                            ),
                          );
                        }
                      : null,
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
                      color: widget.listing.address != null && widget.listing.address!.isNotEmpty
                          ? const Color(0xFFCDFE3D)
                          : Colors.grey[500],
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
}