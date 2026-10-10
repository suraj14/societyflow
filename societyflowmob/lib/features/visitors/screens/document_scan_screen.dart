import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/services/document_scan_service.dart';
import '../../../core/theme/app_theme.dart';
import '../models/id_document.dart';

/// Scan a visitor's ID at the gate, then check what was read off it before
/// any of it reaches the visitor form.
///
/// Pops a [ScannedIdentity] when the guard accepts the reading, or `null`
/// when they cancel or choose to type it in - the Add Visitor sheet this is
/// pushed from *is* the manual form, so `null` simply leaves it as it was.
///
/// Three rules this screen exists to enforce:
///   1. Nothing is auto-filled without the guard seeing it first (the review
///      step), because OCR on a worn plastic card is a guess, not a fact.
///   2. When OCR reads nothing, the screen says so and offers the fields
///      empty. It never invents a name from a partial match.
///   3. An Aadhaar number is redacted to its last four digits on the way in
///      *and* on the way out, including when the guard types it by hand.
class DocumentScanScreen extends StatefulWidget {
  final IdDocumentType? initialType;

  const DocumentScanScreen({super.key, this.initialType});

  @override
  State<DocumentScanScreen> createState() => _DocumentScanScreenState();
}

class _DocumentScanScreenState extends State<DocumentScanScreen> {
  final _nameCtrl = TextEditingController();
  final _referenceCtrl = TextEditingController();

  IdDocumentType _type = IdDocumentType.aadhaar;
  ScannedIdentity? _captured;
  bool _busy = false;
  String? _notice;

  @override
  void initState() {
    super.initState();
    if (widget.initialType != null) _type = widget.initialType!;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _referenceCtrl.dispose();
    super.dispose();
  }

  Future<void> _scan() async {
    setState(() {
      _busy = true;
      _notice = null;
    });

    final result = await DocumentScanService.captureIdDocument(_type);
    if (!mounted) return;

    // A retake replaces the previous page, so the old one does not linger in
    // the cache.
    if (result.isCaptured && _captured?.imagePath != null) {
      await DocumentScanService.discardCapture(_captured!.imagePath);
    }
    if (!mounted) return;

    setState(() {
      _busy = false;
      _notice = result.message;
      if (result.isCaptured && result.identity != null) {
        _captured = result.identity;
        _nameCtrl.text = result.identity!.name ?? '';
        _referenceCtrl.text = result.identity!.documentReference ?? '';
      }
    });
  }

  void _continue() {
    final captured = _captured;
    if (captured == null) return;

    final name = _nameCtrl.text.trim();
    // Redact again on the way out: the field is editable, so a guard can
    // type a full Aadhaar into it even though the scan never would have.
    final reference = IdDocumentParser.redactReference(
      _type,
      _referenceCtrl.text.trim(),
    );

    Navigator.of(context).pop(
      captured.copyWith(
        documentType: _type,
        name: name.isEmpty ? null : name,
        documentReference:
            (reference == null || reference.isEmpty) ? null : reference,
      ),
    );
  }

  Future<void> _leave() async {
    await DocumentScanService.discardCapture(_captured?.imagePath);
    if (!mounted) return;
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.textPrimary),
        title: Text(
          _captured == null ? 'Scan ID / Document' : 'Check the details',
          style: AppTextStyles.h3,
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _typePicker(),
              const SizedBox(height: 16),
              if (_notice != null) _noticeBanner(_notice!),
              if (_captured == null) _beforeCapture() else _review(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _typePicker() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Document type', style: AppTextStyles.label),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: IdDocumentType.values.map((t) {
              final selected = _type == t;
              return GestureDetector(
                onTap: _busy ? null : () => setState(() => _type = t),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 9),
                  decoration: BoxDecoration(
                    color: selected ? AppColors.primary : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                        color: selected ? AppColors.primary : AppColors.divider),
                  ),
                  child: Text(
                    t.label,
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: selected ? Colors.white : AppColors.textSecondary,
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      );

  Widget _noticeBanner(String text) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.warning.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
        ),
        child: Row(children: [
          const Icon(Icons.info_outline_rounded,
              color: AppColors.warning, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(text,
                style: const TextStyle(
                    fontSize: 12.5, color: AppColors.textSecondary)),
          ),
        ]),
      );

  Widget _beforeCapture() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 36),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.divider),
            ),
            child: Column(children: [
              const Icon(Icons.document_scanner_outlined,
                  size: 54, color: AppColors.primary),
              const SizedBox(height: 12),
              Text('Hold the ${_type.label} card flat in good light',
                  style: AppTextStyles.body2, textAlign: TextAlign.center),
              const SizedBox(height: 4),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 24),
                child: Text(
                  'The scanner finds the edges and crops for you. '
                  'You can retake before anything is used.',
                  style: AppTextStyles.caption,
                  textAlign: TextAlign.center,
                ),
              ),
            ]),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton.icon(
              onPressed: _busy ? null : _scan,
              icon: _busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.camera_alt_rounded, color: Colors.white),
              label: Text(_busy ? 'Reading document...' : 'Open Scanner',
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w600)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                elevation: 0,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: OutlinedButton.icon(
              onPressed: _busy ? null : _leave,
              icon: const Icon(Icons.keyboard_rounded,
                  size: 18, color: AppColors.primary),
              label: const Text('Enter Manually',
                  style: TextStyle(
                      color: AppColors.primary, fontWeight: FontWeight.w600)),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppColors.primary),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ],
      );

  Widget _review() {
    final captured = _captured!;
    final readNothing = !captured.ocrFoundText || !captured.hasAnyExtractedField;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (captured.imagePath != null)
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: Image.file(
              File(captured.imagePath!),
              width: double.infinity,
              height: 190,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const SizedBox.shrink(),
            ),
          ),
        const SizedBox(height: 16),

        if (readNothing)
          _noticeBanner(
            'Nothing could be read off this document. '
            'Type the details below, or retake the photo.',
          ),

        const Text('Name', style: AppTextStyles.label),
        const SizedBox(height: 6),
        TextField(
          controller: _nameCtrl,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            hintText: 'Visitor name',
            prefixIcon:
                Icon(Icons.person_outlined, color: AppColors.textHint),
          ),
        ),
        const SizedBox(height: 14),

        Text(_type.referenceLabel, style: AppTextStyles.label),
        const SizedBox(height: 6),
        TextField(
          controller: _referenceCtrl,
          textCapitalization: TextCapitalization.characters,
          inputFormatters: [LengthLimitingTextInputFormatter(40)],
          decoration: InputDecoration(
            hintText: _type.redactsNumber ? 'XXXX XXXX 1234' : 'Document number',
            prefixIcon:
                const Icon(Icons.badge_outlined, color: AppColors.textHint),
          ),
        ),
        if (_type.redactsNumber)
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'Only the last 4 digits are kept. A full Aadhaar number is '
              'reduced automatically and never stored or sent.',
              style: AppTextStyles.caption,
            ),
          ),

        if (captured.address != null) ...[
          const SizedBox(height: 14),
          const Text('Address on document', style: AppTextStyles.label),
          const SizedBox(height: 6),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.divider),
            ),
            child: Text(captured.address!, style: AppTextStyles.body2),
          ),
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'Shown for your check only - a visitor record has no address '
              'field, so this is not saved.',
              style: AppTextStyles.caption,
            ),
          ),
        ],

        const SizedBox(height: 22),
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton.icon(
            onPressed: _busy ? null : _continue,
            icon: const Icon(Icons.check_rounded, color: Colors.white),
            label: const Text('Continue',
                style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600)),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.secondary,
              elevation: 0,
              shape:
                  RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(
            child: SizedBox(
              height: 48,
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _scan,
                icon: const Icon(Icons.refresh_rounded,
                    size: 18, color: AppColors.primary),
                label: const Text('Retake',
                    style: TextStyle(
                        color: AppColors.primary, fontWeight: FontWeight.w600)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: AppColors.primary),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: SizedBox(
              height: 48,
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _leave,
                icon: const Icon(Icons.keyboard_rounded,
                    size: 18, color: AppColors.textSecondary),
                label: const Text('Enter Manually',
                    style: TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: AppColors.divider),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ),
        ]),
      ],
    );
  }
}
