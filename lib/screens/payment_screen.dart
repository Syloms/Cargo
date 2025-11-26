import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../services/paymongo_service.dart';

class PaymentScreen extends StatefulWidget {
  final double rentalAmount;
  final String carName;
  final String bookingId;

  const PaymentScreen({
    Key? key,
    required this.rentalAmount,
    required this.carName,
    required this.bookingId,
  }) : super(key: key);

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final PayMongoService _paymongoService = PayMongoService();
  bool _isLoading = false;
  String? _errorMessage;

  // Payment method selection
  String _selectedPaymentMethod = 'gcash';

  Future<void> _processGCashPayment() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // Convert amount to centavos
      final amountInCentavos = PayMongoService.toCentavos(widget.rentalAmount);

      // Create GCash source
      final sourceResponse = await _paymongoService.createGCashSource(
        amount: amountInCentavos,
        description: 'Car Rental: ${widget.carName} - Booking #${widget.bookingId}',
      );

      // Extract checkout URL
      final checkoutUrl = sourceResponse['data']['attributes']['redirect']['checkout_url'];
      final sourceId = sourceResponse['data']['id'];

      // Navigate to WebView for payment
      if (mounted) {
        final result = await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => GCashWebViewScreen(
              checkoutUrl: checkoutUrl,
              sourceId: sourceId,
            ),
          ),
        );

        if (result == 'success') {
          _showSuccessDialog();
        } else if (result == 'failed') {
          setState(() {
            _errorMessage = 'Payment was cancelled or failed';
          });
        }
      }
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Colors.green, size: 30),
            SizedBox(width: 10),
            Text('Payment Successful!'),
          ],
        ),
        content: Text(
          'Your payment of ₱${widget.rentalAmount.toStringAsFixed(2)} has been processed successfully.\n\nBooking ID: ${widget.bookingId}',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context).pop();
              Navigator.of(context).pop('success'); // Return to previous screen
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Payment'),
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 20),
                  Text('Processing payment...'),
                ],
              ),
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Booking Summary Card
                  Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Booking Summary',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 15),
                          _buildInfoRow('Car:', widget.carName),
                          _buildInfoRow('Booking ID:', widget.bookingId),
                          const Divider(height: 30),
                          _buildInfoRow(
                            'Total Amount:',
                            '₱${widget.rentalAmount.toStringAsFixed(2)}',
                            isBold: true,
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 30),

                  // Payment Method Selection
                  const Text(
                    'Select Payment Method',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 15),

                  // GCash Option
                  _buildPaymentMethodCard(
                    method: 'gcash',
                    title: 'GCash',
                    subtitle: 'Pay securely with GCash',
                    icon: Icons.account_balance_wallet,
                    color: Colors.blue,
                  ),

                  const SizedBox(height: 10),

                  // Future payment methods can be added here
                  // _buildPaymentMethodCard(
                  //   method: 'paymaya',
                  //   title: 'PayMaya',
                  //   subtitle: 'Pay with PayMaya',
                  //   icon: Icons.payment,
                  //   color: Colors.green,
                  // ),

                  if (_errorMessage != null) ...[
                    const SizedBox(height: 20),
                    Container(
                      padding: const EdgeInsets.all(15),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.error_outline, color: Colors.red.shade700),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              _errorMessage!,
                              style: TextStyle(color: Colors.red.shade700),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 30),

                  // Pay Now Button
                  SizedBox(
                    width: double.infinity,
                    height: 55,
                    child: ElevatedButton(
                      onPressed: _isLoading ? null : _processGCashPayment,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.blue,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      child: const Text(
                        'Pay with GCash',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Security Note
                  Container(
                    padding: const EdgeInsets.all(15),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade100,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.security, color: Colors.grey.shade600, size: 20),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Your payment is secured and encrypted by PayMongo',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey.shade600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildInfoRow(String label, String value, {bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey.shade700,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: 14,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              color: isBold ? Colors.black : Colors.grey.shade700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethodCard({
    required String method,
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
  }) {
    final isSelected = _selectedPaymentMethod == method;

    return InkWell(
      onTap: () {
        setState(() {
          _selectedPaymentMethod = method;
        });
      },
      child: Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          border: Border.all(
            color: isSelected ? color : Colors.grey.shade300,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(10),
          color: isSelected ? color.withOpacity(0.05) : Colors.white,
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 30),
            ),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade600,
                    ),
                  ),
                ],
              ),
            ),
            Radio<String>(
              value: method,
              groupValue: _selectedPaymentMethod,
              onChanged: (value) {
                setState(() {
                  _selectedPaymentMethod = value!;
                });
              },
              activeColor: color,
            ),
          ],
        ),
      ),
    );
  }
}

// WebView Screen for GCash Checkout
class GCashWebViewScreen extends StatefulWidget {
  final String checkoutUrl;
  final String sourceId;

  const GCashWebViewScreen({
    Key? key,
    required this.checkoutUrl,
    required this.sourceId,
  }) : super(key: key);

  @override
  State<GCashWebViewScreen> createState() => _GCashWebViewScreenState();
}

class _GCashWebViewScreenState extends State<GCashWebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            setState(() => _isLoading = true);
            _checkPaymentStatus(url);
          },
          onPageFinished: (url) {
            setState(() => _isLoading = false);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.checkoutUrl));
  }

  void _checkPaymentStatus(String url) {
    if (url.contains('payment/success') || url.contains('status=success')) {
      Navigator.pop(context, 'success');
    } else if (url.contains('payment/failed') || url.contains('status=failed')) {
      Navigator.pop(context, 'failed');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('GCash Payment'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.pop(context, 'cancelled'),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_isLoading)
            const Center(
              child: CircularProgressIndicator(),
            ),
        ],
      ),
    );
  }
}