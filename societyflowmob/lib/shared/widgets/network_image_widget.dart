import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../core/services/server_discovery.dart';

/// Resolves a relative path like "/storage/notices/abc.jpg" to a full URL
/// using the currently discovered server base URL.
/// Full URLs (http/https) are returned as-is.
String resolveImageUrl(String path) {
  if (path.startsWith('http')) return path; // external URL — keep as-is
  // Relative path — prepend server base URL (strip /api/v1 suffix)
  final base = ServerDiscovery.currentBaseUrl
      .replaceAll('/api/v1', '')
      .replaceAll('/api', '');
  return '${base.trimRight()}$path';
}

/// Network image with disk cache, shimmer loading, and error fallback.
class NetImage extends StatelessWidget {
  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;
  final IconData fallbackIcon;
  final Color? fallbackColor;

  const NetImage({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
    this.fallbackIcon = Icons.image_outlined,
    this.fallbackColor,
  });

  @override
  Widget build(BuildContext context) {
    final radius = borderRadius ?? BorderRadius.circular(AppRadius.md);
    if (url == null || url!.isEmpty) return _fallback(radius);

    final resolvedUrl = resolveImageUrl(url!);

    return ClipRRect(
      borderRadius: radius,
      child: CachedNetworkImage(
        imageUrl: resolvedUrl,
        width: width,
        height: height,
        fit: fit,
        placeholder: (_, __) => _shimmer(radius),
        errorWidget: (_, __, ___) => _fallback(radius),
      ),
    );
  }

  Widget _shimmer(BorderRadius radius) => Container(
    width: width, height: height,
    decoration: BoxDecoration(color: AppColors.shimmerBase, borderRadius: radius),
    child: const Center(child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.textHint))),
  );

  Widget _fallback(BorderRadius radius) => Container(
    width: width, height: height,
    decoration: BoxDecoration(color: (fallbackColor ?? AppColors.primary).withOpacity(0.08), borderRadius: radius),
    child: Icon(fallbackIcon, color: (fallbackColor ?? AppColors.primary).withOpacity(0.35), size: (height ?? 40) * 0.4),
  );
}

/// Circular avatar — shows initials when no image URL.
class NetAvatar extends StatelessWidget {
  final String? url;
  final String name;
  final double size;

  const NetAvatar({super.key, required this.url, required this.name, this.size = 44});

  @override
  Widget build(BuildContext context) {
    final initials = name.trim().split(' ').take(2).map((w) => w.isNotEmpty ? w[0].toUpperCase() : '').join();

    if (url != null && url!.isNotEmpty) {
      final resolvedUrl = resolveImageUrl(url!);
      return ClipOval(
        child: CachedNetworkImage(
          imageUrl: resolvedUrl,
          width: size, height: size, fit: BoxFit.cover,
          placeholder: (_, __) => _initials(initials),
          errorWidget: (_, __, ___) => _initials(initials),
        ),
      );
    }
    return _initials(initials);
  }

  Widget _initials(String text) => Container(
    width: size, height: size,
    decoration: const BoxDecoration(shape: BoxShape.circle, gradient: AppColors.primaryGradient),
    child: Center(child: Text(text, style: TextStyle(color: Colors.white, fontSize: size * 0.35, fontWeight: FontWeight.w700))),
  );
}
