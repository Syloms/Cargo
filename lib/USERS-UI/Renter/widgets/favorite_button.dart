// Stub file for FavoriteButton
import 'package:flutter/material.dart';

class FavoriteButton extends StatelessWidget {
  final int? carId;
  final int? vehicleId;
  final String vehicleType;
  final bool isFavorite;
  final double? size;
  final ValueChanged<bool>? onChanged;

  const FavoriteButton({
    super.key,
    this.carId,
    this.vehicleId,
    this.vehicleType = 'car',
    this.isFavorite = false,
    this.size,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: Icon(
        isFavorite ? Icons.favorite : Icons.favorite_border,
        color: isFavorite ? Colors.red : null,
        size: size,
      ),
      onPressed: () => onChanged?.call(!isFavorite),
    );
  }
}
