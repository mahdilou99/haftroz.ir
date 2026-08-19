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
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  Future<void> _checkLoginStatus() async {
    final prefs = await SharedPreferences.getInstance();
    final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
    
    if (isLoggedIn && mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (ctx) => const HomeScreen()),
      );
    }
  }

  Future<void> _googleLogin() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final GoogleSignIn googleSignIn = GoogleSignIn();
      final GoogleSignInAccount? user = await googleSignIn.signIn();

      if (user != null) {
        final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
        final String url = baseUrl.replaceAll('upload.php', 'login.php');

        final httpClient = HttpClient()
          ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
        final ioClient = IOClient(httpClient);

        final response = await ioClient.post(
          Uri.parse(url),
          headers: {
            'Host': 'haftroz.ir',
          },
          body: {
            'google_id': user.id,
            'email': user.email,
            'name': user.displayName ?? 'کاربر گوگل',
          },
        );

        int? userId;
        String? errorMessage;

        if (response.statusCode == 200) {
          try {
            final data = jsonDecode(response.body);
            if (data['success'] == true) {
              userId = data['data']['user_id'];
            } else {
              errorMessage = data['message'];
            }
          } catch (e) {
            errorMessage = 'خطا در پردازش اطلاعات سرور: ${response.body}';
          }
        } else {
          errorMessage = 'خطای سرور: ${response.statusCode} - ${response.body}';
        }

        if (userId != null) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('user_name', user.displayName ?? 'کاربر گوگل');
          await prefs.setString('user_email', user.email);
          await prefs.setInt('user_id', userId);
          await prefs.setBool('is_logged_in', true);

          if (mounted) {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (ctx) => const HomeScreen()),
            );
          }
        } else {
          throw Exception(errorMessage ?? 'خطا در ارتباط با سرور هنگام ثبت‌نام');
        }
      } else {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.menu_book_rounded,
                  size: 100,
                  color: Colors.brown[700],
                ),
                const SizedBox(height: 24),
                const Text(
                  'به هفت روز خوش آمدید',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    fontFamily: 'Vazir',
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'هفت روز هفته، قصه بگو',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.grey,
                    fontFamily: 'Vazir',
                  ),
                ),
                const SizedBox(height: 48),
                if (_isLoading)
                  const Center(child: CircularProgressIndicator())
                else
                  OutlinedButton.icon(
                    onPressed: _googleLogin,
                    icon: const Icon(Icons.g_mobiledata_rounded, size: 32, color: Colors.brown),
                    label: const Text(
                      'ورود با حساب گوگل',
                      style: TextStyle(fontSize: 16, color: Colors.brown, fontFamily: 'Vazir'),
                    ),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      side: const BorderSide(color: Colors.brown),
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
