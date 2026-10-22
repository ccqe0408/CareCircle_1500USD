import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/design.dart';
import '../core/state.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginState();
}

class _LoginState extends ConsumerState<LoginScreen> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController();
  final password = TextEditingController();
  final name = TextEditingController();
  final phone = TextEditingController();
  final confirmation = TextEditingController();
  bool register = false, busy = false;
  String? error;
  @override
  void dispose() {
    for (final c in [email, password, name, phone, confirmation]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref
          .read(sessionProvider.notifier)
          .login(
            email.text,
            password.text,
            registration: register
                ? {
                    'name': name.text.trim(),
                    'email': email.text.trim(),
                    'phone': phone.text.trim(),
                    'password': password.text,
                    'password_confirmation': confirmation.text,
                  }
                : null,
          );
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: PageBody(
        children: [
          const SizedBox(height: 34),
          const Align(
            alignment: Alignment.centerLeft,
            child: CircleAvatar(
              radius: 30,
              backgroundColor: forest,
              child: Icon(
                Icons.volunteer_activism_rounded,
                color: Colors.white,
                size: 30,
              ),
            ),
          ),
          const SizedBox(height: 24),
          Text('CareCircle', style: Theme.of(context).textTheme.headlineLarge),
          const SizedBox(height: 8),
          Text(
            register
                ? 'Your neighbourhood starts with you.'
                : 'A little help.\nA closer neighbourhood.',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 14),
          const Text(
            'Everyday help, with a trusted handover.',
            style: TextStyle(color: muted),
          ),
          const SizedBox(height: 28),
          Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (register) ...[
                  TextFormField(
                    controller: name,
                    decoration: const InputDecoration(labelText: 'Full name'),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Enter your name'
                        : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Phone number',
                    ),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Enter a phone number'
                        : null,
                  ),
                  const SizedBox(height: 14),
                ],
                TextFormField(
                  key: const ValueKey('email'),
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Email address',
                    prefixIcon: Icon(Icons.alternate_email),
                  ),
                  validator: (v) => v == null || !v.contains('@')
                      ? 'Enter a valid email'
                      : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  key: const ValueKey('password'),
                  controller: password,
                  obscureText: true,
                  enableSuggestions: false,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    prefixIcon: Icon(Icons.lock_outline),
                  ),
                  validator: (v) => v == null || v.isEmpty
                      ? 'Enter your password'
                      : register && v.length < 10
                      ? 'Use at least 10 characters, letters and numbers'
                      : null,
                  onFieldSubmitted: (_) => submit(),
                ),
                if (register) ...[
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: confirmation,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Confirm password',
                    ),
                    validator: (v) =>
                        v != password.text ? 'Passwords do not match' : null,
                  ),
                ],
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    child: Text(
                      error!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                const SizedBox(height: 22),
                BusyButton(
                  busy: busy,
                  onPressed: submit,
                  label: register ? 'Create account' : 'Sign in',
                  icon: Icons.arrow_forward_rounded,
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: busy
                      ? null
                      : () => setState(() {
                          register = !register;
                          error = null;
                        }),
                  child: Text(
                    register
                        ? 'Already a member? Sign in'
                        : 'New here? Create an account',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'LOCAL DEMONSTRATION\nFictional residents and addresses. Non-emergency assistance.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: muted, letterSpacing: .4),
          ),
        ],
      ),
    ),
  );
}
