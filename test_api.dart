import 'dart:io';
import 'dart:convert';
import 'package:http/http.dart' as http;

void main() async {
  print('Creating a fake audio file...');
  final file = File('fake_audio.m4a');
  await file.writeAsString('fake audio content for testing');

  print('Sending request to https://haftroz.ir/api/upload.php...');
  final uri = Uri.parse('https://haftroz.ir/api/upload.php');
  
  var request = http.MultipartRequest('POST', uri)
    ..fields['story_id'] = 'test_story_123'
    ..fields['story_title'] = 'تست داستان از سرور'
    ..fields['device_id'] = 'Antigravity AI Agent'
    ..files.add(await http.MultipartFile.fromPath('audio', file.path));

  try {
    var response = await request.send();
    print('Response status code: ${response.statusCode}');
    
    final respStr = await response.stream.bytesToString();
    print('Response body: $respStr');
    
    if (response.statusCode == 200) {
      final jsonMap = jsonDecode(respStr);
      if (jsonMap['success'] == true) {
        print('SUCCESS: The file was uploaded successfully!');
      } else {
        print('FAILED: Server returned success=false');
      }
    }
  } catch (e) {
    print('Error: $e');
  } finally {
    if (await file.exists()) {
      await file.delete();
    }
  }
}
