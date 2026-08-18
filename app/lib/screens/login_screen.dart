import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'dart:convert';
import 'dart:io';
import 'package:http/io_client.dart';
import 'home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _nameController = TextEditingController();
  bool _isLoading = false;

  Future<void> _login() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً نام خود را وارد کنید')),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    // Simulate network delay
    await Future.delayed(const Duration(seconds: 1));

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user_name', name);
    await prefs.setBool('is_logged_in', true);

    if (mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (ctx) => const HomeScreen()),
      );
    }
  }

  Future<void> _googleLogin() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final GoogleSignIn googleSignIn = GoogleSignIn(
        scopes: ['email', 'profile'],
      );
      
      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      
      if (googleUser != null) {
        String? errorMessage;
        int? userId;
        
        try {
          final String apiUrl = dotenv.env['API_LOGIN_URL'] ?? 'https://haftroz.ir/backend/login.php';
          var response = await http.post(
            Uri.parse(apiUrl),
            body: {
              'google_id': googleUser.id,
              'email': googleUser.email,
              'name': googleUser.displayName ?? 'کاربر گوگل',
            },
          );

          if (response.statusCode == 200) {
            final jsonMap = jsonDecode(response.body);
            if (jsonMap['success'] == true) {
              userId = jsonMap['data']['user_id'];
            } else {
              errorMessage = jsonMap['message'];
            }
          }
        } catch (e) {
          if (e is SocketException || e.toString().contains('Failed host lookup')) {
            // DNS Fallback
            final String fallbackUrl = 'https://91.107.153.4/backend/login.php';
            final httpClient = HttpClient()
              ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
            final ioClient = IOClient(httpClient);

            var response = await ioClient.post(
              Uri.parse(fallbackUrl),
              headers: {'Host': 'haftroz.ir'},
              body: {
                'google_id': googleUser.id,
                'email': googleUser.email,
                'name': googleUser.displayName ?? 'کاربر گوگل',
              },
            );
            
            if (response.statusCode == 200) {
              final jsonMap = jsonDecode(response.body);
              if (jsonMap['success'] == true) {
                userId = jsonMap['data']['user_id'];
              } else {
                errorMessage = jsonMap['message'];
              }
            }
          }
        }

        if (userId != null) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('user_name', googleUser.displayName ?? 'کاربر گوگل');
          await prefs.setInt('user_id', userId);
          await prefs.setString('user_email', googleUser.email);
          await prefs.setBool('is_logged_in', true);

          if (mounted) {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (ctx) => const HomeScreen()),
            );
          }
        } else {
          throw Exception(errorMessage ?? 'خطا در ارتباط با سرور هنگام ثبت‌نام');
        }
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('خطا در ورود با گوگل: $error'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(32.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.auto_stories_rounded,
                  size: 100,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 24),
                const Text(
                  'به هفت روز خوش آمدید',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'هفت روز هفته، قصه بگو',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(height: 48),
                TextField(
                  controller: _nameController,
                  decoration: InputDecoration(
                    labelText: 'نام شما',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    prefixIcon: const Icon(Icons.person_rounded),
                  ),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: _isLoading ? null : _login,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('ورود / ثبت‌نام'),
                ),
                const SizedBox(height: 24),
                const Row(
                  children: [
                    Expanded(child: Divider()),
                    Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16),
                      child: Text('یا', style: TextStyle(color: Colors.grey)),
                    ),
                    Expanded(child: Divider()),
                  ],
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  onPressed: _isLoading ? null : _googleLogin,
                  icon: const Icon(Icons.g_mobiledata_rounded, size: 32),
                  label: const Text('ورود با حساب گوگل'),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
