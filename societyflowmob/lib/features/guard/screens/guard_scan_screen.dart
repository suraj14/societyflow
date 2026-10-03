import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../core/theme/app_theme.dart';
import '../providers/access_pass_provider.dart';

/// "Scan, verify, move on." Camera-first, with a manual code field always
/// available underneath — for a blocked camera permission, a damaged/dirty
/// lens, or a physical USB/Bluetooth QR scanner that just types its result
/// into a focused text field (works through the same field, no extra code;
/// same pattern the web gate's guard/scan.blade.php uses).
class GuardScanScreen extends StatefulWidget {
  const GuardScanScreen({super.key});

  @override
  State<GuardScanScreen> createState() => _GuardScanScreenState();
}

class _GuardScanScreenState extends State<GuardScanScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  final TextEditingController _manualController = TextEditingController();
  final FocusNode _manualFocus = FocusNode();

  bool _busy = false;
  bool _cameraFailed = false;
  ScanOutcome? _lastOutcome;

  @override
  void dispose() {
    _controller.dispose();
    _manualController.dispose();
    _manualFocus.dispose();
    super.dispose();
  }

  Future<void> _submit(String token) async {
    final trimmed = token.trim();
    if (trimmed.isEmpty || _busy) return;

    setState(() {
      _busy = true;
      _lastOutcome = null;
    });

    try {
      final outcome = await AccessPassApi.scan(trimmed);
      if (!mounted) return;
      setState(() => _lastOutcome = outcome);
    } catch (e) {
      if (!mounted) return;
      setState(() => _lastOutcome = const ScanOutcome(
        success: false,
        message: 'Could not reach the server. Check your connection.',
      ));
    } finally {
      if (mounted) setState(() => _busy = false);
      _manualController.clear();
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (_busy) return;
    for (final barcode in capture.barcodes) {
      final value = barcode.rawValue;
      if (value != null && value.isNotEmpty) {
        _submit(value);
        return;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('Scan QR'),
        actions: [
          IconButton(
            icon: const Icon(Icons.flash_on_rounded),
            tooltip: 'Toggle flash',
            onPressed: _cameraFailed ? null : () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            flex: 3,
            child: _cameraFailed
                ? _cameraUnavailable()
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      MobileScanner(
                        controller: _controller,
                        onDetect: _onDetect,
                        // MobileScannerErrorBuilder in mobile_scanner 5.2.3 is
                        // `Widget Function(BuildContext, MobileScannerException,
                        // Widget?)` - three positional parameters, confirmed
                        // against the installed version's own API docs (the
                        // two-parameter form used by some other versions of
                        // this package does not compile here).
                        errorBuilder: (context, error, child) {
                          // Runs after build - avoid setState during build.
                          WidgetsBinding.instance.addPostFrameCallback((_) {
                            if (mounted && !_cameraFailed) {
                              setState(() => _cameraFailed = true);
                            }
                          });
                          return _cameraUnavailable();
                        },
                      ),
                      _ScanFrame(),
                      if (_busy)
                        Container(
                          color: Colors.black45,
                          child: const Center(child: CircularProgressIndicator(color: Colors.white)),
                        ),
                    ],
                  ),
          ),
          Expanded(
            flex: 2,
            child: Container(
              color: AppColors.background,
              padding: const EdgeInsets.all(16),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_lastOutcome != null) _ResultCard(outcome: _lastOutcome!),
                    if (_lastOutcome != null) const SizedBox(height: 16),
                    const Text('Or enter the code manually', style: AppTextStyles.label),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _manualController,
                      focusNode: _manualFocus,
                      autocorrect: false,
                      enableSuggestions: false,
                      textInputAction: TextInputAction.done,
                      style: const TextStyle(fontFamily: 'monospace', letterSpacing: 1),
                      decoration: InputDecoration(
                        hintText: 'Paste or type code',
                        filled: true,
                        fillColor: Colors.white,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                      ),
                      onSubmitted: _submit,
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: _busy ? null : () => _submit(_manualController.text),
                        icon: const Icon(Icons.check_circle_rounded),
                        label: const Text('Verify'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _cameraUnavailable() => Container(
    color: Colors.black,
    padding: const EdgeInsets.all(24),
    child: const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.camera_alt_outlined, color: Colors.white54, size: 40),
          SizedBox(height: 12),
          Text(
            'Camera unavailable — enter the code below instead.',
            style: TextStyle(color: Colors.white70),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

class _ScanFrame extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 220,
      height: 220,
      decoration: BoxDecoration(
        border: Border.all(color: Colors.white70, width: 3),
        borderRadius: BorderRadius.circular(16),
      ),
    ),
  );
}

class _ResultCard extends StatelessWidget {
  final ScanOutcome outcome;
  const _ResultCard({required this.outcome});

  @override
  Widget build(BuildContext context) {
    final color = outcome.success ? AppColors.secondary : AppColors.error;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16)),
      child: Column(
        children: [
          Icon(outcome.success ? Icons.check_circle_rounded : Icons.cancel_rounded, color: Colors.white, size: 40),
          const SizedBox(height: 8),
          Text(
            outcome.success ? 'ENTRY ALLOWED' : 'ACCESS DENIED',
            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
          ),
          if (outcome.person != null) ...[
            const SizedBox(height: 4),
            Text(
              [outcome.person, outcome.category].where((s) => s != null && s.isNotEmpty).join(' · '),
              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
            ),
          ],
          const SizedBox(height: 4),
          Text(outcome.message, style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 12), textAlign: TextAlign.center),
        ],
      ),
    );
  }
}
