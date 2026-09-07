import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/skeleton_loader.dart';
import '../../../shared/widgets/status_badge.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../core/utils/auto_refresh_mixin.dart';
import '../providers/complaints_provider.dart';
import '../models/complaint_model.dart';

class ComplaintsScreen extends ConsumerStatefulWidget {
  const ComplaintsScreen({super.key});
  @override
  ConsumerState<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends ConsumerState<ComplaintsScreen>
    with SingleTickerProviderStateMixin, AutoRefreshMixin {
  late TabController _tabController;

  @override
  void onAutoRefresh() => ref.read(complaintsProvider.notifier).loadComplaints();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    Future.microtask(() {
      ref.read(complaintsProvider.notifier).loadComplaints();
      ref.read(complaintsProvider.notifier).loadCategories();
    });
    startAutoRefresh();
  }

  void _startAutoRefresh() {} // kept for compatibility, mixin handles it

  @override
  void dispose() { _tabController.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(complaintsProvider);
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Complaints'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textHint,
          indicatorColor: AppColors.primary,
          tabs: const [Tab(text: 'All'), Tab(text: 'Open'), Tab(text: 'Closed')],
        ),
      ),
      body: state.isLoading
          ? const ListSkeleton()
          : RefreshIndicator(
              onRefresh: () => ref.read(complaintsProvider.notifier).loadComplaints(),
              color: AppColors.primary,
              child: state.error != null && state.complaints.isEmpty
                  ? SingleChildScrollView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      child: SizedBox(height: 400, child: EmptyState(icon: Icons.error_outline_rounded, title: 'Failed to load', subtitle: state.error!)),
                    )
                  : TabBarView(
                      controller: _tabController,
                      children: [
                        _ComplaintList(complaints: state.complaints),
                        _ComplaintList(complaints: state.complaints.where((c) => c.isOpen || c.isInProgress).toList()),
                        _ComplaintList(complaints: state.complaints.where((c) => c.isClosed).toList()),
                      ],
                    ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent, builder: (_) => const _RaiseComplaintSheet()),
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.add_rounded, color: Colors.white),
        label: const Text('Raise Complaint', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
      ),
    );
  }
}

class _ComplaintList extends StatelessWidget {
  final List<ComplaintModel> complaints;
  const _ComplaintList({required this.complaints});

  @override
  Widget build(BuildContext context) {
    if (complaints.isEmpty) {
      return const EmptyState(icon: Icons.support_agent_outlined, title: 'No complaints', subtitle: 'Raise a complaint if you have any issues');
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
      itemCount: complaints.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, i) => _ComplaintCard(complaint: complaints[i]),
    );
  }
}

class _ComplaintCard extends StatelessWidget {
  final ComplaintModel complaint;
  const _ComplaintCard({required this.complaint});

  Color _statusColor(String s) {
    switch (s) {
      case 'open': return AppColors.primary;
      case 'in_progress': return AppColors.info;
      case 'closed': case 'resolved': return AppColors.secondary;
      default: return AppColors.textHint;
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.all(16),
      onTap: () => showModalBottomSheet(
        context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
        builder: (_) => _ComplaintDetailSheet(complaint: complaint),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 44, height: 44, decoration: BoxDecoration(color: _statusColor(complaint.status).withOpacity(0.1), borderRadius: BorderRadius.circular(12)), child: Icon(Icons.support_agent_rounded, color: _statusColor(complaint.status), size: 20)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(complaint.title, style: AppTextStyles.label, maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Text(complaint.category ?? 'General', style: AppTextStyles.caption),
          ])),
          StatusBadge(status: complaint.status),
        ]),
        const SizedBox(height: 10),
        Text(complaint.description, style: AppTextStyles.body2, maxLines: 2, overflow: TextOverflow.ellipsis),
        const SizedBox(height: 10),
        Row(children: [
          const Icon(Icons.access_time_rounded, size: 13, color: AppColors.textHint),
          const SizedBox(width: 4),
          Text('${complaint.createdAt.day}/${complaint.createdAt.month}/${complaint.createdAt.year}', style: AppTextStyles.caption),
          const Spacer(),
          if (complaint.updates.isNotEmpty) ...[
            const Icon(Icons.chat_bubble_outline_rounded, size: 13, color: AppColors.textHint),
            const SizedBox(width: 4),
            Text('${complaint.updates.length} updates', style: AppTextStyles.caption),
          ],
        ]),
      ]),
    );
  }
}

class _ComplaintDetailSheet extends ConsumerStatefulWidget {
  final ComplaintModel complaint;
  const _ComplaintDetailSheet({required this.complaint});

  @override
  ConsumerState<_ComplaintDetailSheet> createState() => _ComplaintDetailSheetState();
}

class _ComplaintDetailSheetState extends ConsumerState<_ComplaintDetailSheet> {
  ComplaintModel? _fresh;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchFresh();
  }

  Future<void> _fetchFresh() async {
    setState(() => _loading = true);
    try {
      final result = await ref.read(complaintsProvider.notifier).fetchComplaint(widget.complaint.id);
      if (mounted) setState(() { _fresh = result; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _fresh = widget.complaint; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = _fresh ?? widget.complaint;
    return DraggableScrollableSheet(
      initialChildSize: 0.75, maxChildSize: 0.95, minChildSize: 0.5,
      builder: (_, ctrl) => Container(
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        child: Column(children: [
          const SizedBox(height: 12),
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
          if (_loading)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else
            Expanded(child: ListView(controller: ctrl, padding: const EdgeInsets.all(24), children: [
              Row(children: [Expanded(child: Text(c.title, style: AppTextStyles.h3)), StatusBadge(status: c.status)]),
              const SizedBox(height: 8),
              Text(c.category ?? 'General', style: AppTextStyles.body2.copyWith(color: AppColors.primary)),
              const SizedBox(height: 16),
              Text(c.description, style: AppTextStyles.body1),
              const SizedBox(height: 24),
              Row(children: [
                const Text('Updates & Replies', style: AppTextStyles.h3),
                const Spacer(),
                GestureDetector(
                  onTap: _fetchFresh,
                  child: const Icon(Icons.refresh_rounded, color: AppColors.primary, size: 20),
                ),
              ]),
              const SizedBox(height: 12),
              if (c.updates.isEmpty)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: AppColors.background, borderRadius: BorderRadius.circular(12)),
                  child: const Text('No replies yet. Admin will respond soon.', style: AppTextStyles.caption, textAlign: TextAlign.center),
                )
              else
                ...c.updates.map((u) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withOpacity(0.05),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.primary.withOpacity(0.15)),
                    ),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Container(
                          width: 28, height: 28,
                          decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.15), shape: BoxShape.circle),
                          child: const Icon(Icons.person_rounded, color: AppColors.primary, size: 14),
                        ),
                        const SizedBox(width: 8),
                        Text(u.updatedBy ?? 'Admin', style: AppTextStyles.label.copyWith(fontSize: 13)),
                        const Spacer(),
                        Text('${u.createdAt.day}/${u.createdAt.month}/${u.createdAt.year}', style: AppTextStyles.caption),
                      ]),
                      const SizedBox(height: 8),
                      Text(u.message, style: AppTextStyles.body2),
                    ]),
                  ),
                )),
            ])),
        ]),
      ),
    );
  }
}

class _RaiseComplaintSheet extends ConsumerStatefulWidget {
  const _RaiseComplaintSheet();
  @override
  ConsumerState<_RaiseComplaintSheet> createState() => _RaiseComplaintSheetState();
}

class _RaiseComplaintSheetState extends ConsumerState<_RaiseComplaintSheet> {
  final _formKey   = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _descCtrl  = TextEditingController();
  String? _selectedCategory;
  File? _image;
  bool _isLoading = false;

  final List<String> _defaultCats = ['Plumbing', 'Electrical', 'Cleaning', 'Security', 'Parking', 'Lift', 'Common Area', 'Other'];

  @override
  void dispose() { _titleCtrl.dispose(); _descCtrl.dispose(); super.dispose(); }

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (picked != null) setState(() => _image = File(picked.path));
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref.read(complaintsProvider.notifier).raiseComplaint(title: _titleCtrl.text.trim(), description: _descCtrl.text.trim(), category: _selectedCategory, image: _image);
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Complaint raised successfully!'), backgroundColor: AppColors.secondary, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), margin: const EdgeInsets.all(16)));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cats = ref.watch(complaintsProvider).categories.isNotEmpty ? ref.watch(complaintsProvider).categories : _defaultCats;
    return Container(
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      padding: EdgeInsets.fromLTRB(24, 20, 24, MediaQuery.of(context).viewInsets.bottom + 24),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 20),
            const Text('Raise a Complaint', style: AppTextStyles.h3),
            const SizedBox(height: 24),
            const Text('Category', style: AppTextStyles.label),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              value: _selectedCategory,
              hint: const Text('Select category'),
              decoration: InputDecoration(prefixIcon: const Icon(Icons.category_outlined, color: AppColors.textHint), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
              items: cats.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
              onChanged: (v) => setState(() => _selectedCategory = v),
              validator: (v) => v == null ? 'Please select a category' : null,
            ),
            const SizedBox(height: 16),
            const Text('Title', style: AppTextStyles.label),
            const SizedBox(height: 8),
            TextFormField(controller: _titleCtrl, decoration: const InputDecoration(hintText: 'Brief title of the issue', prefixIcon: Icon(Icons.title_rounded, color: AppColors.textHint)), validator: (v) => v?.isEmpty == true ? 'Title is required' : null),
            const SizedBox(height: 16),
            const Text('Description', style: AppTextStyles.label),
            const SizedBox(height: 8),
            TextFormField(controller: _descCtrl, maxLines: 4, decoration: const InputDecoration(hintText: 'Describe the issue in detail...'), validator: (v) => v?.isEmpty == true ? 'Description is required' : null),
            const SizedBox(height: 16),
            GestureDetector(
              onTap: _pickImage,
              child: Container(
                height: 90,
                decoration: BoxDecoration(border: Border.all(color: AppColors.divider), borderRadius: BorderRadius.circular(12), color: AppColors.background),
                child: _image != null
                    ? ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.file(_image!, fit: BoxFit.cover, width: double.infinity))
                    : const Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.add_photo_alternate_outlined, color: AppColors.textHint, size: 28),
                        SizedBox(height: 6),
                        Text('Tap to add photo (optional)', style: AppTextStyles.caption),
                      ]),
              ),
            ),
            const SizedBox(height: 24),
            AppButton(label: 'Submit Complaint', onPressed: _submit, isLoading: _isLoading, icon: Icons.send_rounded),
          ]),
        ),
      ),
    );
  }
}
