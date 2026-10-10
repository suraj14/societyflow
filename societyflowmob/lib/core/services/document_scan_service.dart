import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_document_scanner/google_mlkit_document_scanner.dart';
import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

import '../../features/visitors/models/id_document.dart';

/// How a document capture ended. The guard sees a different screen for each,
/// and in three of the four cases that screen offers manual entry - the gate
/// cannot stop working because a camera or a Play services module did not.
enum DocumentScanOutcome {
  /// A page was captured. OCR may still have found nothing; that is carried
  /// on the identity itself, not here.
  captured,

  /// The guard backed out of the scanner.
  cancelled,

  /// This build/platform has no document scanner at all (iOS, or a device
  /// without the Play services module behind it).
  unavailable,

  /// The scanner started and then failed.
  failed,
}

@immutable
class DocumentScanResult {
  final DocumentScanOutcome outcome;
  final ScannedIdentity? identity;

  /// Safe to show a guard: never a stack trace, never document content.
  final String? message;

  const DocumentScanResult(this.outcome, {this.identity, this.message});

  bool get isCaptured => outcome == DocumentScanOutcome.captured;
}

/// The real Android document scanner, plus on-device OCR over what it
/// captured.
///
/// This is a *different* concern from `mobile_scanner`, which stays exactly
/// as it was for reading access-pass QR codes:
///   - `mobile_scanner` runs a camera preview inside our own widget and
///     decodes barcodes from the stream.
///   - ML Kit's document scanner is a full-screen Google activity that does
///     edge detection, perspective correction, cropping and retake, and hands
///     back a JPEG. It is launched, not embedded, so there is no camera
///     preview widget here and no permission plumbing of our own: the Play
///     services UI asks for the camera itself.
///
/// Android only. `google_mlkit_document_scanner` has no iOS implementation,
/// so [isSupported] is checked before every call and the UI falls through to
/// manual entry everywhere else.
class DocumentScanService {
  const DocumentScanService._();

  static bool get isSupported {
    if (kIsWeb) return false;
    try {
      return Platform.isAndroid;
    } catch (_) {
      // Platform throws under `flutter test`; treated as unsupported so a
      // widget test never tries to launch a scanner activity.
      return false;
    }
  }

  /// Launches the scanner, then reads the captured page.
  ///
  /// Always returns a result - nothing here throws at the caller, because
  /// every failure mode has a guard-facing fallback rather than an error
  /// dialog.
  static Future<DocumentScanResult> captureIdDocument(
    IdDocumentType type,
  ) async {
    if (!isSupported) {
      return const DocumentScanResult(
        DocumentScanOutcome.unavailable,
        message: 'Document scanning needs an Android device. '
            'Enter the details manually.',
      );
    }

    final scanner = DocumentScanner(
      options: DocumentScannerOptions(
        // One page: an ID card is one side, and a page limit of 1 means the
        // Google UI closes on the first accepted capture instead of asking
        // the guard whether to add another.
        pageLimit: 1,
        documentFormats: const {DocumentFormat.jpeg},
        // `filter` gives crop + rotate + retake without the PDF/"scan more"
        // extras of `full`.
        mode: ScannerMode.filter,
        // The gate photographs the card in front of it; importing an ID from
        // the device gallery is not a thing a guard should be able to do.
        isGalleryImport: false,
      ),
    );

    try {
      final result = await scanner.scanDocument();
      final images = result.images ?? const <String>[];

      if (images.isEmpty) {
        return const DocumentScanResult(
          DocumentScanOutcome.cancelled,
          message: 'No document captured.',
        );
      }

      return DocumentScanResult(
        DocumentScanOutcome.captured,
        identity: await _readIdentity(images.first, type),
      );
    } on PlatformException catch (e) {
      // The plugin reports a back-press as an error, not an empty result.
      final detail = (e.message ?? '').toLowerCase();
      if (detail.contains('cancel')) {
        return const DocumentScanResult(DocumentScanOutcome.cancelled);
      }

      // "Failed to start document scanner" is what a device without the
      // Play services document-scanner module gives back.
      return const DocumentScanResult(
        DocumentScanOutcome.unavailable,
        message: 'The document scanner could not start on this device. '
            'Enter the details manually.',
      );
    } catch (_) {
      return const DocumentScanResult(
        DocumentScanOutcome.failed,
        message: 'Scanning failed. Enter the details manually.',
      );
    } finally {
      // Best effort: a failed close must not mask the result above.
      try {
        await scanner.close();
      } catch (_) {}
    }
  }

  /// OCR over one captured page.
  ///
  /// A failure here is never fatal: the capture still happened, so the guard
  /// gets the image and an empty form rather than an error. Nothing from the
  /// recognised text is logged or printed - that text is somebody's ID.
  static Future<ScannedIdentity> _readIdentity(
    String imagePath,
    IdDocumentType type,
  ) async {
    final recogniser = TextRecognizer(script: TextRecognitionScript.latin);
    try {
      final recognised =
          await recogniser.processImage(InputImage.fromFilePath(imagePath));

      if (recognised.text.trim().isEmpty) {
        return ScannedIdentity.unreadable(
          documentType: type,
          imagePath: imagePath,
        );
      }

      return IdDocumentParser.parse(
        recognised.text,
        type,
        imagePath: imagePath,
      );
    } catch (_) {
      return ScannedIdentity.unreadable(
        documentType: type,
        imagePath: imagePath,
      );
    } finally {
      try {
        await recogniser.close();
      } catch (_) {}
    }
  }

  /// Drops a captured page once the guard is done with it.
  ///
  /// The gate has no requirement to keep ID images and no protected place to
  /// put them, so the capture does not outlive the flow that made it.
  static Future<void> discardCapture(String? imagePath) async {
    if (imagePath == null || imagePath.isEmpty) return;
    try {
      final file = File(imagePath);
      if (await file.exists()) await file.delete();
    } catch (_) {
      // A cache file we could not delete is the OS's to clean up.
    }
  }
}
