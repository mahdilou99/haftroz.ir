import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:html_unescape/html_unescape.dart';
import '../data/dummy_data.dart';
import 'story_screen.dart';
import 'login_screen.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'dart:convert';
import 'dart:io';
import 'package:http/io_client.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String _userName = 'کاربر میهمان';
  SharedPreferences? _prefs;
  final HtmlUnescape _unescape = HtmlUnescape();
  Set<String> _recordedStoryIds = {};

  @override
  void initState() {
    super.initState();
    _loadUserData();
  }

  Future<void> _loadUserData() async {
    _prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = _prefs?.getString('user_name') ?? 'کاربر میهمان';
    });
    
    final userId = _prefs?.getInt('user_id');
    if (userId != null) {
      _fetchRecordedStories(userId);
    }
  }

  Future<void> _fetchRecordedStories(int userId) async {
    try {
      final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
      final String url = baseUrl.replaceAll('upload.php', 'get_user_recordings.php?user_id=$userId');
      
      final httpClient = HttpClient()
        ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
      final ioClient = IOClient(httpClient);

      final response = await ioClient.get(Uri.parse(url), headers: {'Host': 'haftroz.ir'});
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          final List<dynamic> stories = data['recorded_stories'];
          setState(() {
            _recordedStoryIds = stories.map((e) => 's$e').toSet();
          });
        }
      }
    } catch (e) {
      // Fallback
      try {
        final String fallbackUrl = 'https://91.107.153.4/api/get_user_recordings.php?user_id=$userId';
        final httpClient = HttpClient()
          ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
        final ioClient = IOClient(httpClient);
        final response = await ioClient.get(Uri.parse(fallbackUrl), headers: {'Host': 'haftroz.ir'});
        if (response.statusCode == 200) {
          final data = jsonDecode(response.body);
          if (data['success'] == true) {
            final List<dynamic> stories = data['recorded_stories'];
            setState(() {
              _recordedStoryIds = stories.map((e) => 's$e').toSet();
            });
          }
        }
      } catch (e) {}
    }
  }

  Future<void> _logout() async {
    if (_prefs != null) {
      await _prefs!.clear();
      try {
        await GoogleSignIn().disconnect();
      } catch (e) {}
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (ctx) => const LoginScreen()),
        );
      }
    }
  }

  bool _isStoryRecorded(String storyId) {
    return _recordedStoryIds.contains(storyId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      body: CustomScrollView(
        slivers: [
          SliverAppBar.large(
            title: Text(
              'سلام $_userName',
              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white),
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.logout_rounded, color: Colors.white),
                onPressed: _logout,
                tooltip: 'خروج از حساب',
              ),
            ],
            centerTitle: true,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topRight,
                    end: Alignment.bottomLeft,
                    colors: [
                      Theme.of(context).colorScheme.primary,
                      Theme.of(context).colorScheme.tertiary,
                    ],
                  ),
                ),
              ),
            ),
          ),
          
          // Top Banner
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.secondaryContainer,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Theme.of(context).colorScheme.primary.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  Icon(Icons.mic_external_on_rounded, color: Theme.of(context).colorScheme.primary, size: 32),
                  const SizedBox(width: 16),
                  const Expanded(
                    child: Text(
                      'بی صبرانه منتظر شنیدن داستان با صدای شما هستیم!',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
          ),

          SliverPadding(
            padding: const EdgeInsets.all(16.0),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final category = dummyCategories[index];
                  final categoryStories = dummyStories
                      .where((s) => s.categoryId == category.id)
                      .toList();

                  return Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    elevation: 2,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Theme(
                      data: Theme.of(context).copyWith(
                        dividerColor: Colors.transparent,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Text(
                              category.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 18,
                              ),
                            ),
                          ),
                          ...categoryStories.map((story) {
                            final isRecorded = _isStoryRecorded(story.id);
                            final titleColor = isRecorded ? Colors.green : Colors.black87;

                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 8),
                              leading: Container(
                                width: 50,
                                height: 50,
                                decoration: BoxDecoration(
                                  color: Theme.of(context).colorScheme.primaryContainer,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Center(
                                  child: Icon(Icons.image_rounded, color: Colors.grey),
                                ),
                              ),
                              title: Text(
                                _unescape.convert(story.title),
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                  color: titleColor,
                                ),
                              ),
                              subtitle: isRecorded
                                  ? const Text('صدای شما ثبت شده است', style: TextStyle(color: Colors.green, fontSize: 12))
                                  : null,
                              trailing: Icon(
                                isRecorded ? Icons.check_circle_rounded : Icons.arrow_forward_ios_rounded,
                                size: isRecorded ? 24 : 16,
                                color: isRecorded ? Colors.green : Colors.grey,
                              ),
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (ctx) => StoryScreen(story: story),
                                  ),
                                );
                                // Refresh state when coming back in case they recorded it
                                _loadUserData();
                              },
                            );
                          }).toList(),
                        ],
                      ),
                    ),
                  );
                },
                childCount: dummyCategories.length,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
