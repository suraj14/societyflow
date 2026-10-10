/// Client-side checks for a profile photo, run before anything is uploaded so
/// a person gets an immediate, plain-language reason instead of a failed
/// request. The server enforces the same limits (App\Support\ProfileImage) -
/// this is a courtesy, never the only line of defence.
class ProfilePhotoRules {
  ProfilePhotoRules._();

  /// Mirrors ProfileImage::MAX_KB (2048 KB) on the server.
  static const int maxBytes = 2 * 1024 * 1024;

  static const List<String> allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

  /// Null when the photo is acceptable, otherwise the message to show.
  static String? validate({required String path, required int sizeBytes}) {
    final dot = path.lastIndexOf('.');
    final extension = dot == -1 ? '' : path.substring(dot + 1).toLowerCase();

    if (!allowedExtensions.contains(extension)) {
      return 'Please choose a JPG, PNG or GIF photo.';
    }
    if (sizeBytes <= 0) {
      return 'That photo could not be read. Please choose another.';
    }
    if (sizeBytes > maxBytes) {
      final mb = (sizeBytes / (1024 * 1024)).toStringAsFixed(1);
      return 'That photo is $mb MB. The limit is ${maxBytes ~/ (1024 * 1024)} MB - please choose a smaller one.';
    }
    return null;
  }

  /// 0..1 for a LinearProgressIndicator; null (indeterminate) until the
  /// total size is known.
  static double? progress(int sent, int total) {
    if (total <= 0) return null;
    final value = sent / total;
    return value.clamp(0.0, 1.0);
  }
}
