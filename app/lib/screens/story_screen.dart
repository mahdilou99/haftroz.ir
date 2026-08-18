import 'dart:io';
import 'dart:convert';
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
        const RecordConfig(encoder: AudioEncoder.aacLc),
        path: filePath,
      );

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
      final userName = prefs.getString('user_name') ?? 'کاربر ناشناس';
      final userId = prefs.getInt('user_id');
      
      bool uploadSuccess = false;
      String? errorMessage;
      
      // Attempt 1: Standard domain
      try {
        final String apiUrl = dotenv.env['API_BASE_URL'] ?? 'https://haftroz.ir/api/upload.php';
        var uri = Uri.parse(apiUrl);
        var request = http.MultipartRequest('POST', uri)
          ..fields['story_id'] = widget.story.id
          ..fields['story_title'] = _unescape.convert(widget.story.title)
          ..fields['device_id'] = userName;
          
        if (userId != null) {
          request.fields['user_id'] = userId.toString();
        }
          
        request.files.add(await http.MultipartFile.fromPath('audio', _recordedFilePath!));

        var response = await request.send();
        if (response.statusCode == 200) {
          uploadSuccess = true;
        } else {
          errorMessage = 'خطای سرور: ${response.statusCode}';
        }
      } catch (e) {
        debugPrint('First attempt failed: $e');
        if (e is SocketException || e.toString().contains('SocketException') || e.toString().contains('Failed host lookup')) {
          // Attempt 2: Direct IP Fallback (Bypass DNS cache)
          debugPrint('Trying IP Fallback...');
          final String fallbackUrl = 'https://91.107.153.4/api/upload.php';
          var uri = Uri.parse(fallbackUrl);
          var request = http.MultipartRequest('POST', uri)
            ..headers['Host'] = 'haftroz.ir'
            ..fields['story_id'] = widget.story.id
            ..fields['story_title'] = _unescape.convert(widget.story.title)
            ..fields['device_id'] = userName;
            
          if (userId != null) {
            request.fields['user_id'] = userId.toString();
          }
          
          request.files.add(await http.MultipartFile.fromPath('audio', _recordedFilePath!));

          // Bypass SSL verification for fallback (since IP doesn't match cert)
          final httpClient = HttpClient()
            ..badCertificateCallback = ((X509Certificate cert, String host, int port) => true);
          final ioClient = IOClient(httpClient);

          var response = await ioClient.send(request);
          if (response.statusCode == 200) {
             uploadSuccess = true;
          } else {
             errorMessage = 'خطای سرور در تلاش دوم: ${response.statusCode}';
          }
        } else {
          errorMessage = e.toString();
        }
      }

      if (mounted) {
        if (uploadSuccess) {
            // Mark as recorded
            await prefs.setBool('recorded_${widget.story.id}', true);
            
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('صدای شما با موفقیت به سرور هفت روز ارسال شد!'),
                backgroundColor: Colors.green,
              ),
            );
            setState(() {
              _recordedFilePath = null;
            });
            Navigator.of(context).pop();
        } else {
            throw Exception(errorMessage ?? 'خطای اتصال به اینترنت');
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('خطا در ارسال: ${e.toString()}'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isUploading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    // Decode HTML entities so &quot; becomes "
    final cleanTitle = _unescape.convert(widget.story.title);
    final cleanContent = _unescape.convert(widget.story.content);

    return Scaffold(
      appBar: AppBar(
        title: Text(cleanTitle),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24.0),
                child: Text(
                  cleanContent,
                  style: const TextStyle(
                    fontSize: 18,
                    height: 2.0,
                    color: Colors.black87,
                  ),
                  textAlign: TextAlign.justify,
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.all(24.0),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    offset: const Offset(0, -4),
                    blurRadius: 16,
                  )
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
