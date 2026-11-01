import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:html_unescape/html_unescape.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import '../data/dummy_data.dart';
import '../models/story.dart';
import '../services/analytics_service.dart';
import 'story_screen.dart';
import 'login_screen.dart';
import 'package:google_sign_in/google_sign_in.dart';
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
  final _unescape = HtmlUnescape();
  SharedPreferences? _prefs;
  String _userName = '';
  Set<String> _recordedStoryIds = {};
  
  // Ad settings
  String _adCode = '';
  String _adTitle = '';
  String _adBannerImage = '';
  String _adTargetUrl = '';
  bool _adActive = false;

  bool _isLoadingStories = true;

  @override
  void initState() {
    super.initState();
    _loadUserData();
    _fetchSettings();
    _fetchStories();
  }

  Future<void> _fetchStories() async {
    try {
      final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
      final String url = baseUrl.replaceAll('upload.php', 'get_stories.php');
      final httpClient = HttpClient()
        ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
      final ioClient = IOClient(httpClient);

      final response = await ioClient.get(Uri.parse(url), headers: {'Host': 'haftroz.ir'});
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final List<dynamic> storiesJson = data['data'];
          dummyStories.clear();
          dummyStories.addAll(storiesJson.map<Story>((json) => Story.fromJson(json)).toList());
        }
      }
    } catch (e) {
      // Error fetching stories, will just show empty list
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingStories = false;
        });
      }
    }
  }

  Future<void> _loadUserData() async {
    _prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _userName = _prefs?.getString('user_name') ?? 'کاربر میهمان';
      });
    }
    
    final userId = _prefs?.getInt('user_id');
    if (userId != null) {
      _fetchRecordedStories(userId);
    }
  }

  Future<void> _fetchSettings() async {
    try {
      final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
      final String url = baseUrl.replaceAll('upload.php', 'get_settings.php');
      final httpClient = HttpClient()
        ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
      final ioClient = IOClient(httpClient);

      final response = await ioClient.get(Uri.parse(url), headers: {'Host': 'haftroz.ir'});
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && mounted) {
          setState(() {
            _adCode = data['ad_code'] ?? '';
            _adTitle = data['ad_title'] ?? '';
            _adBannerImage = data['ad_banner_image'] ?? '';
            _adTargetUrl = data['ad_target_url'] ?? '';
            _adActive = data['ad_active'] == true;
          });
        }
      }
    } catch (e) {}
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
        if (data['success'] == true && mounted) {
          final List<dynamic> stories = data['recorded_stories'];
          setState(() {
            _recordedStoryIds = stories.map((e) => 's$e').toSet();
          });
        }
      }
    } catch (e) {}
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

  Future<void> _handleAdClick() async {
    if (_adTargetUrl.isNotEmpty) {
      AnalyticsService().trackAdClick(_adTargetUrl);
      final uri = Uri.parse(_adTargetUrl);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
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
                border: Border.all(color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.3)),
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

          if (_isLoadingStories)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.all(32.0),
                child: Center(child: CircularProgressIndicator()),
              ),
            )
          else
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
                                clipBehavior: Clip.hardEdge,
                                child: story.imageUrl != null && story.imageUrl!.isNotEmpty
                                    ? Image.network(
                                        'https://haftroz.ir/api/${story.imageUrl}',
                                        fit: BoxFit.cover,
                                        errorBuilder: (context, error, stackTrace) =>
                                            const Center(child: Icon(Icons.image_rounded, color: Colors.grey)),
                                      )
                                    : const Center(
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
                          }),
                        ],
                      ),
                    ),
                  );
                },
                childCount: dummyCategories.length,
              ),
            ),
          ),

          // Smart Ad Banner & Share Button
          SliverToBoxAdapter(
            child: Column(
              children: [
                // Display Smart Banner Ad if active
                if (_adActive && (_adBannerImage.isNotEmpty || _adTitle.isNotEmpty || _adCode.isNotEmpty))
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: InkWell(
                      onTap: _adTargetUrl.isNotEmpty ? _handleAdClick : null,
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        width: double.infinity,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFFFF8E1), Color(0xFFFFECB3)],
                            begin: Alignment.topRight,
                            end: Alignment.bottomLeft,
                          ),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: Colors.amber.shade400, width: 1.5),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.amber.withValues(alpha: 0.15),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Ad Tag
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.amber.shade800,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Text(
                                      'آگهی ویژه',
                                      style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                  if (_adTargetUrl.isNotEmpty)
                                    Row(
                                      children: [
                                        Text('مشاهده و ورود', style: TextStyle(color: Colors.brown.shade700, fontSize: 12, fontWeight: FontWeight.bold)),
                                        const SizedBox(width: 4),
                                        Icon(Icons.arrow_forward_rounded, size: 14, color: Colors.brown.shade700),
                                      ],
                                    ),
                                ],
                              ),
                            ),

                            // Banner Image if available
                            if (_adBannerImage.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: Image.network(
                                    _adBannerImage.startsWith('http') ? _adBannerImage : 'https://haftroz.ir/api/$_adBannerImage',
                                    fit: BoxFit.cover,
                                    errorBuilder: (ctx, err, stack) => const SizedBox.shrink(),
                                  ),
                                ),
                              ),

                            // Ad Title / Code Text
                            if (_adTitle.isNotEmpty || _adCode.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.fromLTRB(14, 6, 14, 12),
                                child: Text(
                                  _adTitle.isNotEmpty ? _adTitle : _adCode,
                                  style: TextStyle(
                                    color: Colors.brown.shade900,
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    height: 1.4,
                                  ),
                                  textAlign: TextAlign.center,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),

                // Share Button
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: ElevatedButton.icon(
                    onPressed: () {
                      AnalyticsService().trackShare("App Share Button");
                      SharePlus.instance.share(
                        ShareParams(
                          text: 'سلام! اپلیکیشن هفت روز رو دانلود کن و داستان‌های خودت رو ضبط کن: https://haftroz.ir/download.html',
                        ),
                      );
                    },
                    icon: const Icon(Icons.share_rounded),
                    label: const Text('ما را با دوستانتان به اشتراک بگذارید', style: TextStyle(fontFamily: 'Vazir', fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.amber,
                      foregroundColor: Colors.black,
                      minimumSize: const Size(double.infinity, 50),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
