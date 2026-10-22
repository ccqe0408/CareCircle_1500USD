import 'package:carecircle/main.dart' as app;
import 'package:carecircle/core/design.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'Family publishes, volunteer accepts and verifies a real handover',
    (tester) async {
      const storage = FlutterSecureStorage();
      await storage.deleteAll();
      app.main();
      Future<void> until(Finder finder, {int seconds = 30}) async {
        for (int n = 0; n < seconds * 4; n++) {
          await tester.pump(const Duration(milliseconds: 250));
          if (finder.evaluate().isNotEmpty) return;
        }
        throw TestFailure('Not found: $finder');
      }

      Future<void> tap(Finder finder) async {
        await until(finder);
        await tester.ensureVisible(finder);
        await tester.pumpAndSettle(
          const Duration(milliseconds: 100),
          EnginePhase.sendSemanticsUpdate,
          const Duration(seconds: 20),
        );
        await tester.tap(finder);
        await tester.pumpAndSettle(
          const Duration(milliseconds: 100),
          EnginePhase.sendSemanticsUpdate,
          const Duration(seconds: 20),
        );
      }

      Future<void> login(String email) async {
        await until(find.byKey(const ValueKey('email')));
        await tester.enterText(find.byKey(const ValueKey('email')), email);
        await tester.enterText(
          find.byKey(const ValueKey('password')),
          'CircleDemo2026!',
        );
        await tap(find.text('Sign in'));
        await until(find.byTooltip('Your profile'));
      }

      Future<void> logout() async {
        if (find.byTooltip('Back to home').evaluate().isNotEmpty) {
          await tap(find.byTooltip('Back to home'));
        }
        await tap(find.byTooltip('Your profile'));
        await tap(find.text('Sign out'));
        await until(find.text('Sign in'));
      }

      Future<void> screenshot(String name) async {
        await tester.pumpAndSettle(
          const Duration(milliseconds: 100),
          EnginePhase.sendSemanticsUpdate,
          const Duration(seconds: 20),
        );
        debugPrint('CARECIRCLE_CAPTURE:$name');
        await Future<void>.delayed(const Duration(seconds: 2));
        await tester.pump(const Duration(seconds: 1));
      }

      await login('family@carecircle.test');

      await screenshot('01-family-home');
      await tap(find.text('Request a helping hand'));
      await tap(find.text('Continue'));
      final title =
          'Groceries for Mrs Tan · ${DateTime.now().millisecondsSinceEpoch % 10000}';
      await tester.enterText(
        find.byKey(const ValueKey('request-title')),
        title,
      );
      await tester.enterText(
        find.byKey(const ValueKey('pickup-address')),
        'DEMO ONLY · Maple Market collection counter',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Useful details (optional)'),
        'AUTOMATED_DEMO: fictional collection for workflow verification.',
      );
      await tap(find.byType(CheckboxListTile));
      await tap(find.text('Publish request'));
      await until(find.text('Your helping hand'));
      await screenshot('02-request-published');
      final dio = Dio(
        BaseOptions(
          baseUrl: 'http://10.0.2.2:8088/api/v1',
          headers: {'Accept': 'application/json'},
        ),
      );
      final familyLogin = await dio.post(
        '/login',
        data: {
          'email': 'family@carecircle.test',
          'password': 'CircleDemo2026!',
        },
      );
      final familyHeaders = Options(
        headers: {'Authorization': 'Bearer ${familyLogin.data['token']}'},
      );
      final list =
          (await dio.get('/requests', options: familyHeaders)).data['data']
              as List;
      final id = list.firstWhere((r) => r['title'] == title)['id'];
      bool offered = false;
      for (var n = 0; n < 30; n++) {
        final r = (await dio.get('/requests/$id', options: familyHeaders)).data;
        if (r['status'] == 'offered') {
          offered = true;
          break;
        }
        await tester.pump(const Duration(seconds: 1));
        await Future<void>.delayed(const Duration(milliseconds: 300));
      }
      expect(
        offered,
        isTrue,
        reason: 'The real database queue must process matching',
      );
      await logout();
      await login('ali@carecircle.test');
      await tap(find.text('Volunteer', skipOffstage: false));
      await until(find.text(title));
      final card = find.ancestor(
        of: find.text(title),
        matching: find.byType(CarePanel),
      );
      await tap(
        find.descendant(of: card, matching: find.text('Accept invitation')),
      );
      await until(find.text('Start this task'));
      await screenshot('03-volunteer-accepted');
      await tap(find.text('Start this task'));
      await until(find.text('Ready to hand over'));
      await screenshot('04-task-in-progress');
      await tap(find.text('Ready to hand over'));
      await until(find.text('Enter handover code'));
      await logout();
      await login('family@carecircle.test');
      await until(find.text(title));
      await tap(find.text(title));
      await until(find.text('I received my items · Show code'));
      await tap(find.text('I received my items · Show code'));
      await until(find.text('Confirm your handover'));
      final codeFinder = find.byWidgetPredicate(
        (w) => w is Text && RegExp(r'^\d{6}$').hasMatch(w.data ?? ''),
      );
      expect(codeFinder, findsOneWidget);
      final code = (tester.widget<Text>(codeFinder)).data!;
      await screenshot('05-recipient-handover-code');
      await tap(find.text('Done'));
      await logout();
      await login('ali@carecircle.test');
      await tap(find.text('Volunteer', skipOffstage: false));
      await until(find.text(title));
      await tap(find.text(title));
      await tap(find.text('Enter handover code'));
      await until(find.text('Verify the handover'));
      await tester.enterText(
        find.byType(TextFormField),
        code == '111111' ? '222222' : '111111',
      );
      await tap(find.text('Confirm'));
      await until(
        find.text(
          'The code is incorrect or expired. Check with the recipient.',
        ),
      );
      await screenshot('06-wrong-code-rejected');
      await tester.pump(const Duration(seconds: 4));
      await tap(find.text('Enter handover code'));
      await until(find.text('Verify the handover'));
      await tester.enterText(find.byType(TextFormField), code);
      await tap(find.text('Confirm'));
      await until(find.text('Handover complete'));
      await screenshot('07-handover-completed');
      final persisted = (await dio.get(
        '/requests/$id',
        options: familyHeaders,
      )).data;
      expect(persisted['status'], 'completed');
      expect(persisted['completion_kind'], 'verified_handover');
      await logout();
      await login('family@carecircle.test');
      await until(find.text(title));
      await tap(find.text(title));
      await until(find.text('Handover complete'));
      await screenshot('08-family-persisted-completion');
      final replay = await dio.post(
        '/requests/$id/confirm',
        data: {'code': code},
        options: Options(
          headers: {'Authorization': 'Bearer ${familyLogin.data['token']}'},
          validateStatus: (_) => true,
        ),
      );
      expect(replay.statusCode, 403);
      binding.reportData = {
        'request_id': id,
        'status': 'completed',
        'queue_processed': offered,
        'wrong_code_rejected': true,
        'screenshots': binding.reportData?['screenshots'],
      };
    },
  );
}
