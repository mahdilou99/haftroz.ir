import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:http/io_client.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AnalyticsService {
  static final AnalyticsService _instance = AnalyticsService._internal();
  factory AnalyticsService() => _instance;
  AnalyticsService._internal();

  String? _deviceId;
  static const String _deviceIdKey = 'app_unique_device_id';

  Future<String> getDeviceId() async {
    if (_deviceId != null) return _deviceId!;
    final prefs = await SharedPreferences.getInstance();
    String? storedId = prefs.getString(_deviceIdKey);
    if (storedId == null || storedId.isEmpty) {
      final rand = Random().nextInt(999999);
      storedId = 'dev_${DateTime.now().millisecondsSinceEpoch}_$rand';
      await prefs.setString(_deviceIdKey, storedId);
    }
    _deviceId = storedId;
    return _deviceId!;
  }

  Future<void> sendEvent({
    required String eventType,
    String? eventLabel,
  }) async {
    try {
      final deviceId = await getDeviceId();
      final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
      final String trackUrl = baseUrl.replaceAll('upload.php', 'track.php');

      final httpClient = HttpClient()
        ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
      final ioClient = IOClient(httpClient);

      final payload = jsonEncode({
        'device_id': deviceId,
        'event_type': eventType,
        'event_label': eventLabel ?? '',
        'app_version': '1.0.0-lite',
      });

      await ioClient.post(
        Uri.parse(trackUrl),
        headers: {
          'Host': 'haftroz.ir',
          'Content-Type': 'application/json',
        },
        body: payload,
      );
    } catch (e) {
      debugPrint('Analytics error (safely ignored): $e');
    }
  }

  Future<void> trackAppOpen() async {
    await sendEvent(eventType: 'app_open', eventLabel: 'App Opened');
  }

  Future<void> trackStoryView(String storyId, String title) async {
    await sendEvent(eventType: 'story_view', eventLabel: title);
  }

  Future<void> trackRecordStart(String storyId, String title) async {
    await sendEvent(eventType: 'record_start', eventLabel: title);
  }

  Future<void> trackShare(String title) async {
    await sendEvent(eventType: 'share_click', eventLabel: title);
  }

  Future<void> trackAdClick(String targetUrl) async {
    await sendEvent(eventType: 'ad_click', eventLabel: targetUrl);
  }
}
