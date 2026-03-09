// Stub file for InsuranceService
class InsuranceService {
  static String formatCurrency(double amount) {
    return '₱${amount.toStringAsFixed(2)}';
  }

  Future<Map<String, dynamic>> getInsuranceOptions() async {
    return {'success': false, 'options': []};
  }
}
