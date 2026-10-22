import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api.dart';

typedef Json = Map<String, dynamic>;
// Explicit user-driven retry keeps connection failures visible instead of an endless spinner.
Duration? manualRetry(int attempt, Object error) => null;
final apiProvider = Provider<CareApi>((ref) => CareApi());
final sessionProvider = AsyncNotifierProvider<SessionController, Json?>(
  SessionController.new,
  retry: manualRetry,
);

class SessionController extends AsyncNotifier<Json?> {
  @override
  Future<Json?> build() async {
    final api = ref.read(apiProvider);
    api.onUnauthorized = () {
      state = const AsyncData(null);
    };
    api.token = await api.storage.read(key: 'carecircle_token');
    if (api.token == null) return null;
    try {
      return Map<String, dynamic>.from(await api.call('/me'));
    } on ApiFailure catch (e) {
      if (e.status == 401 || e.status == 403) {
        await api.clear();
        return null;
      }
      rethrow;
    }
  }

  Future<void> login(
    String email,
    String password, {
    Json? registration,
  }) async {
    final api = ref.read(apiProvider);
    final response = await api.call(
      registration == null ? '/login' : '/register',
      method: 'POST',
      data: registration ?? {'email': email.trim(), 'password': password},
    );
    await api.saveToken(response['token']);
    state = AsyncData(Map<String, dynamic>.from(response['user']));
  }

  Future<void> refresh() async {
    final user = await ref.read(apiProvider).call('/me');
    state = AsyncData(Map<String, dynamic>.from(user));
  }

  Future<void> logout() async {
    final api = ref.read(apiProvider);
    // Keep the session if the server cannot confirm revocation; the user can retry.
    await api.call('/logout', method: 'POST');
    await api.clear();
    state = const AsyncData(null);
  }
}

class VolunteerMode extends Notifier<bool> {
  @override
  bool build() {
    ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
    return false;
  }

  void set(bool v) => state = v;
}

final volunteerModeProvider = NotifierProvider<VolunteerMode, bool>(
  VolunteerMode.new,
);
final metaProvider = FutureProvider<Json>((ref) async {
  ref.watch(sessionProvider);
  return Map<String, dynamic>.from(await ref.watch(apiProvider).call('/meta'));
}, retry: manualRetry);
final beneficiariesProvider = FutureProvider<List<Json>>((ref) async {
  final id = ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
  if (id == null) return [];
  return (await ref.watch(apiProvider).call('/beneficiaries') as List)
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
}, retry: manualRetry);
final familyProvider = FutureProvider<List<Json>>((ref) async {
  final id = ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
  if (id == null) return [];
  return (await ref.watch(apiProvider).call('/family-authorizations') as List)
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
}, retry: manualRetry);
final homeProvider = FutureProvider<Json>((ref) async {
  final id = ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
  final mode = ref.watch(volunteerModeProvider);
  if (id == null) return {'requests': [], 'offers': []};
  final api = ref.watch(apiProvider);
  final values = await Future.wait([
    api.call('/requests?mode=${mode ? 'volunteer' : 'resident'}'),
    mode ? api.call('/offers') : Future.value([]),
  ]);
  return {'requests': values[0]['data'], 'offers': values[1]};
}, retry: manualRetry);
final detailProvider = FutureProvider.autoDispose.family<Json, int>((
  ref,
  id,
) async {
  final user = ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
  if (user == null) {
    throw const ApiFailure('Sign in to view this request.', 401);
  }
  return Map<String, dynamic>.from(
    await ref.watch(apiProvider).call('/requests/$id'),
  );
}, retry: manualRetry);
final inboxProvider = FutureProvider<List<Json>>((ref) async {
  final id = ref.watch(sessionProvider.select((s) => s.asData?.value?['id']));
  if (id == null) return [];
  return ((await ref.watch(apiProvider).call('/inbox'))['data'] as List)
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
}, retry: manualRetry);
void refreshWork(WidgetRef ref) {
  ref.invalidate(homeProvider);
  ref.invalidate(inboxProvider);
}
