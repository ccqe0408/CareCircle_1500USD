import 'package:carecircle/core/api.dart';
import 'package:carecircle/core/state.dart';
import 'package:carecircle/main.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'A real connection failure keeps the session recoverable and retry reconnects',
    (tester) async {
      final live = Dio(
        BaseOptions(
          baseUrl: 'http://10.0.2.2:8088/api/v1',
          headers: {'Accept': 'application/json'},
          connectTimeout: const Duration(seconds: 5),
          receiveTimeout: const Duration(seconds: 5),
        ),
      );
      final response = await live.post(
        '/login',
        data: {
          'email': 'family@carecircle.test',
          'password': 'CircleDemo2026!',
        },
      );
      const storage = FlutterSecureStorage();
      await storage.write(
        key: 'carecircle_token',
        value: response.data['token'],
      );
      final failing = Dio(
        BaseOptions(
          baseUrl: 'http://10.0.2.2:8099/api/v1',
          headers: {'Accept': 'application/json'},
          connectTimeout: const Duration(seconds: 2),
          receiveTimeout: const Duration(seconds: 2),
        ),
      );
      final api = CareApi(client: failing);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiProvider.overrideWithValue(api)],
          child: const CareCircleApp(),
        ),
      );
      Future<void> until(Finder f) async {
        for (var i = 0; i < 100; i++) {
          await tester.pump(const Duration(milliseconds: 100));
          if (f.evaluate().isNotEmpty) return;
        }
        throw TestFailure('Expected visible state: $f');
      }

      await until(find.text('Try again'));
      expect(find.textContaining('Could not connect.'), findsOneWidget);
      expect(await storage.read(key: 'carecircle_token'), isNotNull);
      failing.options.baseUrl = 'http://10.0.2.2:8088/api/v1';
      await tester.tap(find.text('Try again'));
      await until(find.byTooltip('Your profile'));
      expect(find.text('CareCircle'), findsOneWidget);
      await live.post(
        '/logout',
        options: Options(
          headers: {'Authorization': 'Bearer ${response.data['token']}'},
        ),
      );
      await storage.delete(key: 'carecircle_token');
      binding.reportData = {
        'connection_refused_verified': true,
        'retry_reconnected_to_real_api': true,
      };
    },
  );
}
