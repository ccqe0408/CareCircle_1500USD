import 'package:carecircle/core/design.dart';
import 'package:carecircle/core/api.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:carecircle/core/state.dart';
import 'package:carecircle/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class SignedOut extends SessionController {
  @override
  Future<Json?> build() async => null;
}

void main() {
  testWidgets('Login validates required fields before any network request', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [sessionProvider.overrideWith(SignedOut.new)],
        child: const CareCircleApp(),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('CareCircle'), findsOneWidget);
    await tester.ensureVisible(find.text('Sign in'));
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();
    expect(find.text('Enter a valid email'), findsOneWidget);
    expect(find.text('Enter your password'), findsOneWidget);
  });
  testWidgets(
    'Resident login supports large text and reduced motion without overflow',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        MaterialApp(
          theme: careTheme(),
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(390, 844),
              textScaler: TextScaler.linear(1.6),
              disableAnimations: true,
            ),
            child: const Scaffold(
              body: SingleChildScrollView(
                child: Enter(
                  child: EmptyCard(
                    title: 'Clear, accessible assistance',
                    body: 'A neighbour can help with everyday collection.',
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Clear, accessible assistance'), findsOneWidget);
    },
  );
  testWidgets(
    'Signing out returns to login without rebuilding a broken router',
    (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            sessionProvider.overrideWith(SessionDouble.new),
            homeProvider.overrideWith(
              (ref) async => {'requests': [], 'offers': []},
            ),
          ],
          child: const CareCircleApp(),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('email')),
        'family@carecircle.test',
      );
      await tester.enterText(
        find.byKey(const ValueKey('password')),
        'CircleDemo2026!',
      );
      await tester.ensureVisible(find.text('Sign in'));
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Your profile'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Sign out'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();
      expect(find.text('Sign in'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  test(
    'Real session controller clears account-scoped caches on logout and account change',
    () async {
      FlutterSecureStorage.setMockInitialValues({});
      final api = FakeCareApi();
      final container = ProviderContainer(
        overrides: [apiProvider.overrideWithValue(api)],
      );
      addTearDown(container.dispose);
      expect(await container.read(sessionProvider.future), isNull);
      await container
          .read(sessionProvider.notifier)
          .login('first@example.test', 'password');
      expect(
        (await container.read(beneficiariesProvider.future)).single['name'],
        'Recipient 1',
      );
      await container.read(homeProvider.future);
      await container.read(sessionProvider.notifier).logout();
      expect(container.read(sessionProvider).asData?.value, isNull);
      expect(await container.read(beneficiariesProvider.future), isEmpty);
      await container
          .read(sessionProvider.notifier)
          .login('second@example.test', 'password');
      expect(
        (await container.read(beneficiariesProvider.future)).single['name'],
        'Recipient 2',
      );
    },
  );
  test('Task statuses have resident-readable labels', () {
    expect(stateLabel('awaiting_confirmation'), 'Ready for handover');
    expect(stateLabel('escalated'), 'Community team reviewing');
  });
}

class SessionDouble extends SessionController {
  @override
  Future<Json?> build() async => null;
  @override
  Future<void> login(
    String email,
    String password, {
    Json? registration,
  }) async {
    state = const AsyncData({
      'id': 1,
      'name': 'Demo family',
      'email': 'family@carecircle.test',
      'phone': '000',
      'volunteer_profile': null,
    });
  }

  @override
  Future<void> logout() async {
    state = const AsyncData(null);
  }
}

class FakeCareApi extends CareApi {
  int userId = 1;
  @override
  Future<dynamic> call(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
  }) async {
    if (path == '/login') {
      userId = data?['email'] == 'second@example.test' ? 2 : 1;
      return {
        'token': 'fake-test-token',
        'user': {
          'id': userId,
          'name': 'Member $userId',
          'email': 'member$userId@example.test',
          'volunteer_profile': null,
        },
      };
    }
    if (path == '/beneficiaries') {
      return [
        {'id': userId, 'name': 'Recipient $userId'},
      ];
    }
    if (path.startsWith('/requests') || path == '/inbox') return {'data': []};
    if (path == '/logout') return {'message': 'Signed out'};
    throw StateError('Unexpected test API path: $path');
  }

  @override
  Future<void> saveToken(String value) async {
    token = value;
  }

  @override
  Future<void> clear() async {
    token = null;
  }
}
