import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../core/design.dart';
import '../core/state.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeState();
}

class _HomeState extends ConsumerState<HomeScreen> {
  int tab = 0;
  int? busyOffer;
  Future<void> offerAction(Json offer, bool accept) async {
    setState(() => busyOffer = offer['id']);
    try {
      final result = await ref
          .read(apiProvider)
          .call(
            '/offers/${offer['id']}/${accept ? 'accept' : 'decline'}',
            method: 'POST',
          );
      refreshWork(ref);
      if (mounted) {
        if (accept) {
          context.push('/request/${result['id']}');
        } else {
          toast(
            context,
            'Invitation declined. We will look for another volunteer.',
          );
        }
      }
    } catch (e) {
      if (mounted) toast(context, e.toString());
    } finally {
      if (mounted) setState(() => busyOffer = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(sessionProvider).asData!.value!;
    final volunteer = ref.watch(volunteerModeProvider);
    final profile = user['volunteer_profile'];
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.volunteer_activism_rounded, size: 24),
            SizedBox(width: 9),
            Text(
              'CareCircle',
              style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -.6),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Your profile',
            onPressed: () => context.push('/profile'),
            icon: const Icon(Icons.account_circle_outlined),
          ),
          const SizedBox(width: 8),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (v) => setState(() => tab = v),
        backgroundColor: Colors.white,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long),
            label: 'My tasks',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_none),
            selectedIcon: Icon(Icons.notifications),
            label: 'Inbox',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          refreshWork(ref);
          await ref.read(homeProvider.future);
        },
        child: PageBody(
          children: [
            if (tab != 2) ...[
              if (profile != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 20),
                  child: SegmentedButton<bool>(
                    segments: const [
                      ButtonSegment(
                        value: false,
                        label: Text('Resident'),
                        icon: Icon(Icons.home_outlined),
                      ),
                      ButtonSegment(
                        value: true,
                        label: Text('Volunteer'),
                        icon: Icon(Icons.favorite_outline),
                      ),
                    ],
                    selected: {volunteer},
                    onSelectionChanged: (s) =>
                        ref.read(volunteerModeProvider.notifier).set(s.first),
                  ),
                ),
              if (tab == 0) ...[
                Text(
                  'Hello, ${user['name'].toString().split(' ').first} 👋',
                  style: const TextStyle(
                    color: muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  volunteer
                      ? 'A little time.\nA real difference.'
                      : 'Good neighbours.\nEveryday support.',
                  style: Theme.of(context).textTheme.headlineLarge,
                ),
                const SizedBox(height: 22),
                Enter(
                  child: CarePanel(
                    color: forest,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'YOUR COMMUNITY',
                                style: TextStyle(
                                  color: Color(0xFFB8D6BE),
                                  fontSize: 11,
                                  letterSpacing: 2,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            Icon(
                              volunteer
                                  ? Icons.spa_outlined
                                  : Icons.waving_hand_outlined,
                              color: coral,
                              size: 32,
                            ),
                          ],
                        ),
                        const SizedBox(height: 15),
                        Text(
                          volunteer
                              ? 'Help someone nearby.'
                              : 'You don’t have to\ndo it all alone.',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 25,
                            height: 1.2,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          volunteer
                              ? 'Choose invitations that fit your time. Every task has a clear handover.'
                              : 'Ask for help with prepaid groceries or an ordinary parcel.',
                          style: const TextStyle(color: Color(0xFFD6E6DB)),
                        ),
                        const SizedBox(height: 20),
                        if (!volunteer)
                          FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: const Color(0xFFE7F2D9),
                              foregroundColor: forest,
                            ),
                            onPressed: () => context.push('/new'),
                            icon: const Icon(Icons.add),
                            label: const Text('Request a helping hand'),
                          ),
                      ],
                    ),
                  ),
                ),
                if (volunteer && profile?['status'] != 'approved')
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: CarePanel(
                      color: const Color(0xFFFFECDD),
                      child: Text(
                        'Volunteer status: ${profile?['status']}. Community approval is required before receiving invitations.',
                      ),
                    ),
                  ),
              ],
              SectionLabel(
                tab == 1
                    ? 'Your task history'
                    : volunteer
                    ? 'Invitations & active tasks'
                    : 'Your requests',
                trailing: IconButton(
                  tooltip: 'Refresh tasks',
                  onPressed: () => ref.invalidate(homeProvider),
                  icon: const Icon(Icons.refresh),
                ),
              ),
              AsyncContent(
                value: ref.watch(homeProvider),
                retry: () => ref.invalidate(homeProvider),
                builder: (data) {
                  final requests = List<Json>.from(data['requests']);
                  final offers = List<Json>.from(data['offers']);
                  return Column(
                    children: [
                      for (final offer in offers)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 14),
                          child: Enter(
                            child: CarePanel(
                              color: const Color(0xFFEDF1E5),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Row(
                                    children: [
                                      Icon(
                                        Icons.near_me_outlined,
                                        color: forest,
                                        size: 18,
                                      ),
                                      SizedBox(width: 8),
                                      Text(
                                        'A MATCH FOR YOU',
                                        style: TextStyle(
                                          fontSize: 11,
                                          letterSpacing: 1.2,
                                          fontWeight: FontWeight.w700,
                                          color: forest,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  Text(
                                    offer['request']['title'],
                                    style: Theme.of(
                                      context,
                                    ).textTheme.titleLarge,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    '${offer['request']['zone']} · ${offer['request']['category']}',
                                    style: const TextStyle(color: muted),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    timeLabel(
                                      offer['request']['scheduled_start'],
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  const Text(
                                    'Exact address is shared only after acceptance.',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: muted,
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  BusyButton(
                                    busy: busyOffer == offer['id'],
                                    onPressed: busyOffer == null
                                        ? () => offerAction(offer, true)
                                        : null,
                                    label: 'Accept invitation',
                                    icon: Icons.check_rounded,
                                  ),
                                  Center(
                                    child: TextButton(
                                      onPressed: busyOffer == null
                                          ? () => offerAction(offer, false)
                                          : null,
                                      child: const Text('Not this time'),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      if (requests.isEmpty && offers.isEmpty)
                        EmptyCard(
                          title: volunteer
                              ? 'No invitations right now'
                              : 'Your first helping hand starts here',
                          body: volunteer
                              ? 'We will show invitations that match your approved service area and availability. Pull down to refresh.'
                              : 'Create a request for yourself or an authorized family member.',
                        ),
                      for (final task in requests)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 14),
                          child: TaskCard(task: task),
                        ),
                    ],
                  );
                },
              ),
              if (tab == 0) ...[
                const SectionLabel('Simple. Considered. Connected.'),
                const CarePanel(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.verified_user_outlined,
                        color: forest,
                        size: 28,
                      ),
                      SizedBox(width: 14),
                      Expanded(
                        child: Text(
                          'Community-reviewed volunteers. Clear progress. A one-time code after you receive your items.',
                          style: TextStyle(color: muted),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'For everyday assistance, not emergencies.\nDemonstration uses fictional people and addresses.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: muted, fontSize: 11),
                ),
              ],
            ] else ...[
              Text(
                'Your community inbox',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              const Text(
                'Invitations and updates, all in one place.',
                style: TextStyle(color: muted),
              ),
              const SizedBox(height: 24),
              AsyncContent(
                value: ref.watch(inboxProvider),
                retry: () => ref.invalidate(inboxProvider),
                builder: (messages) => messages.isEmpty
                    ? const EmptyCard(
                        title: 'You’re all caught up',
                        body: 'Task updates will appear here.',
                        icon: Icons.mark_email_read_outlined,
                      )
                    : Column(
                        children: messages
                            .map(
                              (m) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: CarePanel(
                                  child: InkWell(
                                    onTap: () async {
                                      try {
                                        await ref
                                            .read(apiProvider)
                                            .call(
                                              '/inbox/${m['id']}/read',
                                              method: 'POST',
                                            );
                                        ref.invalidate(inboxProvider);
                                        if (context.mounted &&
                                            m['help_request_id'] != null) {
                                          context.push(
                                            '/request/${m['help_request_id']}',
                                          );
                                        }
                                      } catch (e) {
                                        if (context.mounted) {
                                          toast(context, e.toString());
                                        }
                                      }
                                    },
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            if (m['read_at'] == null) ...[
                                              const Icon(
                                                Icons.circle,
                                                size: 8,
                                                color: forest,
                                              ),
                                              const SizedBox(width: 8),
                                            ],
                                            Expanded(
                                              child: Text(
                                                m['title'],
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          m['body'],
                                          style: const TextStyle(color: muted),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class TaskCard extends StatelessWidget {
  final Json task;
  const TaskCard({super.key, required this.task});
  @override
  Widget build(BuildContext context) => Enter(
    child: CarePanel(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => context.push('/request/${task['id']}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    task['title'],
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                const SizedBox(width: 10),
                const Icon(
                  Icons.arrow_outward_rounded,
                  color: forest,
                  size: 20,
                ),
              ],
            ),
            const SizedBox(height: 12),
            StatusPill(task['status']),
            const SizedBox(height: 14),
            Row(
              children: [
                const Icon(Icons.schedule_rounded, size: 17, color: muted),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    timeLabel(task['scheduled_start']),
                    style: const TextStyle(color: muted, fontSize: 13),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '${task['zone']} · #${task['id']}',
              style: const TextStyle(fontSize: 12, color: muted),
            ),
          ],
        ),
      ),
    ),
  );
}
