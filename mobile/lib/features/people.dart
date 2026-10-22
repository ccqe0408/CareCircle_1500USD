import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../core/design.dart';
import '../core/state.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});
  @override
  ConsumerState<ProfileScreen> createState() => _ProfileState();
}

class _ProfileState extends ConsumerState<ProfileScreen> {
  bool busy = false;
  Future<void> edit() async {
    final user = ref.read(sessionProvider).asData!.value!;
    final name = TextEditingController(text: user['name']);
    final phone = TextEditingController(text: user['phone']);
    final form = GlobalKey<FormState>();
    final result = await showDialog<Json>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Your details'),
        content: Form(
          key: form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Name'),
                validator: (v) =>
                    v == null || v.trim().isEmpty ? 'Enter a name' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: phone,
                decoration: const InputDecoration(labelText: 'Phone'),
                validator: (v) => v == null || v.trim().isEmpty
                    ? 'Enter a phone number'
                    : null,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Back'),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(ctx, {
                  'name': name.text.trim(),
                  'phone': phone.text.trim(),
                });
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (result != null) {
      try {
        await ref.read(apiProvider).call('/me', method: 'PATCH', data: result);
        await ref.read(sessionProvider.notifier).refresh();
      } catch (e) {
        if (mounted) toast(context, e.toString());
      }
    }
    await Future<void>.delayed(const Duration(milliseconds: 250));
    name.dispose();
    phone.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(sessionProvider).asData?.value;
    if (user == null) return const SizedBox();
    return Scaffold(
      appBar: AppBar(title: const Text('Your circle')),
      body: PageBody(
        children: [
          CarePanel(
            color: forest,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const CircleAvatar(
                  radius: 28,
                  backgroundColor: pale,
                  child: Icon(Icons.person_outline, size: 30, color: forest),
                ),
                const SizedBox(height: 18),
                Text(
                  user['name'],
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  user['email'],
                  style: const TextStyle(color: Color(0xFFD6E6DB)),
                ),
                const SizedBox(height: 10),
                const Text(
                  'A neighbour. A helping hand.',
                  style: TextStyle(color: Color(0xFFB5D7BD)),
                ),
              ],
            ),
          ),
          const SectionLabel('Manage your community profile'),
          CarePanel(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                ListTile(
                  minVerticalPadding: 18,
                  leading: const Icon(Icons.person_outline),
                  title: const Text('Personal details'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: edit,
                ),
                const Divider(height: 1),
                ListTile(
                  minVerticalPadding: 18,
                  leading: const Icon(Icons.family_restroom_outlined),
                  title: const Text('Residents & family permissions'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/people'),
                ),
                const Divider(height: 1),
                ListTile(
                  minVerticalPadding: 18,
                  leading: const Icon(Icons.volunteer_activism_outlined),
                  title: const Text('Volunteer with CareCircle'),
                  subtitle: Text(
                    user['volunteer_profile'] != null
                        ? 'Status: ${user['volunteer_profile']['status']}'
                        : 'Choose how and when you can help',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/volunteer'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 26),
          BusyButton(
            busy: busy,
            label: 'Sign out',
            onPressed: () async {
              setState(() => busy = true);
              try {
                await ref.read(sessionProvider.notifier).logout();
              } catch (e) {
                if (mounted) {
                  setState(() => busy = false);
                  toast(this.context, e.toString());
                }
              }
            },
          ),
          const SizedBox(height: 22),
          const Text(
            'CareCircle · Local demonstration\nFictional people and addresses. No payments or emergency services.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: muted),
          ),
        ],
      ),
    );
  }
}

class PeopleScreen extends ConsumerStatefulWidget {
  const PeopleScreen({super.key});
  @override
  ConsumerState<PeopleScreen> createState() => _PeopleState();
}

class _PeopleState extends ConsumerState<PeopleScreen> {
  bool busy = false;
  Future<void> personDialog(Json meta, {Json? person}) async {
    final name = TextEditingController(text: person?['name']);
    final phone = TextEditingController(text: person?['phone']);
    final address = TextEditingController(text: person?['address']);
    final relationship = TextEditingController();
    final consent = TextEditingController();
    final form = GlobalKey<FormState>();
    int? zone =
        person?['zone_id'] ?? (meta['zones'] as List).firstOrNull?['id'];
    String kind = 'self';
    final result = await showDialog<Json>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => AlertDialog(
          title: Text(
            person == null ? 'Add service recipient' : 'Edit resident profile',
          ),
          content: SizedBox(
            width: 420,
            child: SingleChildScrollView(
              child: Form(
                key: form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (person == null) ...[
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'self', label: Text('Myself')),
                          ButtonSegment(value: 'family', label: Text('Family')),
                        ],
                        selected: {kind},
                        onSelectionChanged: (v) => set(() => kind = v.first),
                      ),
                      const SizedBox(height: 16),
                    ],
                    TextFormField(
                      controller: name,
                      decoration: const InputDecoration(
                        labelText: 'Recipient name',
                      ),
                      validator: required,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: phone,
                      decoration: const InputDecoration(
                        labelText: 'Contact phone',
                      ),
                      keyboardType: TextInputType.phone,
                      validator: required,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: address,
                      decoration: const InputDecoration(
                        labelText: 'Delivery address',
                      ),
                      maxLines: 2,
                      validator: required,
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: zone,
                      decoration: const InputDecoration(
                        labelText: 'Service area',
                      ),
                      items: (meta['zones'] as List)
                          .map(
                            (z) => DropdownMenuItem<int>(
                              value: z['id'],
                              child: Text(z['name']),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => zone = v,
                      validator: (v) => v == null ? 'Choose an area' : null,
                    ),
                    if (kind == 'family' && person == null) ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: relationship,
                        decoration: const InputDecoration(
                          labelText: 'Your relationship',
                        ),
                        validator: required,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: consent,
                        decoration: const InputDecoration(
                          labelText: 'How did the recipient give consent?',
                        ),
                        maxLines: 3,
                        validator: (v) => v == null || v.trim().length < 10
                            ? 'Please provide at least 10 characters'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'A community administrator must verify consent before you can request on their behalf.',
                        style: TextStyle(fontSize: 12, color: muted),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Back'),
            ),
            FilledButton(
              onPressed: () {
                if (form.currentState!.validate()) {
                  Navigator.pop(ctx, {
                    'name': name.text.trim(),
                    'phone': phone.text.trim(),
                    'address': address.text.trim(),
                    'zone_id': zone,
                    'kind': kind,
                    'relationship': relationship.text.trim(),
                    'consent_note': consent.text.trim(),
                  });
                }
              },
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
    if (result != null) {
      setState(() => busy = true);
      try {
        await ref
            .read(apiProvider)
            .call(
              person == null
                  ? '/beneficiaries'
                  : '/beneficiaries/${person['id']}',
              method: person == null ? 'POST' : 'PATCH',
              data: result,
            );
        ref.invalidate(beneficiariesProvider);
        ref.invalidate(familyProvider);
        if (mounted) {
          toast(
            context,
            result['kind'] == 'family'
                ? 'Submitted for consent verification.'
                : 'Resident profile saved.',
          );
        }
      } catch (e) {
        if (mounted) toast(context, e.toString());
      } finally {
        if (mounted) setState(() => busy = false);
      }
    }
    await Future<void>.delayed(const Duration(milliseconds: 250));
    for (final c in [name, phone, address, relationship, consent]) {
      c.dispose();
    }
  }

  String? required(String? v) =>
      v == null || v.trim().isEmpty ? 'This field is required' : null;
  Future<void> grant(Json person) async {
    final email = TextEditingController();
    final relation = TextEditingController();
    final note = TextEditingController();
    final form = GlobalKey<FormState>();
    final data = await showDialog<Json>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Authorize a family member'),
        content: SingleChildScrollView(
          child: Form(
            key: form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: email,
                  decoration: const InputDecoration(
                    labelText: 'Their registered email',
                  ),
                  validator: (v) => v == null || !v.contains('@')
                      ? 'Enter a registered email'
                      : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: relation,
                  decoration: const InputDecoration(labelText: 'Relationship'),
                  validator: required,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: note,
                  decoration: const InputDecoration(
                    labelText: 'Consent statement',
                  ),
                  maxLines: 3,
                  validator: (v) => v == null || v.trim().length < 10
                      ? 'Use at least 10 characters'
                      : null,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Back'),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(ctx, {
                  'beneficiary_id': person['id'],
                  'email': email.text.trim(),
                  'relationship': relation.text.trim(),
                  'consent_note': note.text.trim(),
                });
              }
            },
            child: const Text('Request approval'),
          ),
        ],
      ),
    );
    if (data != null) {
      try {
        await ref
            .read(apiProvider)
            .call('/family-authorizations', method: 'POST', data: data);
        ref.invalidate(familyProvider);
        if (mounted) {
          toast(context, 'Submitted to the community team for verification.');
        }
      } catch (e) {
        if (mounted) toast(context, e.toString());
      }
    }
    await Future<void>.delayed(const Duration(milliseconds: 250));
    email.dispose();
    relation.dispose();
    note.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(sessionProvider).asData!.value!;
    return Scaffold(
      appBar: AppBar(title: const Text('Residents & family')),
      body: PageBody(
        children: [
          Text(
            'Help for you.\nSupport for your family.',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 12),
          const Text(
            'Family permissions are verified by your community team. You can withdraw consent at any time.',
            style: TextStyle(color: muted),
          ),
          const SizedBox(height: 24),
          AsyncContent(
            value: ref.watch(metaProvider),
            retry: () => ref.invalidate(metaProvider),
            builder: (meta) => Column(
              children: [
                BusyButton(
                  busy: busy,
                  onPressed: () => personDialog(meta),
                  label: 'Add a service recipient',
                  icon: Icons.person_add_alt_1_outlined,
                ),
                const SizedBox(height: 20),
                AsyncContent(
                  value: ref.watch(beneficiariesProvider),
                  retry: () => ref.invalidate(beneficiariesProvider),
                  builder: (people) => people.isEmpty
                      ? const EmptyCard(
                          title: 'Your circle starts here',
                          body:
                              'Add your own resident profile or a family member who has asked for help.',
                        )
                      : Column(
                          children: [
                            for (final p in people)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 14),
                                child: CarePanel(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        p['name'],
                                        style: Theme.of(
                                          context,
                                        ).textTheme.titleLarge,
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        p['address'],
                                        style: const TextStyle(color: muted),
                                      ),
                                      const SizedBox(height: 12),
                                      Text(
                                        p['can_request'] == true
                                            ? 'Ready to request assistance'
                                            : 'Authorization required / under review',
                                        style: const TextStyle(
                                          color: forest,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                      if (p['user_id'] == user['id'])
                                        Wrap(
                                          spacing: 8,
                                          children: [
                                            TextButton.icon(
                                              onPressed: () =>
                                                  personDialog(meta, person: p),
                                              icon: const Icon(
                                                Icons.edit_outlined,
                                              ),
                                              label: const Text('Edit'),
                                            ),
                                            TextButton.icon(
                                              onPressed: () => grant(p),
                                              icon: const Icon(
                                                Icons.family_restroom,
                                              ),
                                              label: const Text(
                                                'Authorize family',
                                              ),
                                            ),
                                          ],
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                          ],
                        ),
                ),
              ],
            ),
          ),
          const SectionLabel('Family permissions'),
          AsyncContent(
            value: ref.watch(familyProvider),
            retry: () => ref.invalidate(familyProvider),
            builder: (grants) => grants.isEmpty
                ? const Text(
                    'No family permissions yet.',
                    style: TextStyle(color: muted),
                  )
                : Column(
                    children: [
                      for (final g in grants)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: CarePanel(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${g['beneficiary']['name']} · ${g['relationship']}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text('Status: ${g['status']}'),
                                if (g['status'] == 'approved' ||
                                    g['status'] == 'pending')
                                  TextButton(
                                    onPressed: () async {
                                      final confirmed = await showDialog<bool>(
                                        context: context,
                                        builder: (ctx) => AlertDialog(
                                          title: const Text(
                                            'Withdraw this permission?',
                                          ),
                                          content: const Text(
                                            'The family member will lose access. Open requests will be sent to the community team for review.',
                                          ),
                                          actions: [
                                            TextButton(
                                              onPressed: () =>
                                                  Navigator.pop(ctx, false),
                                              child: const Text(
                                                'Keep permission',
                                              ),
                                            ),
                                            FilledButton(
                                              onPressed: () =>
                                                  Navigator.pop(ctx, true),
                                              child: const Text('Withdraw'),
                                            ),
                                          ],
                                        ),
                                      );
                                      if (confirmed == true) {
                                        try {
                                          await ref
                                              .read(apiProvider)
                                              .call(
                                                '/family-authorizations/${g['id']}/revoke',
                                                method: 'POST',
                                              );
                                          ref.invalidate(familyProvider);
                                          ref.invalidate(beneficiariesProvider);
                                          refreshWork(ref);
                                        } catch (e) {
                                          if (context.mounted) {
                                            toast(context, e.toString());
                                          }
                                        }
                                      }
                                    },
                                    child: const Text('Withdraw permission'),
                                  ),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class VolunteerScreen extends ConsumerStatefulWidget {
  const VolunteerScreen({super.key});
  @override
  ConsumerState<VolunteerScreen> createState() => _VolunteerState();
}

class _VolunteerState extends ConsumerState<VolunteerScreen> {
  List<int> zones = [], categories = [], days = [1, 2, 3, 4, 5, 6, 7];
  TimeOfDay from = const TimeOfDay(hour: 8, minute: 0),
      to = const TimeOfDay(hour: 20, minute: 0);
  int capacity = 1;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    final p = ref.read(sessionProvider).asData?.value?['volunteer_profile'];
    if (p != null) {
      zones = List<int>.from(p['zone_ids']);
      categories = List<int>.from(p['category_ids']);
      capacity = p['max_tasks'];
      final windows = p['availability'] as List;
      if (windows.isNotEmpty) {
        days = windows.map((w) => w['day'] as int).toSet().toList();
        from = parse(windows.first['start']);
        to = parse(windows.first['end']);
      }
    }
  }

  TimeOfDay parse(String s) {
    final parts = s.split(':');
    return TimeOfDay(hour: int.parse(parts[0]), minute: int.parse(parts[1]));
  }

  String fmt(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  Future<void> submit() async {
    if (zones.isEmpty || categories.isEmpty || days.isEmpty) {
      toast(context, 'Choose a service area, category and at least one day.');
      return;
    }
    if (to.hour * 60 + to.minute <= from.hour * 60 + from.minute) {
      toast(context, 'End time must be later than start time.');
      return;
    }
    setState(() => busy = true);
    try {
      await ref
          .read(apiProvider)
          .call(
            '/volunteer-profile',
            method: 'PUT',
            data: {
              'zone_ids': zones,
              'category_ids': categories,
              'max_tasks': capacity,
              'availability': days
                  .map((d) => {'day': d, 'start': fmt(from), 'end': fmt(to)})
                  .toList(),
            },
          );
      if (mounted) {
        toast(context, 'Application submitted for community approval.');
      }
      await ref.read(sessionProvider.notifier).refresh();
    } catch (e) {
      if (mounted) toast(context, e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Volunteer profile')),
    body: PageBody(
      children: [
        Text(
          'Give a little time.\nMake someone’s day.',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: 14),
        const Text(
          'Tell us where and when you can help. New applications and changes require community approval.',
          style: TextStyle(color: muted),
        ),
        const SizedBox(height: 20),
        AsyncContent(
          value: ref.watch(metaProvider),
          retry: () => ref.invalidate(metaProvider),
          builder: (meta) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SectionLabel('Your service areas'),
              CarePanel(
                padding: const EdgeInsets.all(8),
                child: Column(
                  children: [
                    for (final z in meta['zones'])
                      CheckboxListTile(
                        value: zones.contains(z['id']),
                        title: Text(z['name']),
                        onChanged: (v) => setState(() {
                          v == true
                              ? zones.add(z['id'])
                              : zones.remove(z['id']);
                        }),
                      ),
                  ],
                ),
              ),
              const SectionLabel('How you can help'),
              CarePanel(
                padding: const EdgeInsets.all(8),
                child: Column(
                  children: [
                    for (final c in meta['categories'])
                      CheckboxListTile(
                        value: categories.contains(c['id']),
                        title: Text(c['name']),
                        onChanged: (v) => setState(() {
                          v == true
                              ? categories.add(c['id'])
                              : categories.remove(c['id']);
                        }),
                      ),
                  ],
                ),
              ),
              const SectionLabel('Available days'),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  for (int d = 1; d <= 7; d++)
                    FilterChip(
                      label: Text(
                        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][d -
                            1],
                      ),
                      selected: days.contains(d),
                      onSelected: (v) => setState(() {
                        v ? days.add(d) : days.remove(d);
                      }),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              const Text(
                'Same time window on selected days · Malaysia time',
                style: TextStyle(color: muted, fontSize: 12),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        final v = await showTimePicker(
                          context: context,
                          initialTime: from,
                        );
                        if (v != null) setState(() => from = v);
                      },
                      child: Text('From ${fmt(from)}'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        final v = await showTimePicker(
                          context: context,
                          initialTime: to,
                        );
                        if (v != null) setState(() => to = v);
                      },
                      child: Text('Until ${fmt(to)}'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              DropdownButtonFormField<int>(
                initialValue: capacity,
                decoration: const InputDecoration(
                  labelText: 'Maximum active tasks',
                ),
                items: [1, 2, 3]
                    .map(
                      (n) => DropdownMenuItem(
                        value: n,
                        child: Text('$n active ${n == 1 ? 'task' : 'tasks'}'),
                      ),
                    )
                    .toList(),
                onChanged: (v) => capacity = v ?? 1,
              ),
              const SizedBox(height: 24),
              const CarePanel(
                color: pale,
                child: Text(
                  'Community staff review your identity and service area. Approval is not a guarantee of safety. Only accept tasks you can complete.',
                ),
              ),
              const SizedBox(height: 24),
              BusyButton(
                busy: busy,
                onPressed: submit,
                label: 'Submit for community review',
                icon: Icons.check_rounded,
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
