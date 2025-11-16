class CarListing {
  // Basic Details
  String? year;
  String? brand;
  String? model;
  String? bodyStyle;
  String? trim;
  String? plateNumber;
  String? color;

  // Preferences
  String? advanceNotice;
  String? minTripDuration;
  String? maxTripDuration;
  List<String> deliveryTypes;

  // Features
  List<String> features;

  // Rules
  List<String> rules;
  bool hasUnlimitedMileage;
  int? mileageLimit;

  // Pricing
  double? dailyRate;

  // Location
  String? address;
  double? latitude;
  double? longitude;

  // Photos
  List<String> photoUrls;

 CarListing({
  this.year,
  this.brand,
  this.model,
  this.bodyStyle,
  this.trim,
  this.plateNumber,
  this.color,
  this.advanceNotice,
  this.minTripDuration,
  this.maxTripDuration,
  List<String>? deliveryTypes,
  List<String>? features,
  List<String>? rules,
  this.hasUnlimitedMileage = true,
  this.mileageLimit,
  this.dailyRate,
  this.address,
  this.latitude,
  this.longitude,
  List<String>? photoUrls,
})  : deliveryTypes = deliveryTypes != null ? List<String>.from(deliveryTypes) : <String>[],
      features = features != null ? List<String>.from(features) : <String>[],
      rules = rules != null ? List<String>.from(rules) : <String>[],
      photoUrls = photoUrls != null ? List<String>.from(photoUrls) : <String>[];
      
  Map<String, dynamic> toJson() {
    return {
      'year': year,
      'brand': brand,
      'model': model,
      'bodyStyle': bodyStyle,
      'trim': trim,
      'plateNumber': plateNumber,
      'color': color,
      'advanceNotice': advanceNotice,
      'minTripDuration': minTripDuration,
      'maxTripDuration': maxTripDuration,
      'deliveryTypes': deliveryTypes,
      'features': features,
      'rules': rules,
      'hasUnlimitedMileage': hasUnlimitedMileage,
      'mileageLimit': mileageLimit,
      'dailyRate': dailyRate,
      'address': address,
      'latitude': latitude,
      'longitude': longitude,
      'photoUrls': photoUrls,
    };
  }
}