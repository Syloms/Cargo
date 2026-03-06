import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import './status_helper.dart';
import 'package:cargo/widgets/optimized_network_image.dart';

class CarCard extends StatelessWidget {
  final Map<String, dynamic> car;
  final VoidCallback onTap;

  const CarCard({
    super.key,
    required this.car,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final imageUrl = car['image'];
    final status = car["status"] ?? "Unknown";

    final colors = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        borderRadius: BorderRadius.circular(16),
       border: Border.all(
  color: Theme.of(context).brightness == Brightness.dark
      ? Colors.transparent   // no white border in dark mode
      : Colors.grey.shade300, // soft border in light mode
),

        boxShadow: isDark
            ? []
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha :0.06),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
      ),
      child: Column(
        children: [
          // Image with Preview
          _buildCarImage(context, imageUrl, status, isDark, colors),

          // Car Details
          Expanded(
            child: InkWell(
              onTap: onTap,
              borderRadius:
                  const BorderRadius.vertical(bottom: Radius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      "${car['brand']} ${car['model']}",
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.poppins(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                        color: isDark
                            ? colors.onSurface
                            : Colors.black87,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(
                          Icons.payments_outlined,
                          size: 14,
                          color: isDark
                              ? colors.onSurface.withValues(alpha :0.7)
                              : Colors.grey.shade600,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          "₱ ${car['price_per_day']}/day",
                          style: GoogleFonts.poppins(
                            color: isDark
                                ? colors.onSurface.withValues(alpha :0.8)
                                : Colors.grey.shade700,
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),
                  ],
                ),
              ),
            ),
          )
        ],
      ),
    );
  }

  Widget _buildCarImage(
    BuildContext context,
    String imageUrl,
    String status,
    bool isDark,
    ColorScheme colors,
  ) {
    return GestureDetector(
      onTap: () => _showImagePreview(context, imageUrl, isDark, colors),
      child: AspectRatio(
        aspectRatio: 1.4,
        child: Stack(
          fit: StackFit.expand,
          children: [
          OptimizedNetworkImage(
            imageUrl: imageUrl,
            width: double.infinity,
            fit: BoxFit.cover,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            errorIcon: Icons.directions_car,
            errorIconSize: 40,
          ),
          Positioned(
            top: 10,
            left: 10,
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: StatusHelper.getStatusColor(status),
                borderRadius: BorderRadius.circular(10),
                boxShadow: [
                  BoxShadow(
                    color: StatusHelper.getStatusColor(status)
                        .withValues(alpha :0.3),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
             child: DefaultTextStyle(
                style: GoogleFonts.poppins(
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 0.5,
                  color: Colors.white, // FORCE white always
                ),
                child: Text(status.toUpperCase()),
              ),


            ),
          ),
        ],
        ),
      ),
    );
  }


  void _showImagePreview(
    BuildContext context,
    String imageUrl,
    bool isDark,
    ColorScheme colors,
  ) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.transparent,
        child: OptimizedNetworkImage(
          imageUrl: imageUrl,
          fit: BoxFit.cover,
          borderRadius: BorderRadius.circular(16),
          errorIcon: Icons.directions_car,
          errorIconSize: 64,
        ),
      ),
    );
  }
}
