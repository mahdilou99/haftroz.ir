import 'dart:io';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:html_unescape/html_unescape.dart';

import '../models/story.dart';
import '../services/analytics_service.dart';

class StoryScreen extends StatefulWidget {
  final Story story;

  const StoryScreen({super.key, required this.story});

  @override
  State<StoryScreen> createState() => _StoryScreenState();
}

class _StoryScreenState extends State<StoryScreen> {
  final AudioRecorder _audioRecorder = AudioRecorder();
  final AudioPlayer _audioPlayer = AudioPlayer();
  final HtmlUnescape _unescape = HtmlUnescape();

  bool _isRecording = false;
  bool _isPlaying = false;
  bool _isUploading = false;
  String? _recordedFilePath;

  @override
  void initState() {
    super.initState();
    // Track story reading
    AnalyticsService().trackStoryView(widget.story.id, widget.story.title);

    _audioPlayer.onPlayerStateChanged.listen((state) {
      if (mounted) {
        setState(() {
          _isPlaying = state == PlayerState.playing;
        });
      }
    });
  }

  @override
  void dispose() {
    _audioRecorder.dispose();
    _audioPlayer.dispose();
    super.dispose();
  }

  Future<void> _startRecording() async {
    final status = await Permission.microphone.request();
    if (status != PermissionStatus.granted) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('دسترسی به میکروفون داده نشده است')),
        );
      }
      return;
    }

    try {
      final directory = await getApplicationDocumentsDirectory();
      final filePath = '${directory.path}/recording_${DateTime.now().millisecondsSinceEpoch}.m4a';

      await _audioRecorder.start(
        const RecordConfig(
          encoder: AudioEncoder.aacLc,
          bitRate: 64000,
          sampleRate: 44100,
          numChannels: 1,
        ),
        path: filePath,
      );

      AnalyticsService().trackRecordStart(widget.story.id, widget.story.title);

      setState(() {
        _isRecording = true;
        _recordedFilePath = null;
      });
    } catch (e) {
      debugPrint('Error starting recording: $e');
    }
  }

  Future<void> _stopRecording() async {
    try {
      final path = await _audioRecorder.stop();
      setState(() {
        _isRecording = false;
        _recordedFilePath = path;
      });
    } catch (e) {
      debugPrint('Error stopping recording: $e');
    }
  }

  Future<void> _playRecording() async {
    if (_recordedFilePath != null) {
      if (_isPlaying) {
        await _audioPlayer.pause();
      } else {
        await _audioPlayer.play(DeviceFileSource(_recordedFilePath!));
      }
    }
  }

  Future<void> _submitRecording() async {
    if (_recordedFilePath == null) return;

    setState(() {
      _isUploading = true;
    });

    try {
      final prefs = await SharedPreferences.getInstance();
      final int? userId = prefs.getInt('user_id');

      final String baseUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
      
      final httpClient = HttpClient()
        ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
      final ioClient = IOClient(httpClient);

      final request = http.MultipartRequest('POST', Uri.parse(baseUrl));
      request.headers['Host'] = 'haftroz.ir';
      request.fields['story_id'] = widget.story.id.replaceAll('s', '');
      request.fields['story_name'] = widget.story.title;
      
      final String userName = prefs.getString('user_name') ?? 'کاربر مهمان';
      request.fields['user_name'] = userName;
      
      if (userId != null) {
        request.fields['user_id'] = userId.toString();
      }

      request.files.add(await http.MultipartFile.fromPath('audio', _recordedFilePath!));

      http.StreamedResponse streamedResponse;
      try {
        streamedResponse = await ioClient.send(request);
      } catch (e) {
        // Fallback to IP if domain fails
        final String fallbackUrl = 'https://91.107.153.4/api/upload.php';
        final fallbackRequest = http.MultipartRequest('POST', Uri.parse(fallbackUrl));
        fallbackRequest.headers['Host'] = 'haftroz.ir';
        fallbackRequest.fields['story_id'] = widget.story.id.replaceAll('s', '');
        fallbackRequest.fields['story_name'] = widget.story.title;
        fallbackRequest.fields['user_name'] = userName;
        if (userId != null) {
          fallbackRequest.fields['user_id'] = userId.toString();
        }
        fallbackRequest.files.add(await http.MultipartFile.fromPath('audio', _recordedFilePath!));
        streamedResponse = await ioClient.send(fallbackRequest);
      }

      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('صدای شما با موفقیت ارسال شد و پس از بررسی و تایید مدیر منتشر خواهد شد.'),
              backgroundColor: Colors.green,
            ),
          );
          // Mark as recorded
          await prefs.setBool('recorded_${widget.story.id}', true);
          Navigator.of(context).pop();
        }
      } else if (response.statusCode == 413) {
        throw Exception('حجم فایل برای سرور زیاد است (خطای ۴۱۳). تنظیمات آپلود سرور را طبق دستورالعمل افزایش دهید.');
      } else {
        throw Exception('خطای سرور: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('خطا در ارسال صدا: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isUploading = false;
          _recordedFilePath = null;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          _unescape.convert(widget.story.title),
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Story Image
                    if (widget.story.imageUrl != null && widget.story.imageUrl!.isNotEmpty)
                      Container(
                        height: 220,
                        margin: const EdgeInsets.only(bottom: 24),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 10,
                              offset: const Offset(0, 5),
                            ),
                          ],
                        ),
                        clipBehavior: Clip.hardEdge,
                        child: Image.network(
                          'https://haftroz.ir/api/${widget.story.imageUrl}',
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              const Center(child: Icon(Icons.broken_image_rounded, size: 48, color: Colors.grey)),
                        ),
                      ),

                    // Story Text
                    Text(
                      _unescape.convert(widget.story.content),
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontSize: 18,
                        height: 2.0,
                        letterSpacing: 0.2,
                        color: Colors.black87,
                      ),
                      textAlign: TextAlign.justify,
                    ),
                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),

            // Bottom Audio Studio Action Bar
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 15,
                    offset: const Offset(0, -5),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_recordedFilePath != null && !_isRecording) ...[
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('پیش‌نمایش صدای ضبط شده:', style: TextStyle(fontWeight: FontWeight.bold)),
                        const SizedBox(width: 16),
                        IconButton.filledTonal(
                          icon: Icon(_isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded),
                          iconSize: 32,
                          onPressed: _playRecording,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                  ],

                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _isRecording ? colorScheme.error : colorScheme.primaryContainer,
                      foregroundColor: _isRecording ? colorScheme.onError : colorScheme.onPrimaryContainer,
                      padding: const EdgeInsets.symmetric(vertical: 20),
                    ),
                    icon: Icon(_isRecording ? Icons.stop_rounded : Icons.mic_rounded, size: 28),
                    label: Text(
                      _isRecording ? 'پایان ضبط' : 'شروع ضبط داستان',
                      style: const TextStyle(fontSize: 18),
                    ),
                    onPressed: _isUploading ? null : (_isRecording ? _stopRecording : _startRecording),
                  ),

                  if (_recordedFilePath != null && !_isRecording) ...[
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: colorScheme.primary,
                        foregroundColor: colorScheme.onPrimary,
                        padding: const EdgeInsets.symmetric(vertical: 20),
                      ),
                      icon: _isUploading
                          ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Icon(Icons.cloud_upload_rounded, size: 28),
                      label: Text(
                        _isUploading ? 'در حال ارسال به سرور...' : 'تایید و ارسال نهایی',
                        style: const TextStyle(fontSize: 18),
                      ),
                      onPressed: _isUploading ? null : _submitRecording,
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
