// Stub file for OverdueBooking model
class OverdueBooking {
  final int bookingId;
  final int userId;
  final int ownerId;
  final String renterName;
  final String renterContact;
  final String ownerName;
  final String ownerContact;
  final String vehicleName;
  final String vehicleImage;
  final DateTime returnDate;
  final String returnTime;
  final String overdueStatus;
  final int daysOverdue;
  final double hoursOverdue;
  final double lateFeeAmount;
  final bool lateFeeCharged;
  final double totalAmount;
  final double totalDue;
  final bool isRentalPaid;
  final double rentalFee;

  const OverdueBooking({
    required this.bookingId,
    required this.userId,
    required this.ownerId,
    required this.renterName,
    required this.renterContact,
    required this.ownerName,
    required this.ownerContact,
    required this.vehicleName,
    required this.vehicleImage,
    required this.returnDate,
    required this.returnTime,
    required this.overdueStatus,
    required this.daysOverdue,
    required this.hoursOverdue,
    required this.lateFeeAmount,
    required this.lateFeeCharged,
    required this.totalAmount,
    required this.totalDue,
    required this.isRentalPaid,
    this.rentalFee = 0,
  });

  factory OverdueBooking.fromJson(Map<String, dynamic> json) {
    return OverdueBooking(
      bookingId: int.tryParse(json['booking_id']?.toString() ?? '0') ?? 0,
      userId: int.tryParse(json['user_id']?.toString() ?? '0') ?? 0,
      ownerId: int.tryParse(json['owner_id']?.toString() ?? '0') ?? 0,
      renterName: json['renter_name']?.toString() ?? '',
      renterContact: json['renter_contact']?.toString() ?? '',
      ownerName: json['owner_name']?.toString() ?? '',
      ownerContact: json['owner_contact']?.toString() ?? '',
      vehicleName: json['vehicle_name']?.toString() ?? '',
      vehicleImage: json['vehicle_image']?.toString() ?? '',
      returnDate: DateTime.tryParse(json['return_date']?.toString() ?? '') ?? DateTime.now(),
      returnTime: json['return_time']?.toString() ?? '',
      overdueStatus: json['overdue_status']?.toString() ?? '',
      daysOverdue: int.tryParse(json['days_overdue']?.toString() ?? '0') ?? 0,
      hoursOverdue: double.tryParse(json['hours_overdue']?.toString() ?? '0') ?? 0,
      lateFeeAmount: double.tryParse(json['late_fee_amount']?.toString() ?? '0') ?? 0,
      lateFeeCharged: json['late_fee_charged'] == true || json['late_fee_charged'] == 1,
      totalAmount: double.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      totalDue: double.tryParse(json['total_due']?.toString() ?? '0') ?? 0,
      isRentalPaid: json['is_rental_paid'] == true || json['is_rental_paid'] == 1,
      rentalFee: double.tryParse(json['rental_fee']?.toString() ?? '0') ?? 0,
    );
  }
}
