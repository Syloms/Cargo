// Stub file for OptimizedNetworkImage
import 'package:flutter/material.dart';

class OptimizedNetworkImage extends StatelessWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;
  final IconData errorIcon;
  final double errorIconSize;

  const OptimizedNetworkImage({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
    this.errorIcon = Icons.image,
    this.errorIconSize = 24,
  });

  @override
  Widget build(BuildContext context) {
    Widget image = Image.network(
      imageUrl,
      width: width,
      height: height,
      fit: fit,
      errorBuilder: (_, __, ___) => Center(
        child: Icon(errorIcon, size: errorIconSize, color: Colors.grey),
      ),
    );

    if (borderRadius != null) {
      return ClipRRect(
        borderRadius: borderRadius!,
        child: image,
      );
    }

    return image;
  }
}
