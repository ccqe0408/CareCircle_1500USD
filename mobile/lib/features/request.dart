import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../core/design.dart';
import '../core/state.dart';

class NewRequestScreen extends ConsumerStatefulWidget {
  const NewRequestScreen({super.key});
  @override
  ConsumerState<NewRequestScreen> createState() => _NewRequestState();
}

class _NewRequestState extends ConsumerState<NewRequestScreen> {
  final form = GlobalKey<FormState>();
  final title = TextEditingController();
  final pickup = TextEditingController();
  final notes = TextEditingController();
  int? beneficiaryId, categoryId;
  int step = 0;
  bool consent = false, busy = false;
  DateTime start = DateTime.now()
      .add(const Duration(days: 1))
      .copyWith(hour: 16, minute: 0, second: 0, millisecond: 0, microsecond: 0);
  @override
  void dispose() {
    title.dispose();
    pickup.dispose();
    notes.dispose();
    super.dispose();
  }

  Future<void> chooseTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: start,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 29)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(start),
    );
    if (time != null) {
      setState(
        () => start = DateTime(
          date.year,
          date.month,
          date.day,
          time.hour,
          time.minute,
        ),
      );
    }
  }

  Future<void> publish() async {
    if (!form.currentState!.validate()) return;
    if (!consent) {
      toast(context, 'Please confirm the service rules.');
      return;
    }
    setState(() => busy = true);
    try {
      final data = await ref
          .read(apiProvider)
          .call(
            '/requests',
            method: 'POST',
            data: {
              'beneficiary_id': beneficiaryId,
              'service_category_id': categoryId,
              'title': title.text.trim(),
              'pickup_address': pickup.text.trim(),
              'notes': notes.text.trim(),
              'scheduled_start': start.toUtc().toIso8601String(),
              'scheduled_end': start
                  .add(const Duration(hours: 1))
                  .toUtc()
                  .toIso8601String(),
              'prepaid_confirmed': consent,
            },
          );
      refreshWork(ref);
      if (mounted) context.go('/request/${data['id']}');
    } catch (e) {
      if (mounted) toast(context, e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final meta = ref.watch(metaProvider);
    final people = ref.watch(beneficiariesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Request a helping hand')),
      body: PageBody(
        children: [
          Text(
            'A few details.\nWe’ll find the right neighbour.',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 12),
          Text(
            'STEP ${step + 1} OF 2',
            style: const TextStyle(
              fontSize: 11,
              letterSpacing: 1.4,
              color: muted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          LinearProgressIndicator(
            value: (step + 1) / 2,
            minHeight: 4,
            borderRadius: BorderRadius.circular(4),
          ),
          const SizedBox(height: 24),
          AsyncContent(
            value: meta,
            retry: () => ref.invalidate(metaProvider),
            builder: (config) => AsyncContent(
              value: people,
              retry: () => ref.invalidate(beneficiariesProvider),
              builder: (allPeople) {
                final available = allPeople
                    .where((b) => b['can_request'] == true)
                    .toList();
                if (available.isEmpty) {
                  return Column(
                    children: [
                      const EmptyCard(
                        title: 'Add a service recipient first',
                        body:
                            'Create your own resident profile, or request community approval to help a family member.',
                      ),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: () => context.push('/people'),
                        child: const Text('Manage service recipients'),
                      ),
                    ],
                  );
                }
                beneficiaryId ??= available.first['id'];
                categoryId ??=
                    (config['categories'] as List).firstOrNull?['id'];
                return Form(
                  key: form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (step == 0) ...[
                        DropdownButtonFormField<int>(
                          initialValue: beneficiaryId,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Who needs help?',
                          ),
                          items: available
                              .map(
                                (b) => DropdownMenuItem<int>(
                                  value: b['id'],
                                  child: Text(b['name']),
                                ),
                              )
                              .toList(),
                          onChanged: (v) => setState(() => beneficiaryId = v),
                          validator: (v) =>
                              v == null ? 'Choose a recipient' : null,
                        ),
                        const SizedBox(height: 18),
                        DropdownButtonFormField<int>(
                          initialValue: categoryId,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'Type of assistance',
                          ),
                          items: (config['categories'] as List)
                              .map(
                                (c) => DropdownMenuItem<int>(
                                  value: c['id'],
                                  child: Text(
                                    c['name'],
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (v) => setState(() => categoryId = v),
                          validator: (v) =>
                              v == null ? 'Choose a category' : null,
                        ),
                        const SizedBox(height: 20),
                        const CarePanel(
                          color: pale,
                          child: Text(
                            'We arrange low-risk collection of prepaid groceries and ordinary parcels. Volunteers do not handle cash, enter homes or provide medical care.',
                          ),
                        ),
                        const SizedBox(height: 24),
                        FilledButton(
                          onPressed: () {
                            if (form.currentState!.validate()) {
                              setState(() => step = 1);
                            }
                          },
                          child: const Text('Continue'),
                        ),
                      ] else ...[
                        TextFormField(
                          key: const ValueKey('request-title'),
                          controller: title,
                          decoration: const InputDecoration(
                            labelText: 'What do you need?',
                            hintText: 'Collect my prepaid grocery order',
                          ),
                          maxLength: 120,
                          validator: (v) => v == null || v.trim().length < 5
                              ? 'Describe your request in at least 5 characters'
                              : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const ValueKey('pickup-address'),
                          controller: pickup,
                          decoration: const InputDecoration(
                            labelText: 'Collection address / counter',
                          ),
                          maxLines: 2,
                          maxLength: 500,
                          validator: (v) => v == null || v.trim().length < 5
                              ? 'Enter a collection location'
                              : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: notes,
                          decoration: const InputDecoration(
                            labelText: 'Useful details (optional)',
                            hintText:
                                'Order reference, items or meeting instructions',
                          ),
                          maxLines: 3,
                          maxLength: 1000,
                        ),
                        const SizedBox(height: 14),
                        OutlinedButton.icon(
                          onPressed: busy ? null : chooseTime,
                          icon: const Icon(Icons.calendar_month_outlined),
                          label: Text(
                            DateFormat('d MMM, h:mm a').format(start),
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'One-hour service window. Delivery uses the recipient’s saved address.',
                          style: TextStyle(fontSize: 12, color: muted),
                        ),
                        const SizedBox(height: 16),
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          value: consent,
                          onChanged: busy
                              ? null
                              : (v) => setState(() => consent = v ?? false),
                          title: const Text(
                            'This is prepaid, non-emergency assistance within the service rules.',
                          ),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                        const SizedBox(height: 18),
                        BusyButton(
                          busy: busy,
                          onPressed: publish,
                          label: 'Publish request',
                          icon: Icons.arrow_forward_rounded,
                        ),
                        TextButton(
                          onPressed: busy
                              ? null
                              : () => setState(() => step = 0),
                          child: const Text('Back to recipient'),
                        ),
                      ],
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class RequestScreen extends ConsumerStatefulWidget {
  final int id;
  const RequestScreen({super.key, required this.id});
  @override
  ConsumerState<RequestScreen> createState() => _RequestState();
}

class _RequestState extends ConsumerState<RequestScreen>
    with WidgetsBindingObserver {
  bool busy = false;
  Timer? timer;
  bool foreground = true;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    timer = Timer.periodic(const Duration(seconds: 12), (_) {
      if (mounted && foreground && !busy) {
        ref.invalidate(detailProvider(widget.id));
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
    if (foreground) ref.invalidate(detailProvider(widget.id));
  }

  @override
  void dispose() {
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> action(String endpoint, {Json? data}) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await ref
          .read(apiProvider)
          .call('/requests/${widget.id}/$endpoint', method: 'POST', data: data);
      ref.invalidate(detailProvider(widget.id));
      refreshWork(ref);
      if (mounted) {
        toast(
          context,
          endpoint == 'confirm'
              ? 'Handover confirmed. Thank you!'
              : 'Task updated.',
        );
      }
    } catch (e) {
      if (mounted) toast(context, e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<String?> ask(String title, String label, {bool code = false}) async {
    final c = TextEditingController();
    final key = GlobalKey<FormState>();
    final answer = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Form(
          key: key,
          child: TextFormField(
            controller: c,
            autofocus: true,
            keyboardType: code ? TextInputType.number : TextInputType.multiline,
            maxLength: code ? 6 : 1000,
            maxLines: code ? 1 : 3,
            decoration: InputDecoration(labelText: label),
            validator: (v) => code
                ? (RegExp(r'^\d{6}$').hasMatch(v ?? '')
                      ? null
                      : 'Enter the six-digit code')
                : (v == null || v.trim().length < 10
                      ? 'Please use at least 10 characters'
                      : null),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Back'),
          ),
          FilledButton(
            onPressed: () {
              if (key.currentState!.validate()) {
                Navigator.pop(ctx, c.text.trim());
              }
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    await Future<void>.delayed(const Duration(milliseconds: 250));
    c.dispose();
    return answer;
  }

  Future<void> showCode() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final code = await ref
          .read(apiProvider)
          .call('/requests/${widget.id}/handover-code', method: 'POST');
      if (!mounted) return;
      setState(() => busy = false);
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Confirm your handover'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.verified_outlined, size: 44, color: forest),
              const SizedBox(height: 16),
              const Text(
                'Check your items first. Then tell your assigned volunteer this code.',
              ),
              const SizedBox(height: 22),
              Text(
                code['code'],
                style: const TextStyle(
                  fontSize: 36,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 6,
                  color: forest,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Expires in 10 minutes. Generating a new code replaces the previous one.',
                style: TextStyle(fontSize: 12, color: muted),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Done'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (mounted) toast(context, e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text('Request #${widget.id}'),
      leading: IconButton(
        tooltip: 'Back to home',
        icon: const Icon(Icons.arrow_back),
        onPressed: () => context.go('/'),
      ),
      actions: [
        IconButton(
          tooltip: 'Refresh request',
          onPressed: () => ref.invalidate(detailProvider(widget.id)),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: PageBody(
      children: [
        AsyncContent(
          value: ref.watch(detailProvider(widget.id)),
          retry: () => ref.invalidate(detailProvider(widget.id)),
          builder: (task) {
            final status = task['status'];
            final owner = task['is_owner'] == true;
            final assigned = task['is_assigned'] == true;
            final closed = [
              'completed',
              'cancelled',
              'closed',
            ].contains(status);
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Enter(
                  child: CarePanel(
                    color: status == 'completed' ? pale : forest,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          status == 'completed'
                              ? Icons.task_alt_rounded
                              : Icons.volunteer_activism_outlined,
                          color: status == 'completed' ? forest : coral,
                          size: 36,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          task['title'],
                          style: TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w700,
                            color: status == 'completed'
                                ? forest
                                : Colors.white,
                          ),
                        ),
                        const SizedBox(height: 16),
                        StatusPill(status),
                        const SizedBox(height: 14),
                        Text(
                          timeLabel(task['scheduled_start']),
                          style: TextStyle(
                            color: status == 'completed'
                                ? muted
                                : const Color(0xFFD7E7DB),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SectionLabel('Your helping hand'),
                CarePanel(
                  child: Row(
                    children: [
                      const CircleAvatar(
                        backgroundColor: pale,
                        child: Icon(Icons.person_outline, color: forest),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              task['volunteer']?['name'] ??
                                  'Finding the right neighbour',
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 17,
                              ),
                            ),
                            Text(
                              task['volunteer'] != null
                                  ? 'Community-reviewed volunteer'
                                  : 'We check service area, time and eligibility.',
                              style: const TextStyle(
                                color: muted,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (task['resolution_reason'] != null) ...[
                  const SizedBox(height: 16),
                  CarePanel(
                    color: const Color(0xFFFFEBDD),
                    child: Text(task['resolution_reason']),
                  ),
                ],
                if (assigned && status == 'accepted') ...[
                  _gap(),
                  BusyButton(
                    busy: busy,
                    onPressed: () => action('start'),
                    label: 'Start this task',
                    icon: Icons.play_arrow_rounded,
                  ),
                ],
                if (assigned && status == 'in_progress') ...[
                  _gap(),
                  BusyButton(
                    busy: busy,
                    onPressed: () => action('arrive'),
                    label: 'Ready to hand over',
                    icon: Icons.inventory_2_outlined,
                  ),
                ],
                if (owner && status == 'awaiting_confirmation') ...[
                  _gap(),
                  BusyButton(
                    busy: busy,
                    onPressed: showCode,
                    label: 'I received my items · Show code',
                    icon: Icons.verified_outlined,
                  ),
                ],
                if (assigned && status == 'awaiting_confirmation') ...[
                  _gap(),
                  BusyButton(
                    busy: busy,
                    onPressed: () async {
                      final code = await ask(
                        'Verify the handover',
                        'Recipient’s six-digit code',
                        code: true,
                      );
                      if (code != null) {
                        await action('confirm', data: {'code': code});
                      }
                    },
                    label: 'Enter handover code',
                    icon: Icons.pin_outlined,
                  ),
                ],
                const SectionLabel('Collection & delivery'),
                CarePanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _line(
                        Icons.storefront_outlined,
                        'COLLECT FROM',
                        task['pickup_address'],
                      ),
                      const Divider(height: 32),
                      _line(
                        Icons.home_outlined,
                        'DELIVER TO',
                        '${task['beneficiary_name']}\n${task['delivery_address']}',
                      ),
                      const Divider(height: 32),
                      _line(
                        Icons.phone_outlined,
                        'RECIPIENT CONTACT',
                        task['contact_phone'],
                      ),
                      if ((task['notes'] ?? '').toString().isNotEmpty) ...[
                        const Divider(height: 32),
                        Text(
                          task['notes'],
                          style: const TextStyle(color: muted),
                        ),
                      ],
                    ],
                  ),
                ),
                const SectionLabel('Progress, kept clear'),
                CarePanel(
                  child: Column(
                    children: [
                      for (final event in task['events'])
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Padding(
                                padding: EdgeInsets.only(top: 4),
                                child: Icon(
                                  Icons.check_circle_outline,
                                  size: 18,
                                  color: forest,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      event['action'].toString().replaceAll(
                                        '_',
                                        ' ',
                                      ),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    Text(
                                      timeLabel(event['at']),
                                      style: const TextStyle(
                                        fontSize: 11,
                                        color: muted,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                if (!closed) ...[
                  const SizedBox(height: 20),
                  OutlinedButton.icon(
                    onPressed: busy
                        ? null
                        : () async {
                            final reason = await ask(
                              'Cancel or withdraw',
                              'Tell the community team why',
                            );
                            if (reason != null) {
                              await action('cancel', data: {'reason': reason});
                              if (mounted && !owner) {
                                this.context.go('/');
                              }
                            }
                          },
                    icon: const Icon(Icons.close),
                    label: const Text('Cancel / withdraw from task'),
                  ),
                ],
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: busy
                      ? null
                      : () async {
                          final concern = await ask(
                            'Report a concern',
                            'What should the community team know?',
                          );
                          if (concern != null) {
                            await action(
                              'report',
                              data: {'description': concern},
                            );
                          }
                        },
                  icon: const Icon(Icons.flag_outlined),
                  label: const Text('Ask the community team to review'),
                ),
                const SizedBox(height: 12),
                const Text(
                  'This service handles everyday assistance. For an emergency, contact the appropriate local emergency service.',
                  style: TextStyle(fontSize: 11, color: muted),
                  textAlign: TextAlign.center,
                ),
              ],
            );
          },
        ),
      ],
    ),
  );
  Widget _gap() => const SizedBox(height: 20);
  Widget _line(IconData icon, String label, String value) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, color: forest, size: 22),
      const SizedBox(width: 14),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 10,
                letterSpacing: 1.2,
                color: muted,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Text(value),
          ],
        ),
      ),
    ],
  );
}
