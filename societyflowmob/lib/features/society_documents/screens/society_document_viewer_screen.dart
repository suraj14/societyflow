import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pdfx/pdfx.dart';
import '../../../core/network/http_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/error_view.dart';
import '../models/society_document_model.dart';
import '../providers/society_documents_provider.dart';

/// Reads a society document in-app: the PDF is fetched with the user's
/// bearer token and rendered natively (pinch to zoom, scroll pages) - the
/// user never has to download a file just to read the rules.
class SocietyDocumentViewerScreen extends ConsumerStatefulWidget {
  final SocietyDocumentModel document;
  const SocietyDocumentViewerScreen({super.key, required this.document});

  @override
  ConsumerState<SocietyDocumentViewerScreen> createState() => _SocietyDocumentViewerScreenState();
}

class _SocietyDocumentViewerScreenState extends ConsumerState<SocietyDocumentViewerScreen> {
  PdfControllerPinch? _controller;
  String? _error;
  bool _loading = true;
  bool _acknowledging = false;
  int _page = 1;
  int _pages = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    String? failure;
    final bytes = await HttpService.instance.getBytes(
      widget.document.filePath,
      onError: (message, _) => failure = message,
    );
    if (!mounted) return;

    if (bytes == null) {
      setState(() {
        _loading = false;
        _error = failure ?? 'Could not open this document.';
      });
      return;
    }

    final old = _controller;
    setState(() {
      _controller = PdfControllerPinch(document: PdfDocument.openData(bytes));
      _loading = false;
    });
    old?.dispose();
  }

  Future<void> _acknowledge() async {
    setState(() => _acknowledging = true);
    final error = await ref.read(societyDocumentsProvider.notifier).acknowledge(widget.document.id);
    if (!mounted) return;
    setState(() => _acknowledging = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error ?? 'Thank you - your acknowledgement has been recorded.')),
    );
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final doc = ref.watch(societyDocumentsProvider.select(
      (s) => s.documents.firstWhere((d) => d.id == widget.document.id, orElse: () => widget.document),
    ));

    return Scaffold(
      backgroundColor: const Color(0xFFE5E7EB),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(doc.title, maxLines: 1, overflow: TextOverflow.ellipsis),
            Text(
              '${doc.categoryLabel} · ${doc.version}',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w400),
            ),
          ],
        ),
        actions: [
          if (_pages > 0)
            Center(
              child: Padding(
                padding: const EdgeInsets.only(right: 16),
                child: Text('$_page / $_pages', style: const TextStyle(fontSize: 13)),
              ),
            ),
        ],
      ),
      body: _content(),
      bottomNavigationBar: doc.requiresAcknowledgement && _controller != null && _error == null
          ? SafeArea(
              child: Container(
                color: AppColors.surface,
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                child: doc.acknowledged
                    ? const Row(children: [
                        Icon(Icons.check_circle_rounded, color: AppColors.secondary),
                        SizedBox(width: 8),
                        Expanded(child: Text('You confirmed reading this version.')),
                      ])
                    : FilledButton(
                        onPressed: _acknowledging ? null : _acknowledge,
                        style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                        child: _acknowledging
                            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('I have read this'),
                      ),
              ),
            )
          : null,
    );
  }

  Widget _content() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ErrorView(message: _error!, icon: Icons.description_outlined, onRetry: _load);
    }
    return PdfViewPinch(
      controller: _controller!,
      padding: 8,
      onDocumentLoaded: (d) => setState(() => _pages = d.pagesCount),
      onPageChanged: (p) => setState(() => _page = p),
      onDocumentError: (_) => setState(
        () => _error = 'This file could not be displayed. Please ask the society office to re-upload it.',
      ),
    );
  }
}
