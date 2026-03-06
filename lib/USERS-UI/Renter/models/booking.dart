class Booking {
  final int bookingId;
  final int carId;
  final int ownerId;
  final String carName;
  final String ownerAvatar;
  final String ownerPhone;

  final String carImage;
  final String location;
  final double? latitude;
  final double? longitude;
  final String pickupDate;
  final String pickupTime;
  final String returnDate;
  final String returnTime;
  final String totalPrice;
  final double pricePerDay;
  final double securityDeposit;
  final String status;
  final String ownerName;
  final String? refundStatus; // Added for refund tracking
  final String? escrowStatus; // Added for escrow tracking
  final bool isReviewed; // Added for review tracking

  Booking({
    required this.bookingId,
    required this.carId,
    required this.ownerAvatar,
    required this.ownerId,
    required this.carName,
    required this.ownerPhone,
    required this.carImage,
    required this.location,
    this.latitude,
    this.longitude,
    required this.pickupDate,
    required this.pickupTime,
    required this.returnDate,
    required this.returnTime,
    required this.totalPrice,
    this.pricePerDay = 0,
    this.securityDeposit = 0,
    required this.status,
    required this.ownerName,
    this.refundStatus, // Added for refund tracking
    this.escrowStatus, // Added for escrow tracking
    this.isReviewed = false, // Added for review tracking
  });

  factory Booking.fromJson(Map<String, dynamic> json) {
  // Safe image handling - ensure empty strings don't cause issues
  final rawCarImage = json['carImage']?.toString() ?? '';
  final rawOwnerAvatar = json['ownerAvatar']?.toString() ?? '';
  
  return Booking(
    bookingId: json['bookingId'],
    carId: json['carId'],
    ownerAvatar: rawOwnerAvatar.trim(), // Trim whitespace
    ownerPhone: json['ownerPhone'] ?? '',
    ownerId: json['ownerId'],
    carName: json['carName'] ?? '',
    carImage: rawCarImage.trim(), // Trim whitespace
    location: json['location'] ?? '',
    latitude: json['latitude'] != null ? (json['latitude'] as num).toDouble() : null,
    longitude: json['longitude'] != null ? (json['longitude'] as num).toDouble() : null,
    pickupDate: json['pickupDate'] ?? '',
    pickupTime: json['pickupTime'] ?? '',
    returnDate: json['returnDate'] ?? '',
    returnTime: json['returnTime'] ?? '',
    totalPrice: (json['totalPrice'] as num).toString(),
    pricePerDay: (json['pricePerDay'] as num?)?.toDouble() ?? 0,
    securityDeposit: (json['securityDeposit'] as num?)?.toDouble() ?? 0,

    // 🔥 Normalize status here
    status: json['status']
        .toString()
        .trim()
        .toLowerCase(),

    ownerName: json['ownerName'] ?? '',
    refundStatus: json['refundStatus']?.toString(), // Added for refund tracking
    escrowStatus: json['escrowStatus']?.toString(), // Added for escrow tracking
    isReviewed: json['isReviewed'] == 1 || json['isReviewed'] == true, // Added for review tracking
  );
}

}
