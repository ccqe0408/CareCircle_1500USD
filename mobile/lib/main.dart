import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'core/design.dart';
import 'core/state.dart';
import 'features/auth.dart';
import 'features/home.dart';
import 'features/request.dart';
import 'features/people.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ProviderScope(child: CareCircleApp()));
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(sessionProvider, (previous, next) {
    refresh.value++;
  });
  final router = GoRouter(
    refreshListenable: refresh,
    initialLocation: '/',
    redirect: (context, state) {
      final signedIn = ref.read(sessionProvider).asData?.value != null;
      return !signedIn && state.matchedLocation != '/login'
          ? '/login'
          : signedIn && state.matchedLocation == '/login'
          ? '/'
          : null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
      GoRoute(
        path: '/new',
        builder: (context, state) => const NewRequestScreen(),
      ),
      GoRoute(
        path: '/request/:id',
        builder: (context, state) =>
            RequestScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/people',
        builder: (context, state) => const PeopleScreen(),
      ),
      GoRoute(
        path: '/volunteer',
        builder: (context, state) => const VolunteerScreen(),
      ),
      GoRoute(
        path: '/profile',
        builder: (context, state) => const ProfileScreen(),
      ),
    ],
  );
  ref.onDispose(() {
    router.dispose();
    refresh.dispose();
  });
  return router;
});

class CareCircleApp extends ConsumerWidget {
  const CareCircleApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    if (session.isLoading) {
      return MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: careTheme(),
        home: const Scaffold(
          body: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.volunteer_activism_rounded, size: 56, color: forest),
                SizedBox(height: 24),
                CircularProgressIndicator(),
              ],
            ),
          ),
        ),
      );
    }
    if (session.hasError) {
      return MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: careTheme(),
        home: Scaffold(
          body: SafeArea(
            child: PageBody(
              children: [
                const SizedBox(height: 80),
                AsyncContent(
                  value: session,
                  builder: (_) => const SizedBox(),
                  retry: () => ref.invalidate(sessionProvider),
                ),
              ],
            ),
          ),
        ),
      );
    }
    return MaterialApp.router(
      title: 'CareCircle',
      debugShowCheckedModeBanner: false,
      theme: careTheme(),
      routerConfig: ref.watch(routerProvider),
    );
  }
}
