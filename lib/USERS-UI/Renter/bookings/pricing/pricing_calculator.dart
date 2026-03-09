// Stub file for PricingCalculator
class BookingPriceBreakdown {
  final double baseRental;
  final double discount;
  final double discountPercentage;
  final double deliveryFee;
  final double insuranceFee;
  final double subtotal;
  final double serviceFee;
  final double total;
  final double totalAmount;
  final double grandTotal;
  final double effectiveDailyRate;
  final double securityDeposit;
  final double pricePerDay;
  final int numberOfDays;

  const BookingPriceBreakdown({
    required this.baseRental,
    this.discount = 0,
    this.discountPercentage = 0,
    this.deliveryFee = 0,
    this.insuranceFee = 0,
    required this.subtotal,
    required this.serviceFee,
    required this.total,
    required this.totalAmount,
    required this.grandTotal,
    required this.effectiveDailyRate,
    this.securityDeposit = 0,
    this.pricePerDay = 0,
    this.numberOfDays = 1,
  });
}

class PricingCalculator {
  static const double weeklyDiscountRate = 0.10;
  static const double monthlyDiscountRate = 0.20;
  static const double deliveryFeeBase = 50.0;
  static const double deliveryFeePerKm = 10.0;
  static const double serviceFeeRate = 0.05;
  static const double securityDepositRate = 0.20;

  static BookingPriceBreakdown calculatePrice({
    required double pricePerDay,
    required int numberOfDays,
    String rentalPeriod = 'Day',
    bool needsDelivery = false,
    double deliveryDistance = 0,
    double insuranceFee = 0,
  }) {
    double baseRental = pricePerDay * numberOfDays;
    double discountPct = 0;
    double discount = 0;

    if (rentalPeriod == 'Weekly') {
      discountPct = weeklyDiscountRate;
      discount = baseRental * discountPct;
    } else if (rentalPeriod == 'Monthly') {
      discountPct = monthlyDiscountRate;
      discount = baseRental * discountPct;
    }

    final double delivery = needsDelivery
        ? deliveryFeeBase + (deliveryDistance * deliveryFeePerKm)
        : 0;

    final double subtotal = baseRental - discount + delivery + insuranceFee;
    final double serviceFee = subtotal * serviceFeeRate;
    final double total = subtotal + serviceFee;
    final double security = total * securityDepositRate;
    final double grand = total + security;
    final double effectiveDailyRate = numberOfDays > 0 ? total / numberOfDays : pricePerDay;

    return BookingPriceBreakdown(
      baseRental: baseRental,
      discount: discount,
      discountPercentage: discountPct * 100,
      deliveryFee: delivery,
      insuranceFee: insuranceFee,
      subtotal: subtotal,
      serviceFee: serviceFee,
      total: total,
      totalAmount: total,
      grandTotal: grand,
      effectiveDailyRate: effectiveDailyRate,
      securityDeposit: security,
      pricePerDay: pricePerDay,
      numberOfDays: numberOfDays,
    );
  }

  static String formatCurrency(double amount) {
    return '₱${amount.toStringAsFixed(2)}';
  }
}
